<?php
/**
 * Pulse Encryption Handler
 *
 * @package Pulse
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Pulse_Encryption_Handler {
    private $public_key;
    private $private_key;

    /**
     * Constructor
     */
    public function __construct() {
        $options = get_option('pulse_options');
        $this->public_key = isset($options['public_key']) ? $options['public_key'] : '';
        $this->private_key = isset($options['private_key']) ? $options['private_key'] : '';
    }

    /**
     * Encrypt data
     *
     * @param string $data The data to encrypt
     * @return string|bool The encrypted data or false on failure
     */
    public function encrypt($data) {
        if (empty($this->public_key)) {
            error_log('Pulse: Public key not set');
            return false;
        }

        $encrypted = '';
        $key = openssl_pkey_get_public($this->public_key);
        if ($key === false) {
            error_log('Pulse: Invalid public key');
            return false;
        }

        if (openssl_public_encrypt($data, $encrypted, $key)) {
            return base64_encode($encrypted);
        } else {
            error_log('Pulse: Encryption failed');
            return false;
        }
    }

    /**
     * Decrypt data
     *
     * @param string $data The data to decrypt
     * @return string|bool The decrypted data or false on failure
     */
    public function decrypt($data) {
        if (empty($this->private_key)) {
            error_log('Pulse: Private key not set');
            return false;
        }

        $decrypted = '';
        $key = openssl_pkey_get_private($this->private_key);
        if ($key === false) {
            error_log('Pulse: Invalid private key');
            return false;
        }

        if (openssl_private_decrypt(base64_decode($data), $decrypted, $key)) {
            return $decrypted;
        } else {
            error_log('Pulse: Decryption failed');
            return false;
        }
    }

    /**
     * Get the public key
     *
     * @return string The public key
     */
    public function get_public_key() {
        return $this->public_key;
    }

    /**
     * Generate new key pair
     *
     * @return array|bool Array containing 'public' and 'private' keys, or false on failure
     */
    public static function generate_key_pair() {
        $config = array(
            "digest_alg" => "sha256",
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        );

        // Create the private and public key
        $res = openssl_pkey_new($config);
        if ($res === false) {
            error_log('Pulse: Failed to generate new key pair');
            return false;
        }

        // Extract the private key
        openssl_pkey_export($res, $private_key);

        // Extract the public key
        $public_key = openssl_pkey_get_details($res);
        $public_key = $public_key["key"];

        return array(
            'public' => $public_key,
            'private' => $private_key
        );
    }
}
