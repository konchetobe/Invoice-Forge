<?php
/**
 * Plugin Activator
 *
 * Handles all tasks that need to run during plugin activation.
 *
 * @package    InvoiceForge
 * @subpackage Core
 * @since      1.0.0
 */

declare(strict_types=1);

namespace InvoiceForge\Core;

use InvoiceForge\Database\Schema;
use InvoiceForge\Repositories\TaxRateRepository;
use InvoiceForge\Utilities\Logger;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin activation handler.
 *
 * This class is responsible for:
 * - Creating default options
 * - Setting up initial database entries
 * - Creating custom database tables
 * - Registering capabilities
 * - Creating required directories
 * - Flushing rewrite rules
 *
 * @since 1.0.0
 */
class Activator
{
    /**
     * Run activation tasks.
     *
     * This method is called when the plugin is activated.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public static function activate(): void
    {
        self::createOptions();
        self::createDirectories();
        self::createDatabaseTables();
        self::registerCapabilities();
        self::setActivationFlag();
        self::flushRewriteRules();
        self::logActivation();
    }

    /**
     * Create custom database tables and seed defaults.
     *
     * @since 1.1.0
     *
     * @return void
     */
    private static function createDatabaseTables(): void
    {
        Schema::createTables();

        // Seed default tax rates
        $taxRateRepo = new TaxRateRepository();
        $taxRateRepo->seedDefaults();
    }

    /**
     * Create default plugin options.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private static function createOptions(): void
    {
        // Default settings
        $default_settings = [
            'company_name'       => '',
            'company_email'      => get_option('admin_email', ''),
            'company_phone'      => '',
            'company_address'    => '',
            'company_logo'       => 0,
            'email_from_name'    => get_option('blogname', 'InvoiceForge'),
            'email_from_address' => get_option('admin_email', ''),
            'smtp_enabled'       => false,
            'smtp_host'          => '',
            'smtp_port'          => 587,
            'smtp_username'      => '',
            'smtp_password'      => '',
            'smtp_encryption'    => 'tls',
            'default_currency'   => 'USD',
            'date_format'        => get_option('date_format', 'Y-m-d'),
            'invoice_prefix'     => 'INV',
            'invoice_terms'      => '',
            'invoice_notes'      => '',
        ];

        // Only add if not exists (preserve existing settings on reactivation)
        if (get_option('invoiceforge_settings') === false) {
            add_option('invoiceforge_settings', $default_settings);
        }

        // Invoice numbering
        if (get_option('invoiceforge_last_invoice_number') === false) {
            add_option('invoiceforge_last_invoice_number', 0);
        }

        if (get_option('invoiceforge_last_invoice_year') === false) {
            add_option('invoiceforge_last_invoice_year', (int) gmdate('Y'));
        }

        // Database version for future migrations
        add_option('invoiceforge_db_version', '1.0.0');
    }

    /**
     * Create required directories.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private static function createDirectories(): void
    {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/invoiceforge-logs/';

        // Create logs directory if it doesn't exist
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);

            // Add .htaccess to protect log files
            $htaccess_content = "Order deny,allow\nDeny from all";
            $htaccess_file = $log_dir . '.htaccess';

            if (!file_exists($htaccess_file)) {
                file_put_contents($htaccess_file, $htaccess_content);
            }

            // Add index.php for extra protection
            $index_file = $log_dir . 'index.php';
            if (!file_exists($index_file)) {
                file_put_contents($index_file, '<?php // Silence is golden.');
            }
        }

        // Create PDF temp directory for future use
        $pdf_dir = $upload_dir['basedir'] . '/invoiceforge-pdfs/';
        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);

            // Protect this directory too
            $htaccess_file = $pdf_dir . '.htaccess';
            if (!file_exists($htaccess_file)) {
                file_put_contents($htaccess_file, "Order deny,allow\nDeny from all");
            }

            $index_file = $pdf_dir . 'index.php';
            if (!file_exists($index_file)) {
                file_put_contents($index_file, '<?php // Silence is golden.');
            }
        }
    }

    /**
     * Register custom capabilities for roles.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private static function registerCapabilities(): void
    {
        // Get administrator role
        $admin = get_role('administrator');

        if ($admin === null) {
            return;
        }

        // Invoice capabilities
        $invoice_caps = [
            'edit_if_invoice',
            'read_if_invoice',
            'delete_if_invoice',
            'edit_if_invoices',
            'edit_others_if_invoices',
            'publish_if_invoices',
            'read_private_if_invoices',
            'delete_if_invoices',
            'delete_private_if_invoices',
            'delete_published_if_invoices',
            'delete_others_if_invoices',
            'edit_private_if_invoices',
            'edit_published_if_invoices',
        ];

        // Client capabilities
        $client_caps = [
            'edit_if_client',
            'read_if_client',
            'delete_if_client',
            'edit_if_clients',
            'edit_others_if_clients',
            'publish_if_clients',
            'read_private_if_clients',
            'delete_if_clients',
            'delete_private_if_clients',
            'delete_published_if_clients',
            'delete_others_if_clients',
            'edit_private_if_clients',
            'edit_published_if_clients',
        ];

        // Settings capability
        $settings_caps = [
            'manage_invoiceforge_settings',
        ];

        // Add all capabilities to administrator
        $all_caps = array_merge($invoice_caps, $client_caps, $settings_caps);

        foreach ($all_caps as $cap) {
            $admin->add_cap($cap);
        }

        // Editor role gets invoice and client capabilities (not settings)
        $editor = get_role('editor');
        if ($editor !== null) {
            foreach (array_merge($invoice_caps, $client_caps) as $cap) {
                $editor->add_cap($cap);
            }
        }
    }

    /**
     * Set activation flag for first-run tasks.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private static function setActivationFlag(): void
    {
        set_transient('invoiceforge_activated', true, 60);
    }

    /**
     * Flush rewrite rules.
     *
     * This ensures our custom post types have proper permalinks.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private static function flushRewriteRules(): void
    {
        // Register post types first
        $invoice_post_type = new \InvoiceForge\PostTypes\InvoicePostType(
            new \InvoiceForge\Security\Nonce(),
            new \InvoiceForge\Security\Sanitizer(),
            new \InvoiceForge\Services\NumberingService(new \InvoiceForge\Utilities\Logger())
        );
        $invoice_post_type->register();

        $client_post_type = new \InvoiceForge\PostTypes\ClientPostType(
            new \InvoiceForge\Security\Nonce(),
            new \InvoiceForge\Security\Sanitizer()
        );
        $client_post_type->register();

        // Now flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Log activation event.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private static function logActivation(): void
    {
        $logger = new Logger();
        $logger->info('InvoiceForge activated', [
            'version'     => INVOICEFORGE_VERSION,
            'php_version' => PHP_VERSION,
            'wp_version'  => get_bloginfo('version'),
        ]);
    }
}
