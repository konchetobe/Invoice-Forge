<?php
/**
 * Database Schema
 *
 * SQL definitions for custom database tables.
 * Tables will be created in Phase 1B.
 *
 * @package    InvoiceForge
 * @subpackage Database
 * @since      1.0.0
 */

declare(strict_types=1);

namespace InvoiceForge\Database;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database schema management class.
 *
 * Contains SQL definitions for custom tables used by InvoiceForge.
 * These tables will be created in Phase 1B when line items
 * and advanced payment tracking are implemented.
 *
 * @since 1.0.0
 */
class Schema
{
    /**
     * Current schema version.
     *
     * @since 1.0.0
     * @var string
     */
    public const VERSION = '1.0.0';

    /**
     * Table name for invoice items.
     *
     * @since 1.0.0
     * @var string
     */
    private const TABLE_INVOICE_ITEMS = 'invoiceforge_invoice_items';

    /**
     * Table name for payments.
     *
     * @since 1.0.0
     * @var string
     */
    private const TABLE_PAYMENTS = 'invoiceforge_payments';

    /**
     * Table name for tax rates.
     *
     * @since 1.0.0
     * @var string
     */
    private const TABLE_TAX_RATES = 'invoiceforge_tax_rates';

    /**
     * Get the invoice items table name with prefix.
     *
     * @since 1.0.0
     *
     * @return string The full table name.
     */
    public static function getInvoiceItemsTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_INVOICE_ITEMS;
    }

    /**
     * Get the payments table name with prefix.
     *
     * @since 1.0.0
     *
     * @return string The full table name.
     */
    public static function getPaymentsTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_PAYMENTS;
    }

    /**
     * Get the tax rates table name with prefix.
     *
     * @since 1.0.0
     *
     * @return string The full table name.
     */
    public static function getTaxRatesTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_TAX_RATES;
    }

    /**
     * Get the SQL for creating the invoice items table.
     *
     * Phase 1B: This table stores line items for each invoice.
     *
     * @since 1.0.0
     *
     * @return string The CREATE TABLE SQL.
     */
    public static function getInvoiceItemsSQL(): string
    {
        global $wpdb;
        $table_name = self::getInvoiceItemsTable();
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            invoice_id bigint(20) unsigned NOT NULL,
            item_order int(11) unsigned NOT NULL DEFAULT 0,
            description text NOT NULL,
            quantity decimal(10,4) NOT NULL DEFAULT 1.0000,
            unit_price decimal(15,4) NOT NULL DEFAULT 0.0000,
            discount_type enum('percentage','fixed') DEFAULT NULL,
            discount_value decimal(15,4) DEFAULT NULL,
            tax_rate_id bigint(20) unsigned DEFAULT NULL,
            tax_amount decimal(15,4) NOT NULL DEFAULT 0.0000,
            subtotal decimal(15,4) NOT NULL DEFAULT 0.0000,
            total decimal(15,4) NOT NULL DEFAULT 0.0000,
            meta longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY invoice_id (invoice_id),
            KEY item_order (item_order),
            KEY tax_rate_id (tax_rate_id)
        ) {$charset_collate};";
    }

    /**
     * Get the SQL for creating the payments table.
     *
     * Phase 1B/2: This table stores payment records for invoices.
     *
     * @since 1.0.0
     *
     * @return string The CREATE TABLE SQL.
     */
    public static function getPaymentsSQL(): string
    {
        global $wpdb;
        $table_name = self::getPaymentsTable();
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            invoice_id bigint(20) unsigned NOT NULL,
            payment_date datetime NOT NULL,
            amount decimal(15,4) NOT NULL,
            currency varchar(3) NOT NULL DEFAULT 'USD',
            exchange_rate decimal(15,6) DEFAULT 1.000000,
            payment_method varchar(50) NOT NULL,
            gateway varchar(50) DEFAULT NULL,
            transaction_id varchar(255) DEFAULT NULL,
            status enum('pending','completed','failed','refunded','partial_refund') NOT NULL DEFAULT 'pending',
            refund_amount decimal(15,4) DEFAULT NULL,
            refund_date datetime DEFAULT NULL,
            refund_reason text DEFAULT NULL,
            notes text DEFAULT NULL,
            meta longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY invoice_id (invoice_id),
            KEY payment_date (payment_date),
            KEY status (status),
            KEY transaction_id (transaction_id),
            KEY gateway (gateway)
        ) {$charset_collate};";
    }

    /**
     * Get the SQL for creating the tax rates table.
     *
     * Phase 1B: This table stores tax rate configurations.
     *
     * @since 1.0.0
     *
     * @return string The CREATE TABLE SQL.
     */
    public static function getTaxRatesSQL(): string
    {
        global $wpdb;
        $table_name = self::getTaxRatesTable();
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            rate decimal(8,4) NOT NULL,
            country varchar(2) DEFAULT NULL,
            state varchar(100) DEFAULT NULL,
            tax_type varchar(50) DEFAULT 'standard',
            is_compound tinyint(1) NOT NULL DEFAULT 0,
            is_default tinyint(1) NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            priority int(11) NOT NULL DEFAULT 0,
            meta longtext DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY country (country),
            KEY state (state),
            KEY is_active (is_active),
            KEY is_default (is_default)
        ) {$charset_collate};";
    }

    /**
     * Create all custom tables.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public static function createTables(): void
    {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta(self::getInvoiceItemsSQL());
        dbDelta(self::getPaymentsSQL());
        dbDelta(self::getTaxRatesSQL());

        // Store the schema version
        update_option('invoiceforge_db_version', self::VERSION);
    }

    /**
     * Drop all custom tables.
     *
     * WARNING: This permanently deletes all data in these tables.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public static function dropTables(): void
    {
        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery
        $wpdb->query("DROP TABLE IF EXISTS " . self::getInvoiceItemsTable());
        $wpdb->query("DROP TABLE IF EXISTS " . self::getPaymentsTable());
        $wpdb->query("DROP TABLE IF EXISTS " . self::getTaxRatesTable());
        // phpcs:enable

        delete_option('invoiceforge_db_version');
    }

    /**
     * Check if tables exist.
     *
     * @since 1.0.0
     *
     * @return bool True if all tables exist.
     */
    public static function tablesExist(): bool
    {
        global $wpdb;

        $tables = [
            self::getInvoiceItemsTable(),
            self::getPaymentsTable(),
            self::getTaxRatesTable(),
        ];

        foreach ($tables as $table) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $exists = $wpdb->get_var(
                $wpdb->prepare("SHOW TABLES LIKE %s", $table)
            );

            if ($exists !== $table) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if schema needs upgrade.
     *
     * @since 1.0.0
     *
     * @return bool True if upgrade is needed.
     */
    public static function needsUpgrade(): bool
    {
        $current_version = get_option('invoiceforge_db_version', '0.0.0');
        return version_compare($current_version, self::VERSION, '<');
    }

    /**
     * Run schema migrations.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public static function migrate(): void
    {
        $current_version = get_option('invoiceforge_db_version', '0.0.0');

        // Create tables if they don't exist
        if (!self::tablesExist()) {
            self::createTables();
            return;
        }

        // Run version-specific migrations
        // Example:
        // if (version_compare($current_version, '1.1.0', '<')) {
        //     self::migrateToVersion110();
        // }

        // Update version
        update_option('invoiceforge_db_version', self::VERSION);
    }

    /**
     * Get table status information.
     *
     * @since 1.0.0
     *
     * @return array<string, array{exists: bool, rows: int}> Table status info.
     */
    public static function getTableStatus(): array
    {
        global $wpdb;

        $tables = [
            'invoice_items' => self::getInvoiceItemsTable(),
            'payments'      => self::getPaymentsTable(),
            'tax_rates'     => self::getTaxRatesTable(),
        ];

        $status = [];

        foreach ($tables as $key => $table) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery
            $exists = $wpdb->get_var(
                $wpdb->prepare("SHOW TABLES LIKE %s", $table)
            ) === $table;

            $rows = 0;
            if ($exists) {
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $rows = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            }
            // phpcs:enable

            $status[$key] = [
                'exists' => $exists,
                'rows'   => $rows,
            ];
        }

        return $status;
    }
}
