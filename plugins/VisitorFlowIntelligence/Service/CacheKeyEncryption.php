<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Service;

use Piwik\Plugins\VisitorFlowIntelligence\Exception\SecurityException;

/**
 * SB-018.4: CacheKeyEncryption
 * 
 * Encrypts cache keys at rest using AES-256-CBC
 * Adds hash-based validation to detect tampering
 */
class CacheKeyEncryption
{
    private const ALGORITHM = 'AES-256-CBC';
    private const HASH_ALGORITHM = 'sha256';
    
    private static ?string $encryptionKey = null;

    /**
     * Initialize encryption key (should come from Matomo configuration)
     */
    public static function setEncryptionKey(string $key): void
    {
        if (strlen($key) < 32) {
            throw new SecurityException('Encryption key must be at least 32 characters');
        }

        self::$encryptionKey = $key;
    }

    /**
     * Get encryption key from Matomo config or generate fallback
     */
    private static function getEncryptionKey(): string
    {
        if (self::$encryptionKey === null) {
            // Fallback to Matomo security salt if available
            if (defined('Piwik\Config::getInstance')) {
                try {
                    $config = \Piwik\Config::getInstance();
                    $salt = $config->General['salt'] ?? null;
                    if ($salt && strlen($salt) >= 32) {
                        return $salt;
                    }
                } catch (\Exception $e) {
                    // Fallback to default
                }
            }

            // Fallback key (should be overridden in production)
            return hash(self::HASH_ALGORITHM, 'visitorflow_default_key', true);
        }

        return self::$encryptionKey;
    }

    /**
     * Encrypt a cache key
     */
    public static function encrypt(string $plaintext): string
    {
        $key = self::getEncryptionKey();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::ALGORITHM));

        if ($iv === false) {
            throw new SecurityException('Failed to generate IV for encryption');
        }

        $encrypted = openssl_encrypt($plaintext, self::ALGORITHM, $key, 0, $iv);

        if ($encrypted === false) {
            throw new SecurityException('Failed to encrypt cache key');
        }

        // Combine IV + encrypted data + hash
        $combined = base64_encode($iv . $encrypted);
        $hash = hash_hmac(self::HASH_ALGORITHM, $combined, $key);

        return $hash . '::' . $combined;
    }

    /**
     * Decrypt a cache key
     */
    public static function decrypt(string $ciphertext): string
    {
        // Validate format
        if (strpos($ciphertext, '::') === false) {
            throw new SecurityException('Invalid encrypted cache key format');
        }

        [$hash, $combined] = explode('::', $ciphertext, 2);

        // Verify hash (timing-safe comparison)
        $key = self::getEncryptionKey();
        $expectedHash = hash_hmac(self::HASH_ALGORITHM, $combined, $key);

        if (!hash_equals($hash, $expectedHash)) {
            throw new SecurityException('Cache key has been tampered with');
        }

        // Decode and extract IV + encrypted data
        $decoded = base64_decode($combined, true);

        if ($decoded === false) {
            throw new SecurityException('Failed to decode encrypted cache key');
        }

        $ivLength = openssl_cipher_iv_length(self::ALGORITHM);
        $iv = substr($decoded, 0, $ivLength);
        $encrypted = substr($decoded, $ivLength);

        $decrypted = openssl_decrypt($encrypted, self::ALGORITHM, $key, 0, $iv);

        if ($decrypted === false) {
            throw new SecurityException('Failed to decrypt cache key');
        }

        return $decrypted;
    }

    /**
     * Generate a secure cache key
     */
    public static function generateSecureKey(
        int $siteId,
        string $period,
        string $date,
        ?string $segment = null,
        string $method = 'default'
    ): string {
        // Build key components
        $components = [
            'site' => (string)$siteId,
            'period' => $period,
            'date' => $date,
            'segment' => $segment ?? 'none',
            'method' => $method,
        ];

        // Generate plain key
        $plainKey = json_encode($components, JSON_THROW_ON_ERROR);

        // Encrypt and hash
        return self::encrypt($plainKey);
    }

    /**
     * Verify cache key integrity
     */
    public static function isValid(string $ciphertext): bool
    {
        try {
            self::decrypt($ciphertext);
            return true;
        } catch (SecurityException $e) {
            return false;
        }
    }
}
