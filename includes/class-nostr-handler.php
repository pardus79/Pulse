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
        
        // Use our npub_to_hex method to validate the bech32 encoding
        // This provides an extra layer of validation since npub_to_hex performs
        // a full bech32 validation via API calls
        $pubkey = self::npub_to_hex($npub);
        if (!$pubkey) {
            error_log('Pulse: Failed bech32 validation for npub');
            return false;
        }

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
        // Get cached address for potential fallback
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
        
        error_log('Pulse: Failed to convert npub to hex pubkey with any API');
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
            if (isset($data['profile']) && isset($data['profile']['lud16'])) {
                error_log('Pulse: Found lightning address via ' . $url);
                return $data['profile']['lud16'];
            } elseif (isset($data['metadata'])) {
                $metadata = is_array($data['metadata']) ? $data['metadata'] : json_decode($data['metadata'], true);
                if ($metadata && isset($metadata['lud16'])) {
                    error_log('Pulse: Found lightning address in metadata via ' . $url);
                    return $metadata['lud16'];
                }
            } elseif (isset($data['lightning_address'])) {
                error_log('Pulse: Found direct lightning_address field via ' . $url);
                return $data['lightning_address'];
            } elseif (isset($data['lud16'])) {
                error_log('Pulse: Found direct lud16 field via ' . $url);
                return $data['lud16'];
            } elseif (isset($data['data']) && isset($data['data']['lud16'])) {
                error_log('Pulse: Found lightning address in data.lud16 via ' . $url);
                return $data['data']['lud16'];
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