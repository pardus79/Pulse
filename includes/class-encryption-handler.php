<?php
namespace Pulse;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Encryption_Handler {
    private $public_key;
    private $private_key;

    public function __construct() {
        error_log('Pulse: Encryption_Handler constructor called');
        $options = get_option('pulse_options');
        $this->public_key = isset($options['public_key']) ? $options['public_key'] : '';
        $this->private_key = isset($options['private_key']) ? $options['private_key'] : '';
    }

    public function are_keys_set() {
        error_log('Pulse: are_keys_set method called');
        return !empty($this->public_key) && !empty($this->private_key);
    }
	
    /**
     * Encrypt data
     *
     * @param string $data The data to encrypt
     * @return string|bool The encrypted data or false on failure
     */
    public function encrypt($data) {
        if (empty($this->public_key)) {
            $this->log_error('Public key not set');
            return false;
        }

        $encrypted = '';
        $key = openssl_pkey_get_public($this->public_key);
        if ($key === false) {
            $this->log_error('Invalid public key');
            return false;
        }

        if (openssl_public_encrypt($data, $encrypted, $key)) {
            $this->log_info('Data encrypted successfully');
            return base64_encode($encrypted);
        } else {
            $this->log_error('Encryption failed: ' . openssl_error_string());
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
            $this->log_error('Private key not set');
            return false;
        }

        $decrypted = '';
        $key = openssl_pkey_get_private($this->private_key);
        if ($key === false) {
            $this->log_error('Invalid private key');
            return false;
        }

        $decoded_data = base64_decode($data);
        if ($decoded_data === false) {
            $this->log_error('Invalid base64 encoded data');
            return false;
        }

        if (openssl_private_decrypt($decoded_data, $decrypted, $key)) {
            $this->log_info('Data decrypted successfully');
            return $decrypted;
        } else {
            $this->log_error('Decryption failed: ' . openssl_error_string());
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
            self::log_error('Failed to generate new key pair: ' . openssl_error_string());
            return false;
        }

        // Extract the private key
        if (!openssl_pkey_export($res, $private_key)) {
            self::log_error('Failed to export private key: ' . openssl_error_string());
            return false;
        }

        // Extract the public key
        $public_key = openssl_pkey_get_details($res);
        if ($public_key === false) {
            self::log_error('Failed to get public key details: ' . openssl_error_string());
            return false;
        }

        self::log_info('New key pair generated successfully');
        return array(
            'public' => $public_key["key"],
            'private' => $private_key
        );
    }

    /**
     * Log error messages
     *
     * @param string $message The error message to log
     */
    private static function log_error($message) {
        error_log('Pulse Encryption Handler Error: ' . $message);
    }

    /**
     * Log info messages
     *
     * @param string $message The info message to log
     */
    private static function log_info($message) {
        error_log('Pulse Encryption Handler Info: ' . $message);
    }
}