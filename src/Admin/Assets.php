<?php
/**
 * Admin Assets
 *
 * Handles enqueueing of admin CSS and JavaScript.
 *
 * @package    InvoiceForge
 * @subpackage Admin
 * @since      1.0.0
 */

declare(strict_types=1);

namespace InvoiceForge\Admin;

use InvoiceForge\PostTypes\InvoicePostType;
use InvoiceForge\PostTypes\ClientPostType;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin assets handler.
 *
 * @since 1.0.0
 */
class Assets
{
    /**
     * CSS handle.
     *
     * @since 1.0.0
     * @var string
     */
    private const CSS_HANDLE = 'invoiceforge-admin';

    /**
     * JS handle.
     *
     * @since 1.0.0
     * @var string
     */
    private const JS_HANDLE = 'invoiceforge-admin';

    /**
     * Enqueue admin styles.
     *
     * @since 1.0.0
     *
     * @param string $hook_suffix The current admin page hook suffix.
     * @return void
     */
    public function enqueueStyles(string $hook_suffix): void
    {
        // Only load on InvoiceForge pages
        if (!$this->isInvoiceForgePage($hook_suffix)) {
            return;
        }

        wp_enqueue_style(
            self::CSS_HANDLE,
            INVOICEFORGE_PLUGIN_URL . 'assets/admin/css/admin.css',
            [],
            INVOICEFORGE_VERSION
        );

        /**
         * Fires after InvoiceForge admin styles are enqueued.
         *
         * @since 1.0.0
         *
         * @param string $hook_suffix The current admin page.
         */
        do_action('invoiceforge_admin_styles_enqueued', $hook_suffix);
    }

    /**
     * Enqueue admin scripts.
     *
     * @since 1.0.0
     *
     * @param string $hook_suffix The current admin page hook suffix.
     * @return void
     */
    public function enqueueScripts(string $hook_suffix): void
    {
        // Only load on InvoiceForge pages
        if (!$this->isInvoiceForgePage($hook_suffix)) {
            return;
        }

        // Enqueue WordPress media uploader for settings page
        if ($this->isSettingsPage($hook_suffix)) {
            wp_enqueue_media();
            wp_enqueue_script(
                'invoiceforge-sortable',
                INVOICEFORGE_PLUGIN_URL . 'assets/admin/js/sortable.min.js',
                [],
                '1.15.6',
                true
            );
        }

        wp_enqueue_script(
            self::JS_HANDLE,
            INVOICEFORGE_PLUGIN_URL . 'assets/admin/js/admin.js',
            ['jquery'],
            INVOICEFORGE_VERSION,
            true
        );

        // Localize script with data
        wp_localize_script(self::JS_HANDLE, 'InvoiceForge', $this->getLocalizedData());

        /**
         * Fires after InvoiceForge admin scripts are enqueued.
         *
         * @since 1.0.0
         *
         * @param string $hook_suffix The current admin page.
         */
        do_action('invoiceforge_admin_scripts_enqueued', $hook_suffix);
    }

    /**
     * Check if current page is an InvoiceForge page.
     *
     * @since 1.0.0
     *
     * @param string $hook_suffix The current admin page hook suffix.
     * @return bool True if this is an InvoiceForge page.
     */
    private function isInvoiceForgePage(string $hook_suffix): bool
    {
        global $post_type, $pagenow;

        // Check for our custom menu pages
        if (str_contains($hook_suffix, 'invoiceforge')) {
            return true;
        }

        // Check for our post types
        $our_post_types = [
            InvoicePostType::POST_TYPE,
            ClientPostType::POST_TYPE,
        ];

        if (in_array($post_type, $our_post_types, true)) {
            return true;
        }

        // Check for post type edit/new pages
        if (in_array($pagenow, ['post.php', 'post-new.php', 'edit.php'], true)) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $current_post_type = $_GET['post_type'] ?? '';

            if (in_array($current_post_type, $our_post_types, true)) {
                return true;
            }

            // Check post type from post ID
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
            if ($post_id && in_array(get_post_type($post_id), $our_post_types, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if current page is the settings page.
     *
     * @since 1.0.0
     *
     * @param string $hook_suffix The current admin page hook suffix.
     * @return bool True if this is the settings page.
     */
    private function isSettingsPage(string $hook_suffix): bool
    {
        return str_contains($hook_suffix, 'invoiceforge-settings');
    }

    /**
     * Get localized data for JavaScript.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed> Localized data.
     */
    private function getLocalizedData(): array
    {
        return [
            'ajaxUrl'   => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('invoiceforge_admin'),
            'pluginUrl' => INVOICEFORGE_PLUGIN_URL,
            'i18n'      => [
                'confirmDelete'   => __('Are you sure you want to delete this item?', 'invoiceforge'),
                'saving'          => __('Saving...', 'invoiceforge'),
                'saved'           => __('Saved!', 'invoiceforge'),
                'error'           => __('An error occurred. Please try again.', 'invoiceforge'),
                'selectImage'     => __('Select Image', 'invoiceforge'),
                'useImage'        => __('Use this image', 'invoiceforge'),
                'removeImage'     => __('Remove Image', 'invoiceforge'),
                'required'        => __('This field is required.', 'invoiceforge'),
                'invalidEmail'    => __('Please enter a valid email address.', 'invoiceforge'),
                'invalidDate'     => __('Please enter a valid date.', 'invoiceforge'),
                'invalidNumber'   => __('Please enter a valid number.', 'invoiceforge'),
                'dueDateBefore'   => __('Due date cannot be before invoice date.', 'invoiceforge'),
                'clientRequired'  => __('Please select a client.', 'invoiceforge'),
                'loading'         => __('Loading...', 'invoiceforge'),
            ],
            'settings'  => [
                'currency'   => $this->getDefaultCurrency(),
                'dateFormat' => get_option('date_format', 'Y-m-d'),
            ],
        ];
    }

    /**
     * Get the default currency.
     *
     * @since 1.0.0
     *
     * @return string The default currency code.
     */
    private function getDefaultCurrency(): string
    {
        $settings = get_option('invoiceforge_settings', []);

        /**
         * Filter the default currency.
         *
         * @since 1.0.0
         *
         * @param string $currency The default currency code.
         */
        return apply_filters(
            'invoiceforge_default_currency',
            $settings['default_currency'] ?? 'USD'
        );
    }

    /**
     * Get the CSS handle.
     *
     * @since 1.0.0
     *
     * @return string The CSS handle.
     */
    public function getCssHandle(): string
    {
        return self::CSS_HANDLE;
    }

    /**
     * Get the JS handle.
     *
     * @since 1.0.0
     *
     * @return string The JS handle.
     */
    public function getJsHandle(): string
    {
        return self::JS_HANDLE;
    }
}
