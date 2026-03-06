<?php
/**
 * Plugin Deactivator
 *
 * Handles all tasks that need to run during plugin deactivation.
 *
 * @package    InvoiceForge
 * @subpackage Core
 * @since      1.0.0
 */

declare(strict_types=1);

namespace InvoiceForge\Core;

use InvoiceForge\Utilities\Logger;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin deactivation handler.
 *
 * This class is responsible for:
 * - Flushing rewrite rules
 * - Cleaning up transients
 * - Logging deactivation
 *
 * Note: Data is preserved during deactivation.
 * Full cleanup only happens on uninstall.
 *
 * @since 1.0.0
 */
class Deactivator
{
    /**
     * Run deactivation tasks.
     *
     * This method is called when the plugin is deactivated.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public static function deactivate(): void
    {
        self::clearTransients();
        self::flushRewriteRules();
        self::logDeactivation();
    }

    /**
     * Clear plugin transients.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private static function clearTransients(): void
    {
        global $wpdb;

        // Delete all transients with our prefix
        // phpcs:disable WordPress.DB.DirectDatabaseQuery
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_invoiceforge_%'"
        );
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_invoiceforge_%'"
        );
        // phpcs:enable

        // Clear specific transients
        delete_transient('invoiceforge_activated');
        delete_transient('invoiceforge_number_lock');
    }

    /**
     * Flush rewrite rules.
     *
     * This removes our custom post type rewrite rules.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private static function flushRewriteRules(): void
    {
        flush_rewrite_rules();
    }

    /**
     * Log deactivation event.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private static function logDeactivation(): void
    {
        $logger = new Logger();
        $logger->info('InvoiceForge deactivated', [
            'version' => INVOICEFORGE_VERSION,
        ]);
    }
}
