<?php
/**
 * Line Item Model
 *
 * Represents a single line item on an invoice.
 *
 * @package    InvoiceForge
 * @subpackage Models
 * @since      1.1.0
 */

declare(strict_types=1);

namespace InvoiceForge\Models;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Line item data model.
 *
 * @since 1.1.0
 */
class LineItem
{
    /**
     * Line item ID.
     *
     * @since 1.1.0
     * @var int
     */
    public int $id = 0;

    /**
     * Parent invoice post ID.
     *
     * @since 1.1.0
     * @var int
     */
    public int $invoice_id = 0;

    /**
     * Sort order within the invoice.
     *
     * @since 1.1.0
     * @var int
     */
    public int $item_order = 0;

    /**
     * Item description.
     *
     * @since 1.1.0
     * @var string
     */
    public string $description = '';

    /**
     * Quantity.
     *
     * @since 1.1.0
     * @var float
     */
    public float $quantity = 1.0;

    /**
     * Unit price.
     *
     * @since 1.1.0
     * @var float
     */
    public float $unit_price = 0.0;

    /**
     * Tax rate ID (references invoiceforge_tax_rates table).
     *
     * @since 1.1.0
     * @var int|null
     */
    public ?int $tax_rate_id = null;

    /**
     * Computed tax amount for this item.
     *
     * @since 1.1.0
     * @var float
     */
    public float $tax_amount = 0.0;

    /**
     * Subtotal before tax (quantity * unit_price - discount).
     *
     * @since 1.1.0
     * @var float
     */
    public float $subtotal = 0.0;

    /**
     * Total including tax.
     *
     * @since 1.1.0
     * @var float
     */
    public float $total = 0.0;

    /**
     * Create a LineItem from a database row.
     *
     * @since 1.1.0
     *
     * @param object $row Database row object.
     * @return self
     */
    public static function fromRow(object $row): self
    {
        $item = new self();
        $item->id          = (int) ($row->id ?? 0);
        $item->invoice_id  = (int) ($row->invoice_id ?? 0);
        $item->item_order  = (int) ($row->item_order ?? 0);
        $item->description = (string) ($row->description ?? '');
        $item->quantity    = (float) ($row->quantity ?? 1.0);
        $item->unit_price  = (float) ($row->unit_price ?? 0.0);
        $item->tax_rate_id = isset($row->tax_rate_id) && $row->tax_rate_id !== null ? (int) $row->tax_rate_id : null;
        $item->tax_amount  = (float) ($row->tax_amount ?? 0.0);
        $item->subtotal    = (float) ($row->subtotal ?? 0.0);
        $item->total       = (float) ($row->total ?? 0.0);

        return $item;
    }

    /**
     * Create a LineItem from a POST data array.
     *
     * @since 1.1.0
     *
     * @param array<string, mixed> $data Raw form data for one item.
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $item = new self();
        $item->id          = absint($data['id'] ?? 0);
        $item->description = sanitize_text_field((string) ($data['description'] ?? ''));
        $item->quantity    = round((float) ($data['quantity'] ?? 1), 4);
        $item->unit_price  = round((float) ($data['unit_price'] ?? 0), 4);
        $item->tax_rate_id = isset($data['tax_rate_id']) && $data['tax_rate_id'] !== '' ? absint($data['tax_rate_id']) : null;

        return $item;
    }

    /**
     * Convert the model to an array for JSON responses.
     *
     * @since 1.1.0
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'invoice_id'  => $this->invoice_id,
            'item_order'  => $this->item_order,
            'description' => $this->description,
            'quantity'    => $this->quantity,
            'unit_price'  => $this->unit_price,
            'tax_rate_id' => $this->tax_rate_id,
            'tax_amount'  => $this->tax_amount,
            'subtotal'    => $this->subtotal,
            'total'       => $this->total,
        ];
    }
}
