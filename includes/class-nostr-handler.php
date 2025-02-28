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

        // Basic validation passed
        return true;
    }

    /**
     * Get Lightning address from a Nostr npub
     *
     * @param string $npub The Nostr npub
     * @return string|bool The Lightning address or false if not found
     */
    public static function get_lightning_address_from_npub($npub) {
        // Check if we have a cached result
        $cached_address = get_transient('pulse_npub_' . $npub);
        if ($cached_address !== false) {
            return $cached_address;
        }

        // Get the public key from npub
        $pubkey = self::npub_to_hex($npub);
        if (!$pubkey) {
            error_log('Pulse: Failed to convert npub to hex pubkey');
            return false;
        }

        // Get profile metadata from relays
        $lightning_address = self::query_relays_for_lightning_address($pubkey);
        
        if ($lightning_address) {
            // Cache the result
            set_transient('pulse_npub_' . $npub, $lightning_address, self::CACHE_DURATION);
            return $lightning_address;
        }
        
        return false;
    }

    /**
     * Convert npub to hex public key
     * 
     * This is a basic implementation. For production, use a library or more robust implementation.
     *
     * @param string $npub The npub to convert
     * @return string|bool The hex public key or false on failure
     */
    private static function npub_to_hex($npub) {
        // This is a placeholder. In a real implementation, you would:
        // 1. Decode the bech32 string
        // 2. Extract the data part
        // 3. Convert to hex
        
        // For now, we'll use a simple HTTP request to a service that can do this conversion
        // This is just for demonstration - in production, use a proper library
        $response = wp_remote_get('https://api.nostr.watch/pubkey?npub=' . urlencode($npub));
        
        if (is_wp_error($response)) {
            error_log('Pulse: Error converting npub to hex: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['pubkey']) && strlen($data['pubkey']) === 64) {
            return $data['pubkey'];
        }
        
        error_log('Pulse: Failed to convert npub to hex pubkey with API');
        return false;
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
        
        // For now, we'll use a simple HTTP request to a Nostr profile service
        $urls = [
            'https://api.nostr.band/profile/' . $pubkey,
            'https://api.nostr.watch/profile/' . $pubkey
        ];
        
        foreach ($urls as $url) {
            $response = wp_remote_get($url, [
                'timeout' => 5
            ]);
            
            if (is_wp_error($response)) {
                error_log('Pulse: Error querying Nostr profile: ' . $response->get_error_message());
                continue;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            // Try to find the lightning address in the response
            if (isset($data['profile']) && isset($data['profile']['lud16'])) {
                return $data['profile']['lud16'];
            } elseif (isset($data['metadata'])) {
                $metadata = json_decode($data['metadata'], true);
                if (isset($metadata['lud16'])) {
                    return $metadata['lud16'];
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
            $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_pulse_npub_%'");
            $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_pulse_npub_%'");
        } else {
            delete_transient('pulse_npub_' . $npub);
        }
    }
}