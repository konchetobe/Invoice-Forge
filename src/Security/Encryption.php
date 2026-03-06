<?php
/**
 * Encryption Handler
 *
 * Provides encryption and decryption for sensitive data like API keys.
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
 * Encryption class for sensitive data.
 *
 * Uses WordPress AUTH_KEY and SECURE_AUTH_KEY as encryption keys.
 * Implements AES-256-GCM encryption for maximum security.
 *
 * @since 1.0.0
 */
class Encryption
{
    /**
     * The encryption cipher.
     *
     * @since 1.0.0
     * @var string
     */
    private const CIPHER = 'aes-256-gcm';

    /**
     * The tag length for GCM mode.
     *
     * @since 1.0.0
     * @var int
     */
    private const TAG_LENGTH = 16;

    /**
     * Get the encryption key derived from WordPress salts.
     *
     * @since 1.0.0
     *
     * @return string The encryption key.
     */
    private function getKey(): string
    {
        // Use WordPress salts as the base for the encryption key
        $key_material = '';

        if (defined('AUTH_KEY')) {
            $key_material .= AUTH_KEY;
        }

        if (defined('SECURE_AUTH_KEY')) {
            $key_material .= SECURE_AUTH_KEY;
        }

        // Fallback if salts are not defined (should never happen in production)
        if (empty($key_material)) {
            $key_material = 'invoiceforge-default-key-' . ABSPATH;
        }

        // Derive a 256-bit key using hash
        return hash('sha256', $key_material, true);
    }

    /**
     * Encrypt a string value.
     *
     * @since 1.0.0
     *
     * @param string $plaintext The value to encrypt.
     * @return string The encrypted value (base64 encoded).
     *
     * @throws \RuntimeException If encryption fails.
     */
    public function encrypt(string $plaintext): string
    {
        if (empty($plaintext)) {
            return '';
        }

        $key = $this->getKey();
        $iv_length = openssl_cipher_iv_length(self::CIPHER);

        if ($iv_length === false) {
            throw new \RuntimeException('Failed to get IV length for cipher.');
        }

        $iv = openssl_random_pseudo_bytes($iv_length);

        if ($iv === false) {
            throw new \RuntimeException('Failed to generate IV.');
        }

        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed.');
        }

        // Combine IV + tag + ciphertext and base64 encode
        $combined = $iv . $tag . $ciphertext;

        return base64_encode($combined);
    }

    /**
     * Decrypt an encrypted string value.
     *
     * @since 1.0.0
     *
     * @param string $encrypted The encrypted value (base64 encoded).
     * @return string The decrypted value.
     *
     * @throws \RuntimeException If decryption fails.
     */
    public function decrypt(string $encrypted): string
    {
        if (empty($encrypted)) {
            return '';
        }

        $combined = base64_decode($encrypted, true);

        if ($combined === false) {
            throw new \RuntimeException('Invalid encrypted data (base64 decode failed).');
        }

        $key = $this->getKey();
        $iv_length = openssl_cipher_iv_length(self::CIPHER);

        if ($iv_length === false) {
            throw new \RuntimeException('Failed to get IV length for cipher.');
        }

        // Extract IV, tag, and ciphertext
        $iv = substr($combined, 0, $iv_length);
        $tag = substr($combined, $iv_length, self::TAG_LENGTH);
        $ciphertext = substr($combined, $iv_length + self::TAG_LENGTH);

        if (strlen($iv) !== $iv_length || strlen($tag) !== self::TAG_LENGTH) {
            throw new \RuntimeException('Invalid encrypted data format.');
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            throw new \RuntimeException('Decryption failed.');
        }

        return $plaintext;
    }

    /**
     * Safely encrypt a value, returning empty string on failure.
     *
     * @since 1.0.0
     *
     * @param string $plaintext The value to encrypt.
     * @return string The encrypted value or empty string on failure.
     */
    public function safeEncrypt(string $plaintext): string
    {
        try {
            return $this->encrypt($plaintext);
        } catch (\RuntimeException $e) {
            // Log error but don't expose it
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('InvoiceForge encryption error: ' . $e->getMessage());
            }
            return '';
        }
    }

    /**
     * Safely decrypt a value, returning empty string on failure.
     *
     * @since 1.0.0
     *
     * @param string $encrypted The encrypted value.
     * @return string The decrypted value or empty string on failure.
     */
    public function safeDecrypt(string $encrypted): string
    {
        try {
            return $this->decrypt($encrypted);
        } catch (\RuntimeException $e) {
            // Log error but don't expose it
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('InvoiceForge decryption error: ' . $e->getMessage());
            }
            return '';
        }
    }

    /**
     * Check if a string is encrypted (base64 encoded).
     *
     * @since 1.0.0
     *
     * @param string $value The value to check.
     * @return bool True if the value appears to be encrypted.
     */
    public function isEncrypted(string $value): bool
    {
        if (empty($value)) {
            return false;
        }

        // Check if it's valid base64
        $decoded = base64_decode($value, true);

        if ($decoded === false) {
            return false;
        }

        // Check minimum length (IV + tag + at least 1 byte of data)
        $iv_length = openssl_cipher_iv_length(self::CIPHER) ?: 12;
        $min_length = $iv_length + self::TAG_LENGTH + 1;

        return strlen($decoded) >= $min_length;
    }

    /**
     * Hash a value (one-way, for comparison).
     *
     * @since 1.0.0
     *
     * @param string $value The value to hash.
     * @return string The hashed value.
     */
    public function hash(string $value): string
    {
        $key = $this->getKey();
        return hash_hmac('sha256', $value, $key);
    }

    /**
     * Verify a value against a hash.
     *
     * @since 1.0.0
     *
     * @param string $value The value to verify.
     * @param string $hash  The hash to compare against.
     * @return bool True if the value matches the hash.
     */
    public function verifyHash(string $value, string $hash): bool
    {
        return hash_equals($hash, $this->hash($value));
    }
}
