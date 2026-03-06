<?php
/**
 * Tax Rate Model
 *
 * Represents a configured tax rate.
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
 * Tax rate data model.
 *
 * @since 1.1.0
 */
class TaxRate
{
    /**
     * @since 1.1.0
     * @var int
     */
    public int $id = 0;

    /**
     * @since 1.1.0
     * @var string
     */
    public string $name = '';

    /**
     * Tax percentage (e.g. 20.0000 for 20%).
     *
     * @since 1.1.0
     * @var float
     */
    public float $rate = 0.0;

    /**
     * @since 1.1.0
     * @var string
     */
    public string $country = '';

    /**
     * @since 1.1.0
     * @var bool
     */
    public bool $is_compound = false;

    /**
     * @since 1.1.0
     * @var bool
     */
    public bool $is_default = false;

    /**
     * @since 1.1.0
     * @var bool
     */
    public bool $is_active = true;

    /**
     * Create a TaxRate from a database row.
     *
     * @since 1.1.0
     *
     * @param object $row Database row object.
     * @return self
     */
    public static function fromRow(object $row): self
    {
        $tax = new self();
        $tax->id          = (int) ($row->id ?? 0);
        $tax->name        = (string) ($row->name ?? '');
        $tax->rate        = (float) ($row->rate ?? 0.0);
        $tax->country     = (string) ($row->country ?? '');
        $tax->is_compound = (bool) ($row->is_compound ?? false);
        $tax->is_default  = (bool) ($row->is_default ?? false);
        $tax->is_active   = (bool) ($row->is_active ?? true);

        return $tax;
    }

    /**
     * Convert to array.
     *
     * @since 1.1.0
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'rate'        => $this->rate,
            'country'     => $this->country,
            'is_compound' => $this->is_compound,
            'is_default'  => $this->is_default,
            'is_active'   => $this->is_active,
        ];
    }
}
