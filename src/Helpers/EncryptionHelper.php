<?php

namespace Phpfy\LaravelBackup\Helpers;

use RuntimeException;

/**
 * Encryption helper class for secure backup encryption/decryption.
 * 
 * Provides AES-256-CBC encryption methods for securing backup files.
 * Uses OpenSSL for cryptographic operations.
 * 
 * @package Phpfy\LaravelBackup\Helpers
 */
class EncryptionHelper
{
    /**
     * Encryption cipher method.
     */
    private const CIPHER = 'aes-256-cbc';

    /**
     * Hash algorithm for key derivation.
     */
    private const HASH_ALGO = 'sha256';

    /**
     * Encrypt data using AES-256-CBC.
     *
     * @param string $data Data to encrypt
     * @param string $password Password for encryption
     * @return string Encrypted data (base64 encoded)
     * @throws RuntimeException If encryption fails
     */
    public static function encrypt(string $data, string $password): string
    {
        if (empty($password)) {
            throw new RuntimeException('Encryption password cannot be empty');
        }

        // Generate a cryptographically secure IV
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        if ($ivLength === false) {
            throw new RuntimeException('Failed to get IV length for cipher');
        }

        $iv = openssl_random_pseudo_bytes($ivLength, $strong);
        if (!$strong) {
            throw new RuntimeException('Failed to generate secure IV');
        }

        // Derive encryption key from password
        $key = self::deriveKey($password);

        // Encrypt the data
        $encrypted = openssl_encrypt(
            $data,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            throw new RuntimeException('Encryption failed: ' . openssl_error_string());
        }

        // Generate HMAC for authentication
        $hmac = hash_hmac(self::HASH_ALGO, $iv . $encrypted, $key, true);

        // Combine IV, encrypted data, and HMAC
        $combined = $iv . $encrypted . $hmac;

        // Return base64 encoded result
        return base64_encode($combined);
    }

    /**
     * Decrypt data that was encrypted with encrypt().
     *
     * @param string $encryptedData Encrypted data (base64 encoded)
     * @param string $password Password for decryption
     * @return string Decrypted data
     * @throws RuntimeException If decryption fails
     */
    public static function decrypt(string $encryptedData, string $password): string
    {
        if (empty($password)) {
            throw new RuntimeException('Decryption password cannot be empty');
        }

        // Decode base64
        $combined = base64_decode($encryptedData, true);
        if ($combined === false) {
            throw new RuntimeException('Invalid encrypted data format');
        }

        // Derive decryption key from password
        $key = self::deriveKey($password);

        // Extract components
        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $hmacLength = 32; // SHA256 produces 32 bytes

        if (strlen($combined) < $ivLength + $hmacLength) {
            throw new RuntimeException('Encrypted data is too short');
        }

        $iv = substr($combined, 0, $ivLength);
        $hmac = substr($combined, -$hmacLength);
        $encrypted = substr($combined, $ivLength, -$hmacLength);

        // Verify HMAC
        $calculatedHmac = hash_hmac(self::HASH_ALGO, $iv . $encrypted, $key, true);

        if (!hash_equals($hmac, $calculatedHmac)) {
            throw new RuntimeException('HMAC verification failed - data may be corrupted or tampered');
        }

        // Decrypt the data
        $decrypted = openssl_decrypt(
            $encrypted,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decrypted === false) {
            throw new RuntimeException('Decryption failed: ' . openssl_error_string());
        }

        return $decrypted;
    }

    /**
     * Encrypt a file and save to destination.
     *
     * @param string $sourceFile Path to source file
     * @param string $destinationFile Path to encrypted output file
     * @param string $password Password for encryption
     * @return bool True on success
     * @throws RuntimeException If encryption fails
     */
    public static function encryptFile(string $sourceFile, string $destinationFile, string $password): bool
    {
        if (!file_exists($sourceFile)) {
            throw new RuntimeException("Source file does not exist: {$sourceFile}");
        }

        if (!is_readable($sourceFile)) {
            throw new RuntimeException("Source file is not readable: {$sourceFile}");
        }

        // Read source file
        $data = file_get_contents($sourceFile);
        if ($data === false) {
            throw new RuntimeException("Failed to read source file: {$sourceFile}");
        }

        // Encrypt data
        $encrypted = self::encrypt($data, $password);

        // Write encrypted data
        $written = file_put_contents($destinationFile, $encrypted);
        if ($written === false) {
            throw new RuntimeException("Failed to write encrypted file: {$destinationFile}");
        }

        return true;
    }

    /**
     * Decrypt a file and save to destination.
     *
     * @param string $encryptedFile Path to encrypted file
     * @param string $destinationFile Path to decrypted output file
     * @param string $password Password for decryption
     * @return bool True on success
     * @throws RuntimeException If decryption fails
     */
    public static function decryptFile(string $encryptedFile, string $destinationFile, string $password): bool
    {
        if (!file_exists($encryptedFile)) {
            throw new RuntimeException("Encrypted file does not exist: {$encryptedFile}");
        }

        if (!is_readable($encryptedFile)) {
            throw new RuntimeException("Encrypted file is not readable: {$encryptedFile}");
        }

        // Read encrypted file
        $encrypted = file_get_contents($encryptedFile);
        if ($encrypted === false) {
            throw new RuntimeException("Failed to read encrypted file: {$encryptedFile}");
        }

        // Decrypt data
        $decrypted = self::decrypt($encrypted, $password);

        // Write decrypted data
        $written = file_put_contents($destinationFile, $decrypted);
        if ($written === false) {
            throw new RuntimeException("Failed to write decrypted file: {$destinationFile}");
        }

        return true;
    }

    /**
     * Derive encryption key from password using PBKDF2.
     *
     * @param string $password The password
     * @param string $salt Salt for key derivation (optional)
     * @param int $iterations Number of iterations
     * @return string Derived key
     */
    protected static function deriveKey(string $password, string $salt = 'phpfy-backup-salt', int $iterations = 10000): string
    {
        return hash_pbkdf2(
            self::HASH_ALGO,
            $password,
            $salt,
            $iterations,
            32, // 256 bits = 32 bytes
            true
        );
    }

    /**
     * Generate a random password.
     *
     * @param int $length Password length
     * @return string Random password
     */
    public static function generatePassword(int $length = 32): string
    {
        $bytes = openssl_random_pseudo_bytes($length, $strong);

        if (!$strong) {
            throw new RuntimeException('Failed to generate secure random password');
        }

        return base64_encode($bytes);
    }

    /**
     * Check if OpenSSL extension is available.
     *
     * @return bool True if OpenSSL is available
     */
    public static function isAvailable(): bool
    {
        return extension_loaded('openssl');
    }

    /**
     * Get list of available ciphers.
     *
     * @return array List of cipher names
     */
    public static function getAvailableCiphers(): array
    {
        return openssl_get_cipher_methods();
    }

    /**
     * Verify password strength (basic check).
     *
     * @param string $password Password to check
     * @return bool True if password meets minimum requirements
     */
    public static function isPasswordStrong(string $password): bool
    {
        // Minimum 8 characters
        if (strlen($password) < 8) {
            return false;
        }

        // Check for variety (at least 3 of: lowercase, uppercase, digits, special)
        $checks = 0;
        if (preg_match('/[a-z]/', $password)) $checks++;
        if (preg_match('/[A-Z]/', $password)) $checks++;
        if (preg_match('/[0-9]/', $password)) $checks++;
        if (preg_match('/[^a-zA-Z0-9]/', $password)) $checks++;

        return $checks >= 3;
    }

    /**
     * Hash a password for storage (not for encryption).
     *
     * @param string $password Password to hash
     * @return string Hashed password
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    /**
     * Verify a password against a hash.
     *
     * @param string $password Password to verify
     * @param string $hash Hash to verify against
     * @return bool True if password matches
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}
