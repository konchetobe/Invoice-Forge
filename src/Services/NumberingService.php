<?php
/**
 * Invoice Numbering Service
 *
 * Handles sequential invoice number generation.
 *
 * @package    InvoiceForge
 * @subpackage Services
 * @since      1.0.0
 */

declare(strict_types=1);

namespace InvoiceForge\Services;

use InvoiceForge\Utilities\Logger;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Invoice numbering service.
 *
 * Generates sequential invoice numbers with year-based reset.
 * Uses transient locking to prevent race conditions.
 *
 * Format: {PREFIX}-{YEAR}-{NUMBER}
 * Example: INV-2025-0001
 *
 * @since 1.0.0
 */
class NumberingService
{
    /**
     * Option name for the last invoice number.
     *
     * @since 1.0.0
     * @var string
     */
    private const OPTION_LAST_NUMBER = 'invoiceforge_last_invoice_number';

    /**
     * Option name for the last invoice year.
     *
     * @since 1.0.0
     * @var string
     */
    private const OPTION_LAST_YEAR = 'invoiceforge_last_invoice_year';

    /**
     * Transient name for the lock.
     *
     * @since 1.0.0
     * @var string
     */
    private const LOCK_TRANSIENT = 'invoiceforge_number_lock';

    /**
     * Lock timeout in seconds.
     *
     * @since 1.0.0
     * @var int
     */
    private const LOCK_TIMEOUT = 10;

    /**
     * Maximum retry attempts for acquiring lock.
     *
     * @since 1.0.0
     * @var int
     */
    private const MAX_RETRIES = 5;

    /**
     * Logger instance.
     *
     * @since 1.0.0
     * @var Logger
     */
    private Logger $logger;

    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param Logger $logger Logger instance.
     */
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Generate the next invoice number.
     *
     * @since 1.0.0
     *
     * @return string The generated invoice number.
     *
     * @throws \RuntimeException If lock cannot be acquired.
     */
    public function generate(): string
    {
        // Acquire lock
        if (!$this->acquireLock()) {
            $this->logger->error('Failed to acquire lock for invoice number generation');
            throw new \RuntimeException('Could not acquire lock for invoice number generation.');
        }

        try {
            $current_year = (int) gmdate('Y');
            $last_year = (int) get_option(self::OPTION_LAST_YEAR, $current_year);
            $last_number = (int) get_option(self::OPTION_LAST_NUMBER, 0);

            // Reset counter if year changed
            if ($current_year !== $last_year) {
                $last_number = 0;
                update_option(self::OPTION_LAST_YEAR, $current_year);
                $this->logger->info('Invoice numbering reset for new year', [
                    'year' => $current_year,
                ]);
            }

            // Increment the number
            $new_number = $last_number + 1;

            // Update the option
            update_option(self::OPTION_LAST_NUMBER, $new_number);

            // Get prefix from settings
            $settings = get_option('invoiceforge_settings', []);
            $prefix = $settings['invoice_prefix'] ?? 'INV';

            // Generate the formatted number
            $invoice_number = $this->format($prefix, $current_year, $new_number);

            $this->logger->debug('Generated invoice number', [
                'number' => $invoice_number,
            ]);

            return $invoice_number;
        } finally {
            // Always release the lock
            $this->releaseLock();
        }
    }

    /**
     * Format an invoice number.
     *
     * @since 1.0.0
     *
     * @param string $prefix The invoice prefix.
     * @param int    $year   The year.
     * @param int    $number The sequential number.
     * @return string The formatted invoice number.
     */
    public function format(string $prefix, int $year, int $number): string
    {
        $formatted = sprintf(
            '%s-%d-%04d',
            strtoupper($prefix),
            $year,
            $number
        );

        /**
         * Filter the invoice number format.
         *
         * @since 1.0.0
         *
         * @param string $formatted The formatted invoice number.
         * @param string $prefix    The invoice prefix.
         * @param int    $year      The year.
         * @param int    $number    The sequential number.
         */
        return apply_filters('invoiceforge_invoice_number_format', $formatted, $prefix, $year, $number);
    }

    /**
     * Parse an invoice number.
     *
     * @since 1.0.0
     *
     * @param string $invoice_number The invoice number to parse.
     * @return array{prefix: string, year: int, number: int}|null Parsed components or null if invalid.
     */
    public function parse(string $invoice_number): ?array
    {
        if (preg_match('/^([A-Z]+)-(\d{4})-(\d+)$/', strtoupper($invoice_number), $matches)) {
            return [
                'prefix' => $matches[1],
                'year'   => (int) $matches[2],
                'number' => (int) $matches[3],
            ];
        }

        return null;
    }

    /**
     * Check if an invoice number already exists.
     *
     * @since 1.0.0
     *
     * @param string $invoice_number The invoice number to check.
     * @return bool True if the number exists.
     */
    public function exists(string $invoice_number): bool
    {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta}
                WHERE meta_key = '_invoice_number'
                AND meta_value = %s",
                $invoice_number
            )
        );

        return (int) $count > 0;
    }

    /**
     * Get the current counter values.
     *
     * @since 1.0.0
     *
     * @return array{year: int, number: int} The current counter values.
     */
    public function getCurrentCounter(): array
    {
        return [
            'year'   => (int) get_option(self::OPTION_LAST_YEAR, (int) gmdate('Y')),
            'number' => (int) get_option(self::OPTION_LAST_NUMBER, 0),
        ];
    }

    /**
     * Preview the next invoice number without generating it.
     *
     * @since 1.0.0
     *
     * @return string The preview invoice number.
     */
    public function preview(): string
    {
        $current_year = (int) gmdate('Y');
        $last_year = (int) get_option(self::OPTION_LAST_YEAR, $current_year);
        $last_number = (int) get_option(self::OPTION_LAST_NUMBER, 0);

        // If year changed, next number would be 1
        $next_number = ($current_year !== $last_year) ? 1 : $last_number + 1;

        $settings = get_option('invoiceforge_settings', []);
        $prefix = $settings['invoice_prefix'] ?? 'INV';

        return $this->format($prefix, $current_year, $next_number);
    }

    /**
     * Reset the counter.
     *
     * @since 1.0.0
     *
     * @param int|null $number The number to reset to (null for 0).
     * @param int|null $year   The year to reset to (null for current year).
     * @return void
     */
    public function reset(?int $number = null, ?int $year = null): void
    {
        $number = $number ?? 0;
        $year = $year ?? (int) gmdate('Y');

        update_option(self::OPTION_LAST_NUMBER, $number);
        update_option(self::OPTION_LAST_YEAR, $year);

        $this->logger->info('Invoice numbering counter reset', [
            'number' => $number,
            'year'   => $year,
        ]);
    }

    /**
     * Acquire a lock for number generation.
     *
     * @since 1.0.0
     *
     * @return bool True if lock was acquired.
     */
    private function acquireLock(): bool
    {
        for ($i = 0; $i < self::MAX_RETRIES; $i++) {
            // Try to set the transient
            if (get_transient(self::LOCK_TRANSIENT) === false) {
                // Set the lock with a unique identifier
                $lock_id = uniqid('lock_', true);
                set_transient(self::LOCK_TRANSIENT, $lock_id, self::LOCK_TIMEOUT);

                // Verify we got the lock (race condition check)
                if (get_transient(self::LOCK_TRANSIENT) === $lock_id) {
                    return true;
                }
            }

            // Wait before retrying (exponential backoff)
            usleep((int) (100000 * pow(2, $i))); // 100ms, 200ms, 400ms, etc.
        }

        return false;
    }

    /**
     * Release the lock.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private function releaseLock(): void
    {
        delete_transient(self::LOCK_TRANSIENT);
    }
}
