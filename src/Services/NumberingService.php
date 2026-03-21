<?php
/**
 * Invoice Numbering Service
 *
 * Handles sequential invoice number generation with full customization support.
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
 * Generates sequential invoice numbers with configurable prefix, suffix,
 * date patterns, counter reset modes, custom start number, and padding.
 * Uses transient locking to prevent race conditions.
 *
 * Format: {PREFIX}-{DATE_SEGMENT}-{PADDED_NUMBER}-{SUFFIX}
 * Example (default): INV-2026-0001
 * Example (custom): FAK-202603-00001-BG
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
     * Option name for the last invoice month.
     *
     * @since 1.4.0
     * @var string
     */
    private const OPTION_LAST_MONTH = 'invoiceforge_last_invoice_month';

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
     * Get the numbering configuration from settings.
     *
     * @since 1.4.0
     *
     * @return array{
     *   prefix: string,
     *   suffix: string,
     *   date_pattern: string,
     *   counter_reset: string,
     *   start_number: int,
     *   padding: int
     * }
     */
    public function getNumberingConfig(): array
    {
        $settings = get_option('invoiceforge_settings', []);

        return [
            'prefix'        => (string) ($settings['invoice_prefix'] ?? 'INV'),
            'suffix'        => (string) ($settings['invoice_suffix'] ?? ''),
            'date_pattern'  => (string) ($settings['invoice_date_pattern'] ?? 'Y'),
            'counter_reset' => (string) ($settings['invoice_counter_reset'] ?? 'yearly'),
            'start_number'  => max(1, (int) ($settings['invoice_start_number'] ?? 1)),
            'padding'       => max(1, min(10, (int) ($settings['invoice_number_padding'] ?? 4))),
        ];
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
            $config        = $this->getNumberingConfig();
            $current_year  = (int) gmdate('Y');
            $current_month = (int) gmdate('n');
            $last_year     = (int) get_option(self::OPTION_LAST_YEAR, $current_year);
            $last_month    = (int) get_option(self::OPTION_LAST_MONTH, $current_month);
            $last_number   = (int) get_option(self::OPTION_LAST_NUMBER, 0);

            // Determine if we need to reset the counter
            $should_reset = false;
            switch ($config['counter_reset']) {
                case 'yearly':
                    $should_reset = ($current_year !== $last_year);
                    break;
                case 'monthly':
                    $should_reset = ($current_year !== $last_year || $current_month !== $last_month);
                    break;
                case 'never':
                    $should_reset = false;
                    break;
            }

            if ($should_reset) {
                // Set to start_number - 1 so next increment produces start_number
                $last_number = max(0, $config['start_number'] - 1);
                update_option(self::OPTION_LAST_YEAR, $current_year);
                update_option(self::OPTION_LAST_MONTH, $current_month);
                $this->logger->info('Invoice numbering reset', [
                    'year'         => $current_year,
                    'month'        => $current_month,
                    'reset_mode'   => $config['counter_reset'],
                    'start_number' => $config['start_number'],
                ]);
            }

            // Increment the number
            $new_number = $last_number + 1;

            // Update the option
            update_option(self::OPTION_LAST_NUMBER, $new_number);
            if (!$should_reset) {
                // Keep year/month in sync even if not resetting
                update_option(self::OPTION_LAST_YEAR, $current_year);
                update_option(self::OPTION_LAST_MONTH, $current_month);
            }

            // Generate the formatted number
            $invoice_number = $this->formatCustom($config, $current_year, $current_month, $new_number);

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
     * Format an invoice number using the full config array.
     *
     * @since 1.4.0
     *
     * @param array  $config        Numbering config from getNumberingConfig().
     * @param int    $year          The year.
     * @param int    $month         The month.
     * @param int    $number        The sequential number.
     * @return string The formatted invoice number.
     */
    public function formatCustom(array $config, int $year, int $month, int $number): string
    {
        $segments = [];

        // Prefix
        $prefix = strtoupper((string) ($config['prefix'] ?? 'INV'));
        if ($prefix !== '') {
            $segments[] = $prefix;
        }

        // Date segment
        $date_pattern = $config['date_pattern'] ?? 'Y';
        switch ($date_pattern) {
            case 'Y':
                $segments[] = (string) $year;
                break;
            case 'Ym':
                $segments[] = sprintf('%04d%02d', $year, $month);
                break;
            case 'Y-m':
                $segments[] = sprintf('%04d-%02d', $year, $month);
                break;
            case 'none':
                // No date segment
                break;
            default:
                $segments[] = (string) $year;
                break;
        }

        // Padded number
        $padding   = max(1, min(10, (int) ($config['padding'] ?? 4)));
        $segments[] = str_pad((string) $number, $padding, '0', STR_PAD_LEFT);

        // Suffix
        $suffix = strtoupper((string) ($config['suffix'] ?? ''));
        if ($suffix !== '') {
            $segments[] = $suffix;
        }

        $formatted = implode('-', $segments);

        /**
         * Filter the invoice number format.
         *
         * @since 1.0.0
         *
         * @param string $formatted The formatted invoice number.
         * @param array  $config    The numbering config.
         * @param int    $year      The year.
         * @param int    $number    The sequential number.
         */
        return apply_filters('invoiceforge_invoice_number_format', $formatted, $config['prefix'] ?? 'INV', $year, $number);
    }

    /**
     * Format an invoice number (legacy method for backward compatibility).
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
        $config = $this->getNumberingConfig();
        $config['prefix'] = $prefix;

        return $this->formatCustom($config, $year, (int) gmdate('n'), $number);
    }

    /**
     * Parse an invoice number (lenient for flexible formats).
     *
     * @since 1.0.0
     *
     * @param string $invoice_number The invoice number to parse.
     * @return array{prefix: string, year: int, number: int}|null Parsed components or null if invalid.
     */
    public function parse(string $invoice_number): ?array
    {
        $upper = strtoupper($invoice_number);

        // Try new flexible format: segments separated by dashes
        // Attempt to find the numeric counter (last all-digit group that looks like a counter)
        $parts = explode('-', $upper);
        if (count($parts) >= 2) {
            // Find year (4-digit number) and counter (padded number)
            $prefix = '';
            $year   = 0;
            $number = 0;

            foreach ($parts as $i => $part) {
                if (preg_match('/^\d{4}$/', $part) && (int) $part > 1900) {
                    // This looks like a year
                    $year   = (int) $part;
                    $prefix = implode('-', array_slice($parts, 0, $i));
                    // Number is the next purely-numeric segment
                    for ($j = $i + 1; $j < count($parts); $j++) {
                        if (preg_match('/^\d+$/', $parts[$j])) {
                            $number = (int) $parts[$j];
                            break;
                        }
                    }
                    break;
                }
                // Check for YYYYMM format (6 digits)
                if (preg_match('/^\d{6}$/', $part) && (int) substr($part, 0, 4) > 1900) {
                    $year   = (int) substr($part, 0, 4);
                    $prefix = implode('-', array_slice($parts, 0, $i));
                    for ($j = $i + 1; $j < count($parts); $j++) {
                        if (preg_match('/^\d+$/', $parts[$j])) {
                            $number = (int) $parts[$j];
                            break;
                        }
                    }
                    break;
                }
            }

            if ($year > 0 && $number > 0) {
                return [
                    'prefix' => $prefix ?: (count($parts) > 0 ? $parts[0] : ''),
                    'year'   => $year,
                    'number' => $number,
                ];
            }
        }

        // Fallback: legacy format PREFIX-YEAR-NUMBER
        if (preg_match('/^([A-Z]+)-(\d{4})-(\d+)$/', $upper, $matches)) {
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
        $config        = $this->getNumberingConfig();
        $current_year  = (int) gmdate('Y');
        $current_month = (int) gmdate('n');
        $last_year     = (int) get_option(self::OPTION_LAST_YEAR, $current_year);
        $last_month    = (int) get_option(self::OPTION_LAST_MONTH, $current_month);
        $last_number   = (int) get_option(self::OPTION_LAST_NUMBER, 0);

        // Determine if counter would reset
        $would_reset = false;
        switch ($config['counter_reset']) {
            case 'yearly':
                $would_reset = ($current_year !== $last_year);
                break;
            case 'monthly':
                $would_reset = ($current_year !== $last_year || $current_month !== $last_month);
                break;
            case 'never':
                $would_reset = false;
                break;
        }

        if ($would_reset) {
            $next_number = $config['start_number'];
        } else {
            $next_number = $last_number + 1;
        }

        return $this->formatCustom($config, $current_year, $current_month, $next_number);
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
        $year   = $year ?? (int) gmdate('Y');
        $month  = (int) gmdate('n');

        update_option(self::OPTION_LAST_NUMBER, $number);
        update_option(self::OPTION_LAST_YEAR, $year);
        update_option(self::OPTION_LAST_MONTH, $month);

        $this->logger->info('Invoice numbering counter reset', [
            'number' => $number,
            'year'   => $year,
            'month'  => $month,
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
