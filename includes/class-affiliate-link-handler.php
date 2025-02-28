<?php
/**
 * Affiliate Link Handler
 *
 * Handles generating and decoding affiliate links
 * 
 * @package Pulse
 */

namespace Pulse;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Affiliate_Link_Handler {
    /**
     * The encryption key used for encoding/decoding
     *
     * @var string
     */
    private $encryption_key;

    /**
     * Constructor
     */
    public function __construct() {
        $this->encryption_key = get_option('pulse_encryption_key');
        if (!$this->encryption_key) {
            $this->encryption_key = bin2hex(random_bytes(16));
            update_option('pulse_encryption_key', $this->encryption_key);
        }
    }

    /**
     * Generate an encrypted affiliate link for a lightning address
     * 
     * @param string $lightning_address The lightning address to encrypt
     * @return string|bool The fully-qualified affiliate URL or false on failure
     */
    public function generate_affiliate_link($lightning_address) {
        if (empty($lightning_address) || !filter_var($lightning_address, FILTER_VALIDATE_EMAIL)) {
            error_log('Pulse: Invalid lightning address format for encryption: ' . esc_html($lightning_address));
            return false;
        }
        
        $padded = str_pad($lightning_address, 64, "\0"); // Pad to fixed length
        
        // For backward compatibility, keep using the same algorithm
        // but add validation and error handling
        $encrypted = openssl_encrypt(
            $padded, 
            'aes-128-ecb', 
            hex2bin($this->encryption_key), 
            OPENSSL_RAW_DATA
        );
        
        if ($encrypted === false) {
            error_log('Pulse: Encryption failed: ' . openssl_error_string());
            return false;
        }
        
        $encoded = rtrim(strtr(base64_encode($encrypted), '+/', '-_'), '=');
        return home_url('?aff=' . $encoded);
    }

    /**
     * Decode an encrypted affiliate link to retrieve the lightning address
     * 
     * @param string $encoded The encoded part of the affiliate link
     * @return string|bool The decoded lightning address or false on failure
     */
    public function decode_affiliate_link($encoded) {
        if (empty($encoded)) {
            error_log('Pulse: Empty encoded affiliate string');
            return false;
        }
        
        try {
            // Add proper padding for base64
            $base64 = strtr($encoded, '-_', '+/');
            $mod4 = strlen($base64) % 4;
            if ($mod4) {
                $base64 .= substr('====', $mod4);
            }
            
            $decoded = base64_decode($base64, true);
            if ($decoded === false) {
                error_log('Pulse: Invalid base64 encoding in affiliate link');
                return false;
            }
            
            // Decrypt using the same algorithm as encryption
            $decrypted = openssl_decrypt(
                $decoded, 
                'aes-128-ecb', 
                hex2bin($this->encryption_key), 
                OPENSSL_RAW_DATA
            );
            
            if ($decrypted === false) {
                error_log('Pulse: Decryption failed: ' . openssl_error_string());
                return false;
            }
            
            $result = rtrim($decrypted, "\0");
            
            // Validate result is a valid lightning address
            if (!filter_var($result, FILTER_VALIDATE_EMAIL)) {
                error_log('Pulse: Decrypted value is not a valid lightning address: ' . esc_html($result));
                return false;
            }
            
            return $result;
        } catch (\Exception $e) {
            error_log('Pulse: Exception during decryption: ' . $e->getMessage());
            return false;
        }
    }
}