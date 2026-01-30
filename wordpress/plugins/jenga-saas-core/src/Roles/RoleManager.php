<?php

declare(strict_types=1);

namespace Jenga\SaaS\Roles;

/**
 * Manages custom user roles and capabilities for the SaaS platform.
 *
 * Roles:
 *  - jenga_free_member    : Free tier (can read free content)
 *  - jenga_pro_member     : Pro tier (can read free + pro content)
 *  - jenga_premium_member : Premium tier (can read all content)
 *  - jenga_creator        : Can create and publish content
 */
final class RoleManager {

    public const ROLE_FREE    = 'jenga_free_member';
    public const ROLE_PRO     = 'jenga_pro_member';
    public const ROLE_PREMIUM = 'jenga_premium_member';
    public const ROLE_CREATOR = 'jenga_creator';

    // Custom capabilities
    public const CAP_READ_FREE    = 'jenga_read_free';
    public const CAP_READ_PRO     = 'jenga_read_pro';
    public const CAP_READ_PREMIUM = 'jenga_read_premium';
    public const CAP_CREATE       = 'jenga_create_content';
    public const CAP_MANAGE       = 'jenga_manage_platform';

    /**
     * Create all custom roles and assign capabilities.
     * Called on plugin activation.
     */
    public function create_roles(): void {
        // Free member
        add_role(self::ROLE_FREE, __('Jenga Free Member', 'jenga-saas'), [
            'read'              => true,
            self::CAP_READ_FREE => true,
        ]);

        // Pro member
        add_role(self::ROLE_PRO, __('Jenga Pro Member', 'jenga-saas'), [
            'read'              => true,
            self::CAP_READ_FREE => true,
            self::CAP_READ_PRO  => true,
        ]);

        // Premium member
        add_role(self::ROLE_PREMIUM, __('Jenga Premium Member', 'jenga-saas'), [
            'read'                 => true,
            self::CAP_READ_FREE    => true,
            self::CAP_READ_PRO     => true,
            self::CAP_READ_PREMIUM => true,
        ]);

        // Creator (can publish content)
        add_role(self::ROLE_CREATOR, __('Jenga Creator', 'jenga-saas'), [
            'read'                 => true,
            'edit_posts'           => true,
            'publish_posts'        => true,
            'upload_files'         => true,
            self::CAP_READ_FREE    => true,
            self::CAP_READ_PRO     => true,
            self::CAP_READ_PREMIUM => true,
            self::CAP_CREATE       => true,
        ]);

        // Grant admin all custom capabilities
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap(self::CAP_READ_FREE);
            $admin->add_cap(self::CAP_READ_PRO);
            $admin->add_cap(self::CAP_READ_PREMIUM);
            $admin->add_cap(self::CAP_CREATE);
            $admin->add_cap(self::CAP_MANAGE);
        }
    }

    /**
     * Remove all custom roles. Called on plugin deactivation (optional).
     */
    public function remove_roles(): void {
        remove_role(self::ROLE_FREE);
        remove_role(self::ROLE_PRO);
        remove_role(self::ROLE_PREMIUM);
        remove_role(self::ROLE_CREATOR);
    }

    /**
     * Assign the appropriate role based on subscription tier.
     */
    public static function assign_tier_role(int $user_id, int $tier): void {
        $user = get_user_by('ID', $user_id);
        if (!$user) {
            return;
        }

        // Remove old Jenga roles
        $user->remove_role(self::ROLE_FREE);
        $user->remove_role(self::ROLE_PRO);
        $user->remove_role(self::ROLE_PREMIUM);

        // Assign new role based on tier
        $role = match ($tier) {
            0 => self::ROLE_FREE,
            1 => self::ROLE_PRO,
            2 => self::ROLE_PREMIUM,
            default => self::ROLE_FREE,
        };

        $user->add_role($role);
    }

    /**
     * Map a tier integer to the corresponding role slug.
     */
    public static function tier_to_role(int $tier): string {
        return match ($tier) {
            0 => self::ROLE_FREE,
            1 => self::ROLE_PRO,
            2 => self::ROLE_PREMIUM,
            default => self::ROLE_FREE,
        };
    }
}
