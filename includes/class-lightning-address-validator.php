<?php
/**
 * Lightning Address Validator
 *
 * @package Pulse
 */

namespace Pulse;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Lightning_Address_Validator {
    /**
     * Validate a Lightning address
     *
     * @param string $address The Lightning address to validate
     * @return bool True if the address is valid, false otherwise
     */
    public static function validate($address) {
        $pattern = '/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/';
        return (bool) preg_match($pattern, $address);
    }
}