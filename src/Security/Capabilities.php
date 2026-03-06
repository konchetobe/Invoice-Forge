<?php
/**
 * Capabilities Handler
 *
 * Manages capability checks and role management for the plugin.
 *
 * @package    InvoiceForge
 * @subpackage Security
 * @since      1.0.0
 */

declare(strict_types=1);

namespace InvoiceForge\Security;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Capabilities management class.
 *
 * Provides methods for checking user capabilities and managing
 * plugin-specific permissions.
 *
 * @since 1.0.0
 */
class Capabilities
{
    /**
     * Capability for managing plugin settings.
     *
     * @since 1.0.0
     * @var string
     */
    public const MANAGE_SETTINGS = 'manage_invoiceforge_settings';

    /**
     * Capability for editing invoices.
     *
     * @since 1.0.0
     * @var string
     */
    public const EDIT_INVOICES = 'edit_if_invoices';

    /**
     * Capability for editing clients.
     *
     * @since 1.0.0
     * @var string
     */
    public const EDIT_CLIENTS = 'edit_if_clients';

    /**
     * Invoice capability mapping.
     *
     * @since 1.0.0
     * @var array<string, string>
     */
    public const INVOICE_CAPS = [
        'edit_post'          => 'edit_if_invoice',
        'read_post'          => 'read_if_invoice',
        'delete_post'        => 'delete_if_invoice',
        'edit_posts'         => 'edit_if_invoices',
        'edit_others_posts'  => 'edit_others_if_invoices',
        'publish_posts'      => 'publish_if_invoices',
        'read_private_posts' => 'read_private_if_invoices',
    ];

    /**
     * Client capability mapping.
     *
     * @since 1.0.0
     * @var array<string, string>
     */
    public const CLIENT_CAPS = [
        'edit_post'          => 'edit_if_client',
        'read_post'          => 'read_if_client',
        'delete_post'        => 'delete_if_client',
        'edit_posts'         => 'edit_if_clients',
        'edit_others_posts'  => 'edit_others_if_clients',
        'publish_posts'      => 'publish_if_clients',
        'read_private_posts' => 'read_private_if_clients',
    ];

    /**
     * Check if the current user can manage settings.
     *
     * @since 1.0.0
     *
     * @return bool True if user can manage settings.
     */
    public function canManageSettings(): bool
    {
        return current_user_can(self::MANAGE_SETTINGS) || current_user_can('manage_options');
    }

    /**
     * Check if the current user can edit invoices.
     *
     * @since 1.0.0
     *
     * @return bool True if user can edit invoices.
     */
    public function canEditInvoices(): bool
    {
        return current_user_can(self::EDIT_INVOICES) || current_user_can('edit_posts');
    }

    /**
     * Check if the current user can edit a specific invoice.
     *
     * @since 1.0.0
     *
     * @param int $post_id The invoice post ID.
     * @return bool True if user can edit the invoice.
     */
    public function canEditInvoice(int $post_id): bool
    {
        return current_user_can('edit_if_invoice', $post_id) || current_user_can('edit_post', $post_id);
    }

    /**
     * Check if the current user can edit clients.
     *
     * @since 1.0.0
     *
     * @return bool True if user can edit clients.
     */
    public function canEditClients(): bool
    {
        return current_user_can(self::EDIT_CLIENTS) || current_user_can('edit_posts');
    }

    /**
     * Check if the current user can edit a specific client.
     *
     * @since 1.0.0
     *
     * @param int $post_id The client post ID.
     * @return bool True if user can edit the client.
     */
    public function canEditClient(int $post_id): bool
    {
        return current_user_can('edit_if_client', $post_id) || current_user_can('edit_post', $post_id);
    }

    /**
     * Check a capability and die if not authorized.
     *
     * @since 1.0.0
     *
     * @param string      $capability The capability to check.
     * @param int|null    $object_id  Optional object ID for meta capabilities.
     * @param string|null $message    Optional custom error message.
     * @return void
     */
    public function checkOrDie(string $capability, ?int $object_id = null, ?string $message = null): void
    {
        $has_cap = $object_id !== null
            ? current_user_can($capability, $object_id)
            : current_user_can($capability);

        if (!$has_cap) {
            wp_die(
                esc_html($message ?? __('You do not have permission to perform this action.', 'invoiceforge')),
                esc_html__('Access Denied', 'invoiceforge'),
                ['response' => 403, 'back_link' => true]
            );
        }
    }

    /**
     * Check settings capability and die if not authorized.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function checkSettingsOrDie(): void
    {
        if (!$this->canManageSettings()) {
            wp_die(
                esc_html__('You do not have permission to manage InvoiceForge settings.', 'invoiceforge'),
                esc_html__('Access Denied', 'invoiceforge'),
                ['response' => 403, 'back_link' => true]
            );
        }
    }

    /**
     * Check invoices capability and die if not authorized.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function checkInvoicesOrDie(): void
    {
        if (!$this->canEditInvoices()) {
            wp_die(
                esc_html__('You do not have permission to manage invoices.', 'invoiceforge'),
                esc_html__('Access Denied', 'invoiceforge'),
                ['response' => 403, 'back_link' => true]
            );
        }
    }

    /**
     * Check clients capability and die if not authorized.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function checkClientsOrDie(): void
    {
        if (!$this->canEditClients()) {
            wp_die(
                esc_html__('You do not have permission to manage clients.', 'invoiceforge'),
                esc_html__('Access Denied', 'invoiceforge'),
                ['response' => 403, 'back_link' => true]
            );
        }
    }

    /**
     * Get the capability requirement for the admin menu.
     *
     * @since 1.0.0
     *
     * @return string The capability string.
     */
    public function getMenuCapability(): string
    {
        /**
         * Filter the capability required to access the InvoiceForge admin menu.
         *
         * @since 1.0.0
         *
         * @param string $capability The capability string.
         */
        return apply_filters('invoiceforge_admin_menu_capability', 'edit_posts');
    }

    /**
     * Check if the current user is an administrator.
     *
     * @since 1.0.0
     *
     * @return bool True if user is an administrator.
     */
    public function isAdmin(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Get the current user ID.
     *
     * @since 1.0.0
     *
     * @return int The current user ID (0 if not logged in).
     */
    public function getCurrentUserId(): int
    {
        return get_current_user_id();
    }

    /**
     * Check if a user is logged in.
     *
     * @since 1.0.0
     *
     * @return bool True if a user is logged in.
     */
    public function isLoggedIn(): bool
    {
        return is_user_logged_in();
    }
}
