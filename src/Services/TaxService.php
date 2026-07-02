<?php
/**
 * Tax Calculation Service
 *
 * Calculates subtotals, tax amounts, and totals for line items.
 *
 * @package    InvoiceForge
 * @subpackage Services
 * @since      1.1.0
 */

declare(strict_types=1);

namespace InvoiceForge\Services;

use InvoiceForge\Models\LineItem;
use InvoiceForge\Models\TaxRate;
use InvoiceForge\Repositories\TaxRateRepository;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tax calculation service.
 *
 * @since 1.1.0
 */
class TaxService
{
    /**
     * Tax rate repository.
     *
     * @since 1.1.0
     * @var TaxRateRepository
     */
    private TaxRateRepository $taxRateRepo;

    /**
     * Cached tax rates keyed by ID.
     *
     * @since 1.1.0
     * @var array<int, TaxRate>
     */
    private array $rateCache = [];

    /**
     * Constructor.
     *
     * @since 1.1.0
     *
     * @param TaxRateRepository $taxRateRepo Tax rate repository.
     */
    public function __construct(TaxRateRepository $taxRateRepo)
    {
        $this->taxRateRepo = $taxRateRepo;
    }

    /**
     * Calculate a single line item's subtotal, tax, and total.
     *
     * Applies any line-item discount (percentage or fixed) to the gross
     * amount before computing tax, so that tax is calculated on the
     * discounted subtotal.
     *
     * Mutates the passed LineItem in place and returns it.
     *
     * @since 1.1.0
     *
     * @param LineItem $item The line item to calculate.
     * @return LineItem The calculated line item.
     */
    public function calculateItem(LineItem $item): LineItem
    {
        // Gross amount before discount
        $gross = round($item->quantity * $item->unit_price, 4);

        // Apply line-item discount to determine the taxable subtotal
        $discount_amount = $this->calculateItemDiscount($item, $gross);
        $item->subtotal = round($gross - $discount_amount, 4);

        // tax (computed on the discounted subtotal)
        $item->tax_amount = 0.0;
        if ($item->tax_rate_id !== null && $item->tax_rate_id > 0) {
            $rate = $this->getRate($item->tax_rate_id);
            if ($rate !== null) {
                $item->tax_amount = round($item->subtotal * ($rate->rate / 100), 4);
            }
        }

        // total = subtotal + tax
        $item->total = round($item->subtotal + $item->tax_amount, 4);

        return $item;
    }

    /**
     * Calculate the discount amount for a line item.
     *
     * Supports 'percentage' and 'fixed' discount types. The result is
     * clamped so it is never negative and never exceeds the gross amount.
     *
     * @since 1.2.8
     *
     * @param LineItem $item The line item.
     * @param float    $gross The gross amount (quantity * unit_price).
     * @return float The discount amount.
     */
    private function calculateItemDiscount(LineItem $item, float $gross): float
    {
        if ($item->discount_type === null || $item->discount_value === null || $item->discount_value <= 0) {
            return 0.0;
        }

        $discount = 0.0;
        if ($item->discount_type === 'percentage') {
            $discount = $gross * ($item->discount_value / 100);
        } elseif ($item->discount_type === 'fixed') {
            $discount = $item->discount_value;
        }

        // Clamp: discount cannot be negative or exceed the gross amount
        return max(0.0, min($discount, $gross));
    }

    /**
     * Calculate totals for a collection of line items.
     *
     * Each item is calculated individually, then an invoice-level
     * summary is returned.
     *
     * @since 1.1.0
     *
     * @param LineItem[] $items Array of line items.
     * @return array{subtotal: float, tax: float, total: float, items: LineItem[]}
     */
    public function calculateInvoice(array $items): array
    {
        $subtotal = 0.0;
        $tax      = 0.0;

        foreach ($items as $item) {
            $this->calculateItem($item);
            $subtotal += $item->subtotal;
            $tax      += $item->tax_amount;
        }

        return [
            'subtotal' => round($subtotal, 2),
            'tax'      => round($tax, 2),
            'total'    => round($subtotal + $tax, 2),
            'items'    => $items,
        ];
    }

    /**
     * Get a TaxRate by ID, using a local cache.
     *
     * @since 1.1.0
     *
     * @param int $id Tax rate ID.
     * @return TaxRate|null
     */
    private function getRate(int $id): ?TaxRate
    {
        if (!isset($this->rateCache[$id])) {
            $this->rateCache[$id] = $this->taxRateRepo->find($id);
        }

        return $this->rateCache[$id];
    }

    /**
     * Get the tax rate repository.
     *
     * @since 1.1.0
     *
     * @return TaxRateRepository
     */
    public function getTaxRateRepository(): TaxRateRepository
    {
        return $this->taxRateRepo;
    }
}
