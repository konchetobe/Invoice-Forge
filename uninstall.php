<?php
/**
 * InvoiceForge Uninstall Script
 *
 * This file runs when the plugin is uninstalled via WordPress admin.
 * It cleans up all plugin data including options, post types, and custom tables.
 *
 * @package InvoiceForge
 * @since   1.0.0
 */

declare(strict_types=1);

// Exit if not uninstalling from WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Clean up all InvoiceForge data on uninstall
 *
 * @return void
 */
function invoiceforge_uninstall(): void
{
    global $wpdb;

    // Delete all invoices
    $invoices = get_posts([
        'post_type'      => 'if_invoice',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'fields'         => 'ids',
    ]);

    foreach ($invoices as $invoice_id) {
        wp_delete_post($invoice_id, true);
    }

    // Delete all clients
    $clients = get_posts([
        'post_type'      => 'if_client',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'fields'         => 'ids',
    ]);

    foreach ($clients as $client_id) {
        wp_delete_post($client_id, true);
    }

    // Delete plugin options
    $options = [
        'invoiceforge_settings',
        'invoiceforge_last_invoice_number',
        'invoiceforge_last_invoice_year',
        'invoiceforge_db_version',
        'invoiceforge_activated',
    ];

    foreach ($options as $option) {
        delete_option($option);
    }

    // Delete all transients with our prefix
    $wpdb->query(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_invoiceforge_%'"
    );
    $wpdb->query(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_invoiceforge_%'"
    );

    // Drop custom tables (Phase 1B+)
    // phpcs:disable WordPress.DB.DirectDatabaseQuery
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}invoiceforge_invoice_items");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}invoiceforge_payments");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}invoiceforge_tax_rates");
    // phpcs:enable

    // Delete log files
    $upload_dir = wp_upload_dir();
    $log_dir = $upload_dir['basedir'] . '/invoiceforge-logs/';
    
    if (is_dir($log_dir)) {
        $files = glob($log_dir . '*');
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    wp_delete_file($file);
                }
            }
        }
        rmdir($log_dir);
    }

    // Clear any cached data
    wp_cache_flush();

    // Flush rewrite rules
    flush_rewrite_rules();
}

// Run uninstall
invoiceforge_uninstall();
