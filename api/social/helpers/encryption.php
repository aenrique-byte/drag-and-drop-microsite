<?php
/**
 * Social Media Token Encryption Helper
 * 
 * Provides secure encryption/decryption for social media API tokens
 * using AES-256-CBC encryption.
 *
 * @package AuthorCMS
 * @since Phase 4 - Social Media Integration
 */

// Encryption key should be defined in config.php
// If not defined, use JWT_SECRET as fallback (not ideal but functional)
if (!defined('ENCRYPTION_KEY')) {
    define('ENCRYPTION_KEY', defined('JWT_SECRET') ? JWT_SECRET : 'default-encryption-key-change-me');
}

/**
 * Encrypt a token for secure database storage
 *
 * @param string $token Plain text token
 * @return string Base64-encoded encrypted token (IV prepended)
 */
function encryptToken(string $token): string {
    $key = hash('sha256', ENCRYPTION_KEY, true);
    $iv = openssl_random_pseudo_bytes(16);
    
    $encrypted = openssl_encrypt(
        $token,
        'AES-256-CBC',
        $key,
        OPENSSL_RAW_DATA,
        $iv
    );
    
    if ($encrypted === false) {
        throw new RuntimeException('Encryption failed: ' . openssl_error_string());
    }
    
    // Prepend IV to encrypted data for storage
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt a token from database storage
 *
 * @param string $encryptedToken Base64-encoded encrypted token
 * @return string Plain text token
 */
function decryptToken(string $encryptedToken): string {
    $key = hash('sha256', ENCRYPTION_KEY, true);
    $data = base64_decode($encryptedToken);
    
    if ($data === false || strlen($data) < 17) {
        throw new RuntimeException('Invalid encrypted token format');
    }
    
    // Extract IV (first 16 bytes) and encrypted data
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    
    $decrypted = openssl_decrypt(
        $encrypted,
        'AES-256-CBC',
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
 * Validate that a token can be decrypted (for testing)
 *
 * @param string $encryptedToken Base64-encoded encrypted token
 * @return bool True if token can be decrypted
 */
function validateEncryptedToken(string $encryptedToken): bool {
    try {
        $decrypted = decryptToken($encryptedToken);
        return !empty($decrypted);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Mask a token for display (show first/last few characters)
 *
 * @param string $token Plain text token
 * @param int $showChars Number of characters to show at start/end
 * @return string Masked token (e.g., "abc...xyz")
 */
function maskToken(string $token, int $showChars = 4): string {
    $length = strlen($token);
    
    if ($length <= $showChars * 2) {
        return str_repeat('*', $length);
    }
    
    $start = substr($token, 0, $showChars);
    $end = substr($token, -$showChars);
    
    return $start . str_repeat('*', $length - ($showChars * 2)) . $end;
}
