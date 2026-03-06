<?php
/**
 * Input Sanitizer
 *
 * Provides sanitization methods for various data types.
 *
 * @package    InvoiceForge
 * @subpackage Security
 * @since      1.0.0
 */

declare(strict_types=1);

namespace InvoiceForge\Security;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Input sanitization class.
 *
 * Provides methods for sanitizing user input based on expected data types.
 * Always sanitize input before validation and storage.
 *
 * @since 1.0.0
 */
class Sanitizer
{
    /**
     * Sanitize a text field.
     *
     * @since 1.0.0
     *
     * @param mixed $value The value to sanitize.
     * @return string The sanitized string.
     */
    public function text(mixed $value): string
    {
        if (!is_string($value) && !is_numeric($value)) {
            return '';
        }

        return sanitize_text_field((string) $value);
    }

    /**
     * Sanitize a textarea field (preserves line breaks).
     *
     * @since 1.0.0
     *
     * @param mixed $value The value to sanitize.
     * @return string The sanitized string.
     */
    public function textarea(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return sanitize_textarea_field($value);
    }

    /**
     * Sanitize an email address.
     *
     * @since 1.0.0
     *
     * @param mixed $value The value to sanitize.
     * @return string The sanitized email or empty string if invalid.
     */
    public function email(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return sanitize_email($value);
    }

    /**
     * Sanitize an integer.
     *
     * @since 1.0.0
     *
     * @param mixed $value The value to sanitize.
     * @return int The sanitized integer.
     */
    public function int(mixed $value): int
    {
        return (int) $value;
    }

    /**
     * Sanitize a positive integer (absint).
     *
     * @since 1.0.0
     *
     * @param mixed $value The value to sanitize.
     * @return int The sanitized positive integer.
     */
    public function absint(mixed $value): int
    {
        return absint($value);
    }

    /**
     * Sanitize a float/decimal value.
     *
     * @since 1.0.0
     *
     * @param mixed $value     The value to sanitize.
     * @param int   $precision The number of decimal places (default: 2).
     * @return float The sanitized float.
     */
    public function float(mixed $value, int $precision = 2): float
    {
        $float = (float) $value;
        return round($float, $precision);
    }

    /**
     * Sanitize a monetary amount.
     *
     * @since 1.0.0
     *
     * @param mixed $value The value to sanitize.
     * @return float The sanitized monetary amount.
     */
    public function money(mixed $value): float
    {
        // Remove any non-numeric characters except decimal point and minus
        $clean = preg_replace('/[^0-9.\-]/', '', (string) $value);
        return $this->float($clean);
    }

    /**
     * Sanitize a boolean value.
     *
     * @since 1.0.0
     *
     * @param mixed $value The value to sanitize.
     * @return bool The sanitized boolean.
     */
    public function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) $value;
    }

    /**
     * Sanitize a URL.
     *
     * @since 1.0.0
     *
     * @param mixed         $value     The value to sanitize.
     * @param string[]|null $protocols Allowed protocols (null for default).
     * @return string The sanitized URL.
     */
    public function url(mixed $value, ?array $protocols = null): string
    {
        if (!is_string($value)) {
            return '';
        }

        if ($protocols === null) {
            return esc_url_raw($value);
        }

        return esc_url_raw($value, $protocols);
    }

    /**
     * Sanitize a date string.
     *
     * @since 1.0.0
     *
     * @param mixed  $value  The value to sanitize.
     * @param string $format The expected date format (default: Y-m-d).
     * @return string The sanitized date string or empty if invalid.
     */
    public function date(mixed $value, string $format = 'Y-m-d'): string
    {
        if (!is_string($value) || empty($value)) {
            return '';
        }

        $date = \DateTime::createFromFormat($format, $value);

        if ($date && $date->format($format) === $value) {
            return $value;
        }

        // Try to parse as any date format
        $timestamp = strtotime($value);
        if ($timestamp !== false) {
            return gmdate($format, $timestamp);
        }

        return '';
    }

    /**
     * Sanitize a phone number.
     *
     * @since 1.0.0
     *
     * @param mixed $value The value to sanitize.
     * @return string The sanitized phone number.
     */
    public function phone(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        // Remove all characters except digits, plus, spaces, hyphens, parentheses
        return preg_replace('/[^0-9+\s\-().]/', '', $value) ?? '';
    }

    /**
     * Sanitize a key (slug-like string).
     *
     * @since 1.0.0
     *
     * @param mixed $value The value to sanitize.
     * @return string The sanitized key.
     */
    public function key(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return sanitize_key($value);
    }

    /**
     * Sanitize a filename.
     *
     * @since 1.0.0
     *
     * @param mixed $value The value to sanitize.
     * @return string The sanitized filename.
     */
    public function filename(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return sanitize_file_name($value);
    }

    /**
     * Sanitize HTML content (allows safe HTML tags).
     *
     * @since 1.0.0
     *
     * @param mixed $value The value to sanitize.
     * @return string The sanitized HTML.
     */
    public function html(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return wp_kses_post($value);
    }

    /**
     * Sanitize a string to be a valid option from a list.
     *
     * @since 1.0.0
     *
     * @param mixed    $value   The value to sanitize.
     * @param string[] $options The valid options.
     * @param string   $default The default value if not in options.
     * @return string The sanitized option.
     */
    public function option(mixed $value, array $options, string $default = ''): string
    {
        $value = $this->text($value);

        if (in_array($value, $options, true)) {
            return $value;
        }

        return $default;
    }

    /**
     * Sanitize an array of values.
     *
     * @since 1.0.0
     *
     * @param mixed    $value  The value to sanitize.
     * @param callable $method The sanitization method to apply to each element.
     * @return array<int|string, mixed> The sanitized array.
     */
    public function array(mixed $value, callable $method): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_map($method, $value);
    }

    /**
     * Sanitize an array of IDs (positive integers).
     *
     * @since 1.0.0
     *
     * @param mixed $value The value to sanitize.
     * @return int[] The sanitized array of IDs.
     */
    public function ids(mixed $value): array
    {
        if (!is_array($value)) {
            if (is_numeric($value)) {
                return [absint($value)];
            }
            return [];
        }

        return array_map('absint', $value);
    }

    /**
     * Sanitize data from request based on field definitions.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed>                            $data   The raw data.
     * @param array<string, array{type: string, default?: mixed}> $fields Field definitions with types.
     * @return array<string, mixed> The sanitized data.
     */
    public function sanitizeFields(array $data, array $fields): array
    {
        $sanitized = [];

        foreach ($fields as $key => $config) {
            $type = $config['type'] ?? 'text';
            $default = $config['default'] ?? null;
            $value = $data[$key] ?? $default;

            $sanitized[$key] = match ($type) {
                'text'     => $this->text($value),
                'textarea' => $this->textarea($value),
                'email'    => $this->email($value),
                'int'      => $this->int($value),
                'absint'   => $this->absint($value),
                'float'    => $this->float($value),
                'money'    => $this->money($value),
                'bool'     => $this->bool($value),
                'url'      => $this->url($value),
                'date'     => $this->date($value),
                'phone'    => $this->phone($value),
                'key'      => $this->key($value),
                'html'     => $this->html($value),
                default    => $this->text($value),
            };
        }

        return $sanitized;
    }
}
