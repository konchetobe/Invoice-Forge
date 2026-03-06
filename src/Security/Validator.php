<?php
/**
 * Input Validator
 *
 * Provides validation methods for various data types.
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
 * Input validation class.
 *
 * Provides methods for validating user input. Validation should be done
 * after sanitization to ensure data meets business requirements.
 *
 * @since 1.0.0
 */
class Validator
{
    /**
     * Validation errors.
     *
     * @since 1.0.0
     * @var array<string, string[]>
     */
    private array $errors = [];

    /**
     * Clear all validation errors.
     *
     * @since 1.0.0
     *
     * @return self Returns self for method chaining.
     */
    public function clear(): self
    {
        $this->errors = [];
        return $this;
    }

    /**
     * Check if validation passed (no errors).
     *
     * @since 1.0.0
     *
     * @return bool True if no errors.
     */
    public function isValid(): bool
    {
        return empty($this->errors);
    }

    /**
     * Get all validation errors.
     *
     * @since 1.0.0
     *
     * @return array<string, string[]> Array of errors by field.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get errors for a specific field.
     *
     * @since 1.0.0
     *
     * @param string $field The field name.
     * @return string[] Array of error messages for the field.
     */
    public function getFieldErrors(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Get all errors as a flat array of messages.
     *
     * @since 1.0.0
     *
     * @return string[] Array of error messages.
     */
    public function getAllMessages(): array
    {
        $messages = [];
        foreach ($this->errors as $fieldErrors) {
            $messages = array_merge($messages, $fieldErrors);
        }
        return $messages;
    }

    /**
     * Add an error for a field.
     *
     * @since 1.0.0
     *
     * @param string $field   The field name.
     * @param string $message The error message.
     * @return self Returns self for method chaining.
     */
    public function addError(string $field, string $message): self
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
        return $this;
    }

    /**
     * Validate that a field is required (not empty).
     *
     * @since 1.0.0
     *
     * @param mixed       $value   The value to validate.
     * @param string      $field   The field name.
     * @param string|null $message Custom error message.
     * @return bool True if valid.
     */
    public function required(mixed $value, string $field, ?string $message = null): bool
    {
        $valid = !empty($value) || $value === 0 || $value === '0';

        if (!$valid) {
            $this->addError(
                $field,
                $message ?? sprintf(
                    /* translators: %s: Field name */
                    __('%s is required.', 'invoiceforge'),
                    $field
                )
            );
        }

        return $valid;
    }

    /**
     * Validate an email address.
     *
     * @since 1.0.0
     *
     * @param string      $value   The value to validate.
     * @param string      $field   The field name.
     * @param string|null $message Custom error message.
     * @return bool True if valid.
     */
    public function email(string $value, string $field, ?string $message = null): bool
    {
        if (empty($value)) {
            return true; // Empty is valid (use required() for required fields)
        }

        $valid = is_email($value) !== false;

        if (!$valid) {
            $this->addError(
                $field,
                $message ?? __('Please enter a valid email address.', 'invoiceforge')
            );
        }

        return $valid;
    }

    /**
     * Validate a URL.
     *
     * @since 1.0.0
     *
     * @param string      $value   The value to validate.
     * @param string      $field   The field name.
     * @param string|null $message Custom error message.
     * @return bool True if valid.
     */
    public function url(string $value, string $field, ?string $message = null): bool
    {
        if (empty($value)) {
            return true;
        }

        $valid = filter_var($value, FILTER_VALIDATE_URL) !== false;

        if (!$valid) {
            $this->addError(
                $field,
                $message ?? __('Please enter a valid URL.', 'invoiceforge')
            );
        }

        return $valid;
    }

    /**
     * Validate minimum length.
     *
     * @since 1.0.0
     *
     * @param string      $value   The value to validate.
     * @param string      $field   The field name.
     * @param int         $min     The minimum length.
     * @param string|null $message Custom error message.
     * @return bool True if valid.
     */
    public function minLength(string $value, string $field, int $min, ?string $message = null): bool
    {
        if (empty($value)) {
            return true;
        }

        $valid = mb_strlen($value) >= $min;

        if (!$valid) {
            $this->addError(
                $field,
                $message ?? sprintf(
                    /* translators: 1: Field name, 2: Minimum length */
                    __('%1$s must be at least %2$d characters.', 'invoiceforge'),
                    $field,
                    $min
                )
            );
        }

        return $valid;
    }

    /**
     * Validate maximum length.
     *
     * @since 1.0.0
     *
     * @param string      $value   The value to validate.
     * @param string      $field   The field name.
     * @param int         $max     The maximum length.
     * @param string|null $message Custom error message.
     * @return bool True if valid.
     */
    public function maxLength(string $value, string $field, int $max, ?string $message = null): bool
    {
        $valid = mb_strlen($value) <= $max;

        if (!$valid) {
            $this->addError(
                $field,
                $message ?? sprintf(
                    /* translators: 1: Field name, 2: Maximum length */
                    __('%1$s must not exceed %2$d characters.', 'invoiceforge'),
                    $field,
                    $max
                )
            );
        }

        return $valid;
    }

    /**
     * Validate minimum value (for numbers).
     *
     * @since 1.0.0
     *
     * @param int|float   $value   The value to validate.
     * @param string      $field   The field name.
     * @param int|float   $min     The minimum value.
     * @param string|null $message Custom error message.
     * @return bool True if valid.
     */
    public function min(int|float $value, string $field, int|float $min, ?string $message = null): bool
    {
        $valid = $value >= $min;

        if (!$valid) {
            $this->addError(
                $field,
                $message ?? sprintf(
                    /* translators: 1: Field name, 2: Minimum value */
                    __('%1$s must be at least %2$s.', 'invoiceforge'),
                    $field,
                    (string) $min
                )
            );
        }

        return $valid;
    }

    /**
     * Validate maximum value (for numbers).
     *
     * @since 1.0.0
     *
     * @param int|float   $value   The value to validate.
     * @param string      $field   The field name.
     * @param int|float   $max     The maximum value.
     * @param string|null $message Custom error message.
     * @return bool True if valid.
     */
    public function max(int|float $value, string $field, int|float $max, ?string $message = null): bool
    {
        $valid = $value <= $max;

        if (!$valid) {
            $this->addError(
                $field,
                $message ?? sprintf(
                    /* translators: 1: Field name, 2: Maximum value */
                    __('%1$s must not exceed %2$s.', 'invoiceforge'),
                    $field,
                    (string) $max
                )
            );
        }

        return $valid;
    }

    /**
     * Validate that a value is in a list of options.
     *
     * @since 1.0.0
     *
     * @param mixed       $value   The value to validate.
     * @param string      $field   The field name.
     * @param array<mixed> $options The valid options.
     * @param string|null $message Custom error message.
     * @return bool True if valid.
     */
    public function inArray(mixed $value, string $field, array $options, ?string $message = null): bool
    {
        $valid = in_array($value, $options, true);

        if (!$valid) {
            $this->addError(
                $field,
                $message ?? __('Please select a valid option.', 'invoiceforge')
            );
        }

        return $valid;
    }

    /**
     * Validate a date string.
     *
     * @since 1.0.0
     *
     * @param string      $value   The value to validate.
     * @param string      $field   The field name.
     * @param string      $format  The expected date format.
     * @param string|null $message Custom error message.
     * @return bool True if valid.
     */
    public function date(string $value, string $field, string $format = 'Y-m-d', ?string $message = null): bool
    {
        if (empty($value)) {
            return true;
        }

        $date = \DateTime::createFromFormat($format, $value);
        $valid = $date && $date->format($format) === $value;

        if (!$valid) {
            $this->addError(
                $field,
                $message ?? __('Please enter a valid date.', 'invoiceforge')
            );
        }

        return $valid;
    }

    /**
     * Validate a date is after another date.
     *
     * @since 1.0.0
     *
     * @param string      $value      The date to validate.
     * @param string      $afterDate  The date it must be after.
     * @param string      $field      The field name.
     * @param string|null $message    Custom error message.
     * @return bool True if valid.
     */
    public function dateAfter(string $value, string $afterDate, string $field, ?string $message = null): bool
    {
        if (empty($value) || empty($afterDate)) {
            return true;
        }

        $valid = strtotime($value) > strtotime($afterDate);

        if (!$valid) {
            $this->addError(
                $field,
                $message ?? sprintf(
                    /* translators: 1: Field name, 2: Date */
                    __('%1$s must be after %2$s.', 'invoiceforge'),
                    $field,
                    $afterDate
                )
            );
        }

        return $valid;
    }

    /**
     * Validate a date is not in the past.
     *
     * @since 1.0.0
     *
     * @param string      $value   The date to validate.
     * @param string      $field   The field name.
     * @param string|null $message Custom error message.
     * @return bool True if valid.
     */
    public function dateNotPast(string $value, string $field, ?string $message = null): bool
    {
        if (empty($value)) {
            return true;
        }

        $valid = strtotime($value) >= strtotime('today');

        if (!$valid) {
            $this->addError(
                $field,
                $message ?? __('Date cannot be in the past.', 'invoiceforge')
            );
        }

        return $valid;
    }

    /**
     * Validate that a value matches a regex pattern.
     *
     * @since 1.0.0
     *
     * @param string      $value   The value to validate.
     * @param string      $field   The field name.
     * @param string      $pattern The regex pattern.
     * @param string|null $message Custom error message.
     * @return bool True if valid.
     */
    public function regex(string $value, string $field, string $pattern, ?string $message = null): bool
    {
        if (empty($value)) {
            return true;
        }

        $valid = preg_match($pattern, $value) === 1;

        if (!$valid) {
            $this->addError(
                $field,
                $message ?? __('Please enter a valid value.', 'invoiceforge')
            );
        }

        return $valid;
    }

    /**
     * Simple email validation check (does not add errors).
     *
     * @since 1.0.0
     *
     * @param string $value The email to validate.
     * @return bool True if valid email format.
     */
    public function isValidEmail(string $value): bool
    {
        if (empty($value)) {
            return false;
        }
        return is_email($value) !== false;
    }

    /**
     * Validate a positive number.
     *
     * @since 1.0.0
     *
     * @param int|float   $value   The value to validate.
     * @param string      $field   The field name.
     * @param string|null $message Custom error message.
     * @return bool True if valid.
     */
    public function positive(int|float $value, string $field, ?string $message = null): bool
    {
        $valid = $value > 0;

        if (!$valid) {
            $this->addError(
                $field,
                $message ?? sprintf(
                    /* translators: %s: Field name */
                    __('%s must be a positive number.', 'invoiceforge'),
                    $field
                )
            );
        }

        return $valid;
    }

    /**
     * Validate a non-negative number (zero or positive).
     *
     * @since 1.0.0
     *
     * @param int|float   $value   The value to validate.
     * @param string      $field   The field name.
     * @param string|null $message Custom error message.
     * @return bool True if valid.
     */
    public function nonNegative(int|float $value, string $field, ?string $message = null): bool
    {
        $valid = $value >= 0;

        if (!$valid) {
            $this->addError(
                $field,
                $message ?? sprintf(
                    /* translators: %s: Field name */
                    __('%s cannot be negative.', 'invoiceforge'),
                    $field
                )
            );
        }

        return $valid;
    }

    /**
     * Validate data against a set of rules.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed>                          $data  The data to validate.
     * @param array<string, array<string, mixed>> $rules The validation rules.
     * @return bool True if all validations pass.
     */
    public function validate(array $data, array $rules): bool
    {
        $this->clear();

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;

            foreach ($fieldRules as $rule => $params) {
                if ($rule === 'required') {
                    $this->required($value, $field);
                    continue;
                }

                // Skip other validations if value is empty and not required
                if (empty($value) && $value !== 0 && $value !== '0') {
                    continue;
                }

                match ($rule) {
                    'email'       => $this->email((string) $value, $field),
                    'url'         => $this->url((string) $value, $field),
                    'minLength'   => $this->minLength((string) $value, $field, (int) $params),
                    'maxLength'   => $this->maxLength((string) $value, $field, (int) $params),
                    'min'         => $this->min((float) $value, $field, (float) $params),
                    'max'         => $this->max((float) $value, $field, (float) $params),
                    'inArray'     => $this->inArray($value, $field, (array) $params),
                    'date'        => $this->date((string) $value, $field, is_string($params) ? $params : 'Y-m-d'),
                    'regex'       => $this->regex((string) $value, $field, (string) $params),
                    'positive'    => $this->positive((float) $value, $field),
                    'nonNegative' => $this->nonNegative((float) $value, $field),
                    default       => null,
                };
            }
        }

        return $this->isValid();
    }
}
