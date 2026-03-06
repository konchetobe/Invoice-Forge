<?php
/**
 * Tax Rate Repository
 *
 * CRUD operations for the invoiceforge_tax_rates table.
 *
 * @package    InvoiceForge
 * @subpackage Repositories
 * @since      1.1.0
 */

declare(strict_types=1);

namespace InvoiceForge\Repositories;

use InvoiceForge\Database\Schema;
use InvoiceForge\Models\TaxRate;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tax rate repository class.
 *
 * @since 1.1.0
 */
class TaxRateRepository
{
    /**
     * Find a single tax rate by ID.
     *
     * @since 1.1.0
     *
     * @param int $id Tax rate ID.
     * @return TaxRate|null
     */
    public function find(int $id): ?TaxRate
    {
        global $wpdb;
        $table = Schema::getTaxRatesTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id)
        );

        return $row ? TaxRate::fromRow($row) : null;
    }

    /**
     * Get all active tax rates.
     *
     * @since 1.1.0
     *
     * @return TaxRate[]
     */
    public function findAllActive(): array
    {
        global $wpdb;
        $table = Schema::getTaxRatesTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $rows = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE is_active = 1 ORDER BY name ASC"
        );

        return array_map([TaxRate::class, 'fromRow'], $rows ?: []);
    }

    /**
     * Get all tax rates (including inactive).
     *
     * @since 1.1.0
     *
     * @return TaxRate[]
     */
    public function findAll(): array
    {
        global $wpdb;
        $table = Schema::getTaxRatesTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $rows = $wpdb->get_results(
            "SELECT * FROM {$table} ORDER BY name ASC"
        );

        return array_map([TaxRate::class, 'fromRow'], $rows ?: []);
    }

    /**
     * Save (insert or update) a tax rate.
     *
     * @since 1.1.0
     *
     * @param TaxRate $taxRate The tax rate to save.
     * @return int The tax rate ID.
     */
    public function save(TaxRate $taxRate): int
    {
        global $wpdb;
        $table = Schema::getTaxRatesTable();

        $data = [
            'name'        => $taxRate->name,
            'rate'        => $taxRate->rate,
            'country'     => $taxRate->country,
            'is_compound' => (int) $taxRate->is_compound,
            'is_default'  => (int) $taxRate->is_default,
            'is_active'   => (int) $taxRate->is_active,
        ];

        $format = ['%s', '%f', '%s', '%d', '%d', '%d'];

        if ($taxRate->id > 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->update($table, $data, ['id' => $taxRate->id], $format, ['%d']);
            return $taxRate->id;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->insert($table, $data, $format);
        $taxRate->id = (int) $wpdb->insert_id;

        return $taxRate->id;
    }

    /**
     * Delete a tax rate.
     *
     * @since 1.1.0
     *
     * @param int $id Tax rate ID.
     * @return bool
     */
    public function delete(int $id): bool
    {
        global $wpdb;
        $table = Schema::getTaxRatesTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return (bool) $wpdb->delete($table, ['id' => $id], ['%d']);
    }

    /**
     * Seed default tax rates if table is empty.
     *
     * @since 1.1.0
     *
     * @return void
     */
    public function seedDefaults(): void
    {
        global $wpdb;
        $table = Schema::getTaxRatesTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        if ($count > 0) {
            return;
        }

        $defaults = [
            ['name' => __('VAT 20%', 'invoiceforge'),  'rate' => 20.0000, 'country' => 'BG', 'is_default' => 1],
            ['name' => __('VAT 9%', 'invoiceforge'),   'rate' => 9.0000,  'country' => 'BG', 'is_default' => 0],
            ['name' => __('VAT 0%', 'invoiceforge'),   'rate' => 0.0000,  'country' => '',   'is_default' => 0],
        ];

        foreach ($defaults as $row) {
            $taxRate = new TaxRate();
            $taxRate->name       = $row['name'];
            $taxRate->rate       = $row['rate'];
            $taxRate->country    = $row['country'];
            $taxRate->is_default = (bool) $row['is_default'];
            $this->save($taxRate);
        }
    }
}
