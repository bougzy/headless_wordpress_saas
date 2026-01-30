import { loadStripe, type Stripe } from "@stripe/stripe-js";
import { STRIPE_PUBLISHABLE_KEY } from "./constants";

let stripePromise: Promise<Stripe | null> | null = null;

/**
 * Get or lazily initialize the Stripe.js client.
 */
export function getStripe(): Promise<Stripe | null> {
  if (!stripePromise) {
    stripePromise = loadStripe(STRIPE_PUBLISHABLE_KEY);
  }
  return stripePromise;
}
