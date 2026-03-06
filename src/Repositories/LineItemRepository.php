<?php
/**
 * Line Item Repository
 *
 * CRUD operations for the invoiceforge_invoice_items table.
 *
 * @package    InvoiceForge
 * @subpackage Repositories
 * @since      1.1.0
 */

declare(strict_types=1);

namespace InvoiceForge\Repositories;

use InvoiceForge\Database\Schema;
use InvoiceForge\Models\LineItem;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Line item repository class.
 *
 * @since 1.1.0
 */
class LineItemRepository
{
    /**
     * Find a single line item by its ID.
     *
     * @since 1.1.0
     *
     * @param int $id Line item ID.
     * @return LineItem|null
     */
    public function find(int $id): ?LineItem
    {
        global $wpdb;
        $table = Schema::getInvoiceItemsTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id)
        );

        return $row ? LineItem::fromRow($row) : null;
    }

    /**
     * Get all line items for a given invoice, ordered by item_order.
     *
     * @since 1.1.0
     *
     * @param int $invoice_id The invoice post ID.
     * @return LineItem[]
     */
    public function findByInvoice(int $invoice_id): array
    {
        global $wpdb;
        $table = Schema::getInvoiceItemsTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE invoice_id = %d ORDER BY item_order ASC",
                $invoice_id
            )
        );

        return array_map([LineItem::class, 'fromRow'], $rows ?: []);
    }

    /**
     * Save (insert or update) a line item.
     *
     * @since 1.1.0
     *
     * @param LineItem $item The line item to save.
     * @return int The line item ID.
     */
    public function save(LineItem $item): int
    {
        global $wpdb;
        $table = Schema::getInvoiceItemsTable();

        $data = [
            'invoice_id'  => $item->invoice_id,
            'item_order'  => $item->item_order,
            'description' => $item->description,
            'quantity'    => $item->quantity,
            'unit_price'  => $item->unit_price,
            'tax_rate_id' => $item->tax_rate_id,
            'tax_amount'  => $item->tax_amount,
            'subtotal'    => $item->subtotal,
            'total'       => $item->total,
        ];

        $format = ['%d', '%d', '%s', '%f', '%f', '%d', '%f', '%f', '%f'];

        if ($item->id > 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->update($table, $data, ['id' => $item->id], $format, ['%d']);
            return $item->id;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->insert($table, $data, $format);
        $item->id = (int) $wpdb->insert_id;

        return $item->id;
    }

    /**
     * Delete a single line item.
     *
     * @since 1.1.0
     *
     * @param int $id Line item ID.
     * @return bool
     */
    public function delete(int $id): bool
    {
        global $wpdb;
        $table = Schema::getInvoiceItemsTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return (bool) $wpdb->delete($table, ['id' => $id], ['%d']);
    }

    /**
     * Delete all line items for a given invoice.
     *
     * @since 1.1.0
     *
     * @param int $invoice_id Invoice post ID.
     * @return int Number of rows deleted.
     */
    public function deleteByInvoice(int $invoice_id): int
    {
        global $wpdb;
        $table = Schema::getInvoiceItemsTable();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return (int) $wpdb->delete($table, ['invoice_id' => $invoice_id], ['%d']);
    }
}
