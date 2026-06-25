<?php

declare(strict_types=1);

namespace Piwik\Plugins\VisitorFlowIntelligence\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\VisitorFlowIntelligence\Service\CacheKeyEncryption;
use Piwik\Plugins\VisitorFlowIntelligence\Exception\SecurityException;

/**
 * SB-018.4: CacheKeyEncryptionTest
 * 
 * Tests for cache key encryption and validation
 */
class CacheKeyEncryptionTest extends TestCase
{
    protected function setUp(): void
    {
        // Set a test encryption key
        CacheKeyEncryption::setEncryptionKey(str_repeat('test_key', 4)); // 32 chars
    }

    /**
     * Test encrypt/decrypt round trip
     */
    public function testEncryptDecryptRoundTrip(): void
    {
        $plaintext = 'cache_key_test_value';

        $ciphertext = CacheKeyEncryption::encrypt($plaintext);
        $decrypted = CacheKeyEncryption::decrypt($ciphertext);

        $this->assertEquals($plaintext, $decrypted);
    }

    /**
     * Test different plaintexts produce different ciphertexts
     */
    public function testDifferentPlaintextsDifferentCiphertexts(): void
    {
        $ciphertext1 = CacheKeyEncryption::encrypt('value1');
        $ciphertext2 = CacheKeyEncryption::encrypt('value2');

        $this->assertNotEquals($ciphertext1, $ciphertext2);
    }

    /**
     * Test same plaintext produces different ciphertexts (due to random IV)
     */
    public function testSamePlaintextDifferentCiphertexts(): void
    {
        $plaintext = 'same_value';
        $ciphertext1 = CacheKeyEncryption::encrypt($plaintext);
        $ciphertext2 = CacheKeyEncryption::encrypt($plaintext);

        // Different ciphertexts due to random IV
        $this->assertNotEquals($ciphertext1, $ciphertext2);

        // But decrypt to same plaintext
        $this->assertEquals($plaintext, CacheKeyEncryption::decrypt($ciphertext1));
        $this->assertEquals($plaintext, CacheKeyEncryption::decrypt($ciphertext2));
    }

    /**
     * Test tampering detection
     */
    public function testTamperingDetection(): void
    {
        $plaintext = 'original_value';
        $ciphertext = CacheKeyEncryption::encrypt($plaintext);

        // Tamper with ciphertext (modify a character)
        [$hash, $combined] = explode('::', $ciphertext);
        $tampered = substr($combined, 0, -1) . 'X'; // Change last character
        $tamperedCiphertext = substr($hash, 0, -1) . 'X' . '::' . $tampered;

        $this->expectException(SecurityException::class);
        CacheKeyEncryption::decrypt($tamperedCiphertext);
    }

    /**
     * Test invalid format detection
     */
    public function testInvalidFormatDetection(): void
    {
        $this->expectException(SecurityException::class);
        CacheKeyEncryption::decrypt('invalid_format');
    }

    /**
     * Test generate secure key
     */
    public function testGenerateSecureKey(): void
    {
        $key = CacheKeyEncryption::generateSecureKey(
            siteId: 1,
            period: 'day',
            date: '2026-06-25',
            segment: 'deviceType==mobile',
            method: 'getTopPaths'
        );

        // Key should be non-empty and decryptable
        $this->assertNotEmpty($key);
        $decrypted = CacheKeyEncryption::decrypt($key);
        $this->assertStringContainsString('site', $decrypted);
    }

    /**
     * Test is valid function
     */
    public function testIsValidFunction(): void
    {
        $valid = CacheKeyEncryption::encrypt('test');
        $this->assertTrue(CacheKeyEncryption::isValid($valid));

        $this->assertFalse(CacheKeyEncryption::isValid('invalid'));
    }

    /**
     * Test encryption key too short
     */
    public function testEncryptionKeyTooShort(): void
    {
        $this->expectException(SecurityException::class);
        CacheKeyEncryption::setEncryptionKey('short');
    }

    /**
     * Test empty plaintext
     */
    public function testEmptyPlaintext(): void
    {
        $ciphertext = CacheKeyEncryption::encrypt('');
        $decrypted = CacheKeyEncryption::decrypt($ciphertext);

        $this->assertEquals('', $decrypted);
    }

    /**
     * Test long plaintext
     */
    public function testLongPlaintext(): void
    {
        $longText = str_repeat('x', 10000);
        $ciphertext = CacheKeyEncryption::encrypt($longText);
        $decrypted = CacheKeyEncryption::decrypt($ciphertext);

        $this->assertEquals($longText, $decrypted);
    }

    /**
     * Test special characters in plaintext
     */
    public function testSpecialCharactersInPlaintext(): void
    {
        $special = 'test!@#$%^&*()_+-=[]{}|;:,.<>?';
        $ciphertext = CacheKeyEncryption::encrypt($special);
        $decrypted = CacheKeyEncryption::decrypt($ciphertext);

        $this->assertEquals($special, $decrypted);
    }

    /**
     * Test unicode characters in plaintext
     */
    public function testUnicodeCharactersInPlaintext(): void
    {
        $unicode = 'test_ñ_é_ü_日本語_🔒';
        $ciphertext = CacheKeyEncryption::encrypt($unicode);
        $decrypted = CacheKeyEncryption::decrypt($ciphertext);

        $this->assertEquals($unicode, $decrypted);
    }

    /**
     * Test timing-safe comparison
     */
    public function testTimingSafeComparison(): void
    {
        $plaintext = 'security_test';
        $ciphertext = CacheKeyEncryption::encrypt($plaintext);

        // Even with similar hash prefix, should detect tampering
        [$hash, $combined] = explode('::', $ciphertext);
        $alteredHash = substr($hash, 0, -5) . 'aaaaa';
        $tamperedCiphertext = $alteredHash . '::' . $combined;

        $this->expectException(SecurityException::class);
        CacheKeyEncryption::decrypt($tamperedCiphertext);
    }

    /**
     * Test JSON key generation
     */
    public function testJSONKeyGeneration(): void
    {
        $key1 = CacheKeyEncryption::generateSecureKey(1, 'day', '2026-06-25');
        $key2 = CacheKeyEncryption::generateSecureKey(1, 'day', '2026-06-25');

        // Different ciphertexts
        $this->assertNotEquals($key1, $key2);

        // But same plaintext
        $plain1 = CacheKeyEncryption::decrypt($key1);
        $plain2 = CacheKeyEncryption::decrypt($key2);
        $this->assertEquals($plain1, $plain2);

        // Plaintext should be valid JSON
        $decoded = json_decode($plain1, true);
        $this->assertIsArray($decoded);
        $this->assertEquals(1, $decoded['site']);
        $this->assertEquals('day', $decoded['period']);
    }

    /**
     * Test without segment parameter
     */
    public function testWithoutSegmentParameter(): void
    {
        $key = CacheKeyEncryption::generateSecureKey(
            siteId: 2,
            period: 'week',
            date: '2026-06-20'
        );

        $decrypted = CacheKeyEncryption::decrypt($key);
        $decoded = json_decode($decrypted, true);

        $this->assertEquals('none', $decoded['segment']);
    }
}
