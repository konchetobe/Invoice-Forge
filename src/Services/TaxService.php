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
     * Mutates the passed LineItem in place and returns it.
     *
     * @since 1.1.0
     *
     * @param LineItem $item The line item to calculate.
     * @return LineItem The calculated line item.
     */
    public function calculateItem(LineItem $item): LineItem
    {
        // subtotal = qty * unit_price
        $item->subtotal = round($item->quantity * $item->unit_price, 4);

        // tax
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
