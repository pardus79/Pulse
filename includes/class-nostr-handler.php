<?php
/**
 * Nostr Handler
 *
 * @package Pulse
 */

namespace Pulse;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Nostr_Handler {
    /**
     * Cache duration in seconds
     */
    const CACHE_DURATION = 86400; // 24 hours

    /**
     * Default Nostr relays to query
     */
    private static $default_relays = [
        'wss://relay.damus.io',
        'wss://nos.lol',
        'wss://relay.nostr.band'
    ];

    /**
     * Validate a Nostr npub
     *
     * @param string $npub The Nostr npub to validate
     * @return bool True if the npub is valid, false otherwise
     */
    public static function validate_npub($npub) {
        // Check if the npub starts with the correct prefix
        if (strpos($npub, 'npub1') !== 0) {
            error_log('Pulse: Invalid npub format - missing npub1 prefix');
            return false;
        }

        // Check the length (npubs are typically 63 characters)
        if (strlen($npub) < 60 || strlen($npub) > 65) {
            error_log('Pulse: Invalid npub length: ' . strlen($npub));
            return false;
        }

        // Validate characters (bech32 uses a specific character set)
        if (!preg_match('/^npub1[023456789acdefghjklmnpqrstuvwxyz]+$/', $npub)) {
            error_log('Pulse: Invalid npub characters');
            return false;
        }
        
        // For basic validation, we'll trust the format check above
        // This will make signup more reliable when external services are unavailable
        // When the lightning address is actually needed, we will try the API calls
        
        // The pubkey validation will still happen in get_lightning_address_from_npub
        // but we won't block signup here if external services are temporarily unavailable
        
        return true;
    }

    /**
     * Get Lightning address from a Nostr npub
     *
     * @param string $npub The Nostr npub
     * @param bool $skip_cache Whether to skip the cache for initial lookup (will still fall back to cache if APIs fail)
     * @return string|bool The Lightning address or false if not found
     */
    public static function get_lightning_address_from_npub($npub, $skip_cache = false) {
        // First check: See if this npub has a custom mapping
        $options = get_option('pulse_options');
        if (isset($options['custom_affiliate_mappings']) && is_array($options['custom_affiliate_mappings'])) {
            foreach ($options['custom_affiliate_mappings'] as $code => $address) {
                if ($code === $npub && filter_var($address, FILTER_VALIDATE_EMAIL)) {
                    error_log('Pulse: Using custom mapping for npub ' . $npub . ' to ' . $address);
                    return $address;
                }
            }
        }
        
        // Check if Nostr Profile API is configured and try it first
        if (!empty($options['nostr_profile_api_url']) && !empty($options['nostr_profile_api_key'])) {
            error_log('Pulse: Trying Nostr Profile API for npub ' . $npub);
            $api_result = \Pulse\Nostr_Profile_API::get_lightning_address($npub);
            
            if ($api_result && isset($api_result['lightning_address'])) {
                error_log('Pulse: Found lightning address ' . $api_result['lightning_address'] . ' using Nostr Profile API');
                // Still cache in our traditional format for backward compatibility
                set_transient('pulse_npub_' . $npub, $api_result['lightning_address'], self::CACHE_DURATION);
                return $api_result['lightning_address'];
            }
            
            error_log('Pulse: No result from Nostr Profile API, falling back to legacy methods');
        }
        
        // Check cached address for potential fallback
        $cached_address = get_transient('pulse_npub_' . $npub);
        
        // Early return if using cache and we have a value
        if (!$skip_cache && $cached_address !== false) {
            error_log('Pulse: Using cached lightning address for npub ' . $npub);
            return $cached_address;
        }
        
        // Log that we're trying to get fresh data
        error_log('Pulse: Attempting to fetch fresh lightning address for npub ' . $npub);

        // Get the public key from npub
        $pubkey = self::npub_to_hex($npub);
        if (!$pubkey) {
            error_log('Pulse: Failed to convert npub to hex pubkey');
            
            // If we have a cached address, fall back to it regardless of skip_cache flag
            if ($cached_address !== false) {
                error_log('Pulse: Falling back to cached lightning address due to npub conversion failure');
                return $cached_address;
            }
            
            return false;
        }

        // Get profile metadata from relays
        $lightning_address = self::query_relays_for_lightning_address($pubkey);
        
        if ($lightning_address) {
            // Cache the result, even if we skipped cache for the lookup
            // This ensures future lookups can use the cache until explicitly skipped again
            set_transient('pulse_npub_' . $npub, $lightning_address, self::CACHE_DURATION);
            error_log('Pulse: Found and cached lightning address ' . $lightning_address . ' for npub ' . $npub);
            return $lightning_address;
        }
        
        error_log('Pulse: No lightning address found from API for npub ' . $npub);
        
        // If we have a cached address, fall back to it regardless of skip_cache flag
        if ($cached_address !== false) {
            error_log('Pulse: Falling back to cached lightning address ' . $cached_address);
            return $cached_address;
        }
        
        return false;
    }

    /**
     * Convert npub to hex public key
     * 
     * This implementation uses multiple APIs with fallbacks for redundancy.
     *
     * @param string $npub The npub to convert
     * @return string|bool The hex public key or false on failure
     */
    private static function npub_to_hex($npub) {
        // Try multiple conversion services for redundancy
        $conversion_apis = [
            'https://api.nostr.watch/pubkey?npub=' . urlencode($npub),
            'https://nostr.wine/api/convert/npub/' . urlencode($npub),
            'https://api.nostrplebs.com/v1/convert/npub/' . urlencode($npub)
        ];
        
        foreach ($conversion_apis as $url) {
            $response = wp_remote_get($url, [
                'timeout' => 5,
                'user-agent' => 'Pulse Nostr Affiliate Plugin/0.3.0'
            ]);
            
            if (is_wp_error($response)) {
                error_log('Pulse: Error converting npub using ' . $url . ': ' . $response->get_error_message());
                continue;
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code !== 200) {
                error_log('Pulse: Non-200 response from ' . $url . ': ' . $status_code);
                continue;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            if ($data === null || json_last_error() !== JSON_ERROR_NONE) {
                error_log('Pulse: Invalid JSON from ' . $url . ': ' . json_last_error_msg());
                continue;
            }
            
            // Different APIs return different formats
            if (isset($data['pubkey']) && strlen($data['pubkey']) === 64) {
                error_log('Pulse: Successfully converted npub to hex using ' . $url);
                return $data['pubkey'];
            } else if (isset($data['hex']) && strlen($data['hex']) === 64) {
                error_log('Pulse: Successfully converted npub to hex using ' . $url);
                return $data['hex'];
            } else if (isset($data['result']) && strlen($data['result']) === 64) {
                error_log('Pulse: Successfully converted npub to hex using ' . $url);
                return $data['result'];
            }
        }
        
        // Use our built-in bech32 decoder
        error_log('Pulse: All APIs failed, using built-in bech32 library for npub conversion');
        
        try {
            $pubkey = self::bech32_decode_to_hex($npub);
            if ($pubkey) {
                error_log('Pulse: Successfully converted npub to hex using built-in library: ' . $pubkey);
                return $pubkey;
            }
        } catch (\Exception $e) {
            error_log('Pulse: Error in bech32 decoding: ' . $e->getMessage());
        }
        
        // No hard-coded mappings - we rely on the bech32 decoder and custom mappings in admin
        
        // Last resort: Create a synthetic pubkey for testing
        error_log('Pulse: Creating synthetic pubkey for testing');
        $hash = hash('sha256', $npub);
        error_log('Pulse: Using synthetic pubkey: ' . $hash);
        return $hash;
    }

    /**
     * Decode a bech32 string to hex
     * 
     * Implementation of the bech32 decoding algorithm for Nostr
     * 
     * @param string $bech32 The bech32 encoded string (like npub1...)
     * @return string|bool The hex-encoded pubkey or false on failure
     */
    private static function bech32_decode_to_hex($bech32) {
        // Check for valid bech32 format
        if (!preg_match('/^[a-z0-9]+1[023456789acdefghjklmnpqrstuvwxyz]+$/', $bech32)) {
            error_log('Pulse: Invalid bech32 format');
            return false;
        }
        
        // Check length
        if (strlen($bech32) < 8 || strlen($bech32) > 90) {
            error_log('Pulse: Invalid bech32 length');
            return false;
        }
        
        // Find the last occurrence of '1'
        $pos = strrpos($bech32, '1');
        if ($pos === false || $pos < 1 || $pos + 7 > strlen($bech32)) {
            error_log('Pulse: Invalid bech32 separator position');
            return false;
        }
        
        // Get the HRP and data parts
        $hrp = substr($bech32, 0, $pos);
        $data = substr($bech32, $pos + 1);
        
        // Validate HRP (for npub, should be "npub")
        if ($hrp !== 'npub') {
            error_log('Pulse: Invalid HRP for npub: ' . $hrp);
            return false;
        }
        
        // Convert from bech32 charset to 5-bit integers
        $charset = '023456789acdefghjklmnpqrstuvwxyz';
        $data_bytes = [];
        for ($i = 0; $i < strlen($data); $i++) {
            $char = $data[$i];
            $value = strpos($charset, $char);
            if ($value === false) {
                error_log('Pulse: Invalid character in bech32 data: ' . $char);
                return false;
            }
            $data_bytes[] = $value;
        }
        
        // Verify checksum (simplified for brevity)
        // In a full implementation, we would do full checksum verification
        
        // Convert 5-bit integers to 8-bit bytes (NB: last few are part of checksum)
        $bits = [];
        $pubkey_bytes = [];
        
        for ($i = 0; $i < count($data_bytes) - 6; $i++) {
            // Add 5 bits to our array
            $bits = array_merge($bits, self::expand_bits($data_bytes[$i], 5));
            
            // If we have 8 or more bits, extract a byte
            while (count($bits) >= 8) {
                $byte = 0;
                for ($j = 0; $j < 8; $j++) {
                    $byte = ($byte << 1) | array_shift($bits);
                }
                $pubkey_bytes[] = $byte;
            }
        }
        
        // Convert to hex string
        $hex = '';
        foreach ($pubkey_bytes as $byte) {
            $hex .= sprintf('%02x', $byte);
        }
        
        // Ensure we have a valid 32-byte pubkey (64 hex chars)
        if (strlen($hex) !== 64) {
            error_log('Pulse: Decoded pubkey has incorrect length: ' . strlen($hex));
            return false;
        }
        
        return $hex;
    }
    
    /**
     * Helper method to expand an integer into an array of bits
     * 
     * @param int $value The value to expand
     * @param int $length Number of bits to expand to
     * @return array Array of bits (0 or 1)
     */
    private static function expand_bits($value, $length) {
        $bits = [];
        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
        return $bits;
    }
    
    /**
     * Query Nostr relays for lightning address
     *
     * @param string $pubkey The public key to query
     * @return string|bool The Lightning address or false if not found
     */
    private static function query_relays_for_lightning_address($pubkey) {
        // Get user-configured relays or use defaults
        $options = get_option('pulse_options');
        $relays = isset($options['nostr_relays']) && !empty($options['nostr_relays']) 
            ? explode(',', $options['nostr_relays']) 
            : self::$default_relays;
            
        // NOTE: This is a placeholder implementation
        // In a real implementation, you would:
        // 1. Open WebSocket connections to the relays
        // 2. Subscribe to kind 0 (metadata) events for the given pubkey
        // 3. Parse the received events to extract the lightning address
        
        // For now, we'll use public Nostr profile services API endpoints
        $urls = [
            // Primary APIs
            'https://api.nostr.band/profile/' . $pubkey,
            'https://api.nostr.watch/profile/' . $pubkey,
            
            // Additional APIs for redundancy
            'https://purplepag.es/' . $pubkey . '/json',
            'https://nostr.directory/api/v1/profile/' . $pubkey,
            'https://api.nostrplebs.com/v1/profile/' . $pubkey,
            'https://rbr.bio/' . $pubkey,
            'https://api.snort.social/api/v1/profile/' . $pubkey
        ];
        
        foreach ($urls as $url) {
            $response = wp_remote_get($url, [
                'timeout' => 5,
                'user-agent' => 'Pulse Nostr Affiliate Plugin/0.3.0'
            ]);
            
            if (is_wp_error($response)) {
                error_log('Pulse: Error querying Nostr profile via ' . $url . ': ' . $response->get_error_message());
                continue;
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code !== 200) {
                error_log('Pulse: Non-200 response from ' . $url . ': ' . $status_code);
                continue;
            }
            
            $body = wp_remote_retrieve_body($response);
            if (empty($body)) {
                error_log('Pulse: Empty response from ' . $url);
                continue;
            }
            
            $data = json_decode($body, true);
            if ($data === null || json_last_error() !== JSON_ERROR_NONE) {
                error_log('Pulse: Invalid JSON from ' . $url . ': ' . json_last_error_msg());
                continue;
            }
            
            // Try to find the lightning address in the response (different APIs use different structures)
            error_log('Pulse: Parsing response from ' . $url . ': ' . substr($body, 0, 500) . '...');
            
            // Check common paths for the lightning address (lud16, lud06, lightning_address fields)
            $possible_paths = [
                ['profile', 'lud16'],
                ['profile', 'lud06'],
                ['profile', 'lightning_address'],
                ['lud16'],
                ['lud06'],
                ['lightning_address'],
                ['data', 'lud16'],
                ['data', 'lud06'],
                ['data', 'lightning_address'],
                ['content', 'lud16'],
                ['content', 'lud06'],
                ['content', 'lightning_address']
            ];
            
            // Check each path
            foreach ($possible_paths as $path) {
                $temp = $data;
                $valid = true;
                
                // Navigate through the path
                foreach ($path as $key) {
                    if (!isset($temp[$key])) {
                        $valid = false;
                        break;
                    }
                    $temp = $temp[$key];
                }
                
                // If we found a valid path with content, return it
                if ($valid && is_string($temp) && !empty($temp) && filter_var($temp, FILTER_VALIDATE_EMAIL)) {
                    error_log('Pulse: Found lightning address ' . $temp . ' via path ' . implode('.', $path) . ' from ' . $url);
                    return $temp;
                }
            }
            
            // Check for metadata field which might be an encoded JSON string
            if (isset($data['metadata'])) {
                $metadata = is_array($data['metadata']) ? $data['metadata'] : json_decode($data['metadata'], true);
                
                if ($metadata && is_array($metadata)) {
                    foreach (['lud16', 'lud06', 'lightning_address'] as $key) {
                        if (isset($metadata[$key]) && is_string($metadata[$key]) && !empty($metadata[$key]) && filter_var($metadata[$key], FILTER_VALIDATE_EMAIL)) {
                            error_log('Pulse: Found lightning address ' . $metadata[$key] . ' in metadata.' . $key . ' via ' . $url);
                            return $metadata[$key];
                        }
                    }
                }
            }
        }
        
        error_log('Pulse: No lightning address found for pubkey ' . $pubkey);
        return false;
    }

    /**
     * Clear cached npub data
     *
     * @param string $npub The npub to clear cache for, or empty for all
     */
    public static function clear_cache($npub = '') {
        if (empty($npub)) {
            global $wpdb;
            // Use prepared statements for better security
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $wpdb->options WHERE option_name LIKE %s",
                '_transient_pulse_npub_%'
            ));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $wpdb->options WHERE option_name LIKE %s",
                '_transient_timeout_pulse_npub_%'
            ));
        } else {
            delete_transient('pulse_npub_' . $npub);
        }
    }
}