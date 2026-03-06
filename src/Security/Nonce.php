<?php
/**
 * Nonce Handler
 *
 * Provides wrapper methods for WordPress nonce generation and verification.
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
 * Nonce security handler.
 *
 * This class provides a centralized way to create and verify nonces
 * for CSRF protection throughout the plugin.
 *
 * @since 1.0.0
 */
class Nonce
{
    /**
     * The nonce prefix for all InvoiceForge nonces.
     *
     * @since 1.0.0
     * @var string
     */
    private const PREFIX = 'invoiceforge_';

    /**
     * Default nonce lifetime in seconds (24 hours).
     *
     * @since 1.0.0
     * @var int
     */
    private const DEFAULT_LIFETIME = DAY_IN_SECONDS;

    /**
     * Create a nonce for a specific action.
     *
     * @since 1.0.0
     *
     * @param string $action The action name (will be prefixed automatically).
     * @return string The generated nonce.
     */
    public function create(string $action): string
    {
        return wp_create_nonce($this->prefixAction($action));
    }

    /**
     * Verify a nonce for a specific action.
     *
     * @since 1.0.0
     *
     * @param string $nonce  The nonce to verify.
     * @param string $action The action name (will be prefixed automatically).
     * @return bool True if the nonce is valid, false otherwise.
     */
    public function verify(string $nonce, string $action): bool
    {
        $result = wp_verify_nonce($nonce, $this->prefixAction($action));
        return $result !== false && $result !== 0;
    }

    /**
     * Verify a nonce from the request ($_POST or $_GET).
     *
     * @since 1.0.0
     *
     * @param string $action     The action name.
     * @param string $nonce_name The name of the nonce field (default: '_nonce').
     * @param string $method     The request method ('POST' or 'GET').
     * @return bool True if the nonce is valid.
     */
    public function verifyRequest(
        string $action,
        string $nonce_name = '_nonce',
        string $method = 'POST'
    ): bool {
        $nonce = '';

        if ($method === 'POST' && isset($_POST[$nonce_name])) {
            $nonce = sanitize_text_field(wp_unslash($_POST[$nonce_name]));
        } elseif ($method === 'GET' && isset($_GET[$nonce_name])) {
            $nonce = sanitize_text_field(wp_unslash($_GET[$nonce_name]));
        }

        if (empty($nonce)) {
            return false;
        }

        return $this->verify($nonce, $action);
    }

    /**
     * Output a hidden nonce field for forms.
     *
     * @since 1.0.0
     *
     * @param string $action     The action name.
     * @param string $nonce_name The name of the nonce field (default: '_nonce').
     * @param bool   $referer    Whether to include the referer field (default: true).
     * @return void
     */
    public function field(string $action, string $nonce_name = '_nonce', bool $referer = true): void
    {
        wp_nonce_field($this->prefixAction($action), $nonce_name, $referer);
    }

    /**
     * Get a nonce URL.
     *
     * @since 1.0.0
     *
     * @param string $url        The URL to add the nonce to.
     * @param string $action     The action name.
     * @param string $nonce_name The name of the nonce query parameter (default: '_nonce').
     * @return string The URL with nonce added.
     */
    public function url(string $url, string $action, string $nonce_name = '_nonce'): string
    {
        return wp_nonce_url($url, $this->prefixAction($action), $nonce_name);
    }

    /**
     * Verify a nonce and die if invalid.
     *
     * @since 1.0.0
     *
     * @param string $nonce  The nonce to verify.
     * @param string $action The action name.
     * @return void
     *
     * @throws \WPDieException If nonce is invalid (via wp_die).
     */
    public function verifyOrDie(string $nonce, string $action): void
    {
        if (!$this->verify($nonce, $action)) {
            wp_die(
                esc_html__('Security check failed. Please try again.', 'invoiceforge'),
                esc_html__('Security Error', 'invoiceforge'),
                ['response' => 403, 'back_link' => true]
            );
        }
    }

    /**
     * Verify a nonce from request and die if invalid.
     *
     * @since 1.0.0
     *
     * @param string $action     The action name.
     * @param string $nonce_name The name of the nonce field.
     * @param string $method     The request method.
     * @return void
     */
    public function verifyRequestOrDie(
        string $action,
        string $nonce_name = '_nonce',
        string $method = 'POST'
    ): void {
        if (!$this->verifyRequest($action, $nonce_name, $method)) {
            wp_die(
                esc_html__('Security check failed. Please try again.', 'invoiceforge'),
                esc_html__('Security Error', 'invoiceforge'),
                ['response' => 403, 'back_link' => true]
            );
        }
    }

    /**
     * Check AJAX referer.
     *
     * @since 1.0.0
     *
     * @param string $action     The action name.
     * @param string $nonce_name The name of the nonce parameter.
     * @param bool   $die        Whether to die on failure (default: true).
     * @return bool True if valid, false if invalid (and $die is false).
     */
    public function checkAjaxReferer(string $action, string $nonce_name = 'nonce', bool $die = true): bool
    {
        $result = check_ajax_referer($this->prefixAction($action), $nonce_name, $die);
        return $result !== false;
    }

    /**
     * Get the prefixed action name.
     *
     * @since 1.0.0
     *
     * @param string $action The action name.
     * @return string The prefixed action name.
     */
    private function prefixAction(string $action): string
    {
        // Don't double-prefix
        if (str_starts_with($action, self::PREFIX)) {
            return $action;
        }

        return self::PREFIX . $action;
    }

    /**
     * Get the nonce prefix.
     *
     * @since 1.0.0
     *
     * @return string The nonce prefix.
     */
    public function getPrefix(): string
    {
        return self::PREFIX;
    }
}
