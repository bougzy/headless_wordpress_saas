<?php

declare(strict_types=1);

namespace Jenga\SaaS\Payments;

use Jenga\SaaS\PostTypes\Plan;
use Jenga\SaaS\PostTypes\Subscription;
use Jenga\SaaS\Roles\RoleManager;
use Stripe\Stripe;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\BillingPortal\Session as PortalSession;
use Stripe\Webhook;
use Stripe\Event;

/**
 * Stripe payment integration.
 *
 * Handles checkout session creation, billing portal access,
 * subscription cancellation, and webhook event processing.
 */
final class StripeHandler {

    public function __construct() {
        Stripe::setApiKey(JENGA_STRIPE_SECRET_KEY);
    }

    /**
     * Create a Stripe Checkout Session for a subscription.
     */
    public function create_checkout_session(int $user_id, int $plan_id, string $stripe_price_id): CheckoutSession {
        $user = get_user_by('ID', $user_id);

        // Get or create Stripe customer
        $customer_id = get_user_meta($user_id, '_jenga_stripe_customer_id', true);

        $params = [
            'mode'                => 'subscription',
            'line_items'          => [['price' => $stripe_price_id, 'quantity' => 1]],
            'success_url'         => JENGA_FRONTEND_URL . '/dashboard?checkout=success&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'          => JENGA_FRONTEND_URL . '/pricing?checkout=cancelled',
            'client_reference_id' => (string) $user_id,
            'metadata'            => [
                'user_id' => $user_id,
                'plan_id' => $plan_id,
            ],
            'subscription_data' => [
                'metadata' => [
                    'user_id' => $user_id,
                    'plan_id' => $plan_id,
                ],
            ],
        ];

        if ($customer_id) {
            $params['customer'] = $customer_id;
        } else {
            $params['customer_email'] = $user->user_email;
        }

        return CheckoutSession::create($params);
    }

    /**
     * Create a Stripe Billing Portal session for subscription management.
     */
    public function create_portal_session(string $customer_id): PortalSession {
        return PortalSession::create([
            'customer'   => $customer_id,
            'return_url' => JENGA_FRONTEND_URL . '/dashboard',
        ]);
    }

    /**
     * Cancel a subscription in Stripe (at period end).
     */
    public function cancel_subscription(string $stripe_subscription_id): void {
        $sub = \Stripe\Subscription::retrieve($stripe_subscription_id);
        $sub->cancel();
    }

    /**
     * Handle incoming Stripe webhook event.
     *
     * @throws \Exception On signature verification failure.
     */
    public function handle_webhook(string $payload, string $signature): void {
        $event = Webhook::constructEvent(
            $payload,
            $signature,
            JENGA_STRIPE_WEBHOOK_SECRET
        );

        match ($event->type) {
            'checkout.session.completed'       => $this->on_checkout_completed($event),
            'customer.subscription.updated'    => $this->on_subscription_updated($event),
            'customer.subscription.deleted'    => $this->on_subscription_deleted($event),
            'invoice.payment_failed'           => $this->on_payment_failed($event),
            default                            => null, // Ignore unhandled events
        };
    }

    /**
     * Checkout completed — create internal subscription record.
     */
    private function on_checkout_completed(Event $event): void {
        $session   = $event->data->object;
        $user_id   = (int) ($session->metadata->user_id ?? $session->client_reference_id);
        $plan_id   = (int) ($session->metadata->plan_id ?? 0);

        if (!$user_id || !$plan_id) {
            error_log('[Jenga] Checkout completed but missing user_id or plan_id.');
            return;
        }

        // Store Stripe customer ID on the user
        if ($session->customer) {
            update_user_meta($user_id, '_jenga_stripe_customer_id', $session->customer);
        }

        // Retrieve the Stripe subscription object for period info
        $stripe_sub = \Stripe\Subscription::retrieve($session->subscription);

        // Create internal subscription record
        $sub_id = wp_insert_post([
            'post_type'   => Subscription::POST_TYPE,
            'post_status' => 'publish',
            'post_title'  => sprintf('Sub: User %d - Plan %d', $user_id, $plan_id),
        ]);

        if (is_wp_error($sub_id)) {
            error_log('[Jenga] Failed to create subscription post: ' . $sub_id->get_error_message());
            return;
        }

        update_post_meta($sub_id, '_jenga_sub_user_id', $user_id);
        update_post_meta($sub_id, '_jenga_sub_plan_id', $plan_id);
        update_post_meta($sub_id, '_jenga_sub_stripe_id', $session->subscription);
        update_post_meta($sub_id, '_jenga_sub_stripe_customer', $session->customer);
        update_post_meta($sub_id, '_jenga_sub_status', Subscription::STATUS_ACTIVE);
        update_post_meta($sub_id, '_jenga_sub_current_period_end', $stripe_sub->current_period_end);
        update_post_meta($sub_id, '_jenga_sub_created_at', time());

        // Assign the user the correct role based on plan tier
        $tier = (int) get_post_meta($plan_id, '_jenga_plan_tier', true);
        RoleManager::assign_tier_role($user_id, $tier);

        // Trigger frontend revalidation
        $this->trigger_revalidation(['/dashboard', '/content']);
    }

    /**
     * Subscription updated in Stripe (plan change, renewal, etc.).
     */
    private function on_subscription_updated(Event $event): void {
        $stripe_sub = $event->data->object;
        $sub_post   = Subscription::get_by_stripe_id($stripe_sub->id);

        if (!$sub_post) {
            return;
        }

        $status = match ($stripe_sub->status) {
            'active'   => Subscription::STATUS_ACTIVE,
            'trialing' => Subscription::STATUS_TRIALING,
            'past_due' => Subscription::STATUS_PAST_DUE,
            default    => Subscription::STATUS_EXPIRED,
        };

        update_post_meta($sub_post->ID, '_jenga_sub_status', $status);
        update_post_meta($sub_post->ID, '_jenga_sub_current_period_end', $stripe_sub->current_period_end);

        // Update user role based on new status
        $user_id = (int) get_post_meta($sub_post->ID, '_jenga_sub_user_id', true);
        if ($status === Subscription::STATUS_ACTIVE || $status === Subscription::STATUS_TRIALING) {
            $plan_id = (int) get_post_meta($sub_post->ID, '_jenga_sub_plan_id', true);
            $tier    = (int) get_post_meta($plan_id, '_jenga_plan_tier', true);
            RoleManager::assign_tier_role($user_id, $tier);
        } else {
            RoleManager::assign_tier_role($user_id, 0);
        }
    }

    /**
     * Subscription deleted (fully cancelled).
     */
    private function on_subscription_deleted(Event $event): void {
        $stripe_sub = $event->data->object;
        $sub_post   = Subscription::get_by_stripe_id($stripe_sub->id);

        if (!$sub_post) {
            return;
        }

        update_post_meta($sub_post->ID, '_jenga_sub_status', Subscription::STATUS_EXPIRED);

        $user_id = (int) get_post_meta($sub_post->ID, '_jenga_sub_user_id', true);
        RoleManager::assign_tier_role($user_id, 0);

        $this->trigger_revalidation(['/dashboard']);
    }

    /**
     * Invoice payment failed.
     */
    private function on_payment_failed(Event $event): void {
        $invoice    = $event->data->object;
        $stripe_sub = $invoice->subscription;

        if (!$stripe_sub) {
            return;
        }

        $sub_post = Subscription::get_by_stripe_id($stripe_sub);
        if ($sub_post) {
            update_post_meta($sub_post->ID, '_jenga_sub_status', Subscription::STATUS_PAST_DUE);
        }
    }

    /**
     * Trigger ISR revalidation on the Next.js frontend.
     */
    private function trigger_revalidation(array $paths): void {
        wp_remote_post(JENGA_FRONTEND_URL . '/api/revalidate', [
            'body'    => wp_json_encode([
                'paths'  => $paths,
                'secret' => JENGA_REVALIDATION_SECRET,
            ]),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 5,
        ]);
    }
}
