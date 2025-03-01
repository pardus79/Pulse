<?php
/**
 * Nostr Profile API Integration
 *
 * @package Pulse
 */

namespace Pulse;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class Nostr_Profile_API
 * Handles integration with Nostr Profile API services to lookup Lightning addresses
 */
class Nostr_Profile_API {
    /**
     * Default cache duration in seconds
     */
    const DEFAULT_CACHE_DURATION = 3600; // 1 hour

    /**
     * Query the Nostr Profile API to get a Lightning address for an npub
     *
     * @param string $npub The Nostr npub to lookup
     * @return array|false The response data or false on failure
     */
    public static function get_lightning_address($npub) {
        $options = get_option('pulse_options');
        
        // Check if API settings are configured
        if (empty($options['nostr_profile_api_url']) || empty($options['nostr_profile_api_key'])) {
            error_log('Pulse: Nostr Profile API not configured properly');
            return false;
        }

        // Check cache first
        $cache_duration = isset($options['nostr_profile_api_cache_duration']) ? 
            intval($options['nostr_profile_api_cache_duration']) * 3600 : // Convert hours to seconds
            self::DEFAULT_CACHE_DURATION;
        
        $cached_data = get_transient('pulse_nostr_profile_' . $npub);
        if ($cached_data !== false) {
            error_log('Pulse: Using cached profile data for npub ' . $npub);
            return $cached_data;
        }

        // Build API URL
        $api_url = trailingslashit($options['nostr_profile_api_url']);
        $endpoint = 'api/v1/profile/' . urlencode($npub);
        $url = $api_url . $endpoint;

        // Make API request
        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'X-API-Key' => $options['nostr_profile_api_key'],
                'User-Agent' => 'Pulse Nostr Affiliate Plugin'
            ]
        ]);

        // Check for errors
        if (is_wp_error($response)) {
            error_log('Pulse: API request failed: ' . $response->get_error_message());
            return false;
        }

        // Check response code
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            error_log('Pulse: API returned non-200 status code: ' . $status_code);
            return false;
        }

        // Parse JSON response
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data === null || json_last_error() !== JSON_ERROR_NONE) {
            error_log('Pulse: Invalid JSON response: ' . json_last_error_msg());
            return false;
        }

        // Check if response contains lightning_address
        if (empty($data['lightning_address'])) {
            error_log('Pulse: No lightning_address found in API response');
            return false;
        }

        // Cache the successful response
        set_transient('pulse_nostr_profile_' . $npub, $data, $cache_duration);

        return $data;
    }

    /**
     * Test connection to the Nostr Profile API
     *
     * @return array Response with success/error status and message
     */
    public static function test_connection() {
        $options = get_option('pulse_options');
        
        // Validate settings
        if (empty($options['nostr_profile_api_url'])) {
            return [
                'success' => false,
                'message' => __('API URL is not configured', 'pulse')
            ];
        }

        if (empty($options['nostr_profile_api_key'])) {
            return [
                'success' => false,
                'message' => __('API Key is not configured', 'pulse')
            ];
        }

        // Build API URL for relays endpoint which should exist
        $api_url = trailingslashit($options['nostr_profile_api_url']);
        $test_endpoint = 'api/v1/relays';
        $url = $api_url . $test_endpoint;
        
        error_log('Pulse: Testing API connection to: ' . $url);

        // Make API request
        $response = wp_remote_get($url, [
            'timeout' => 10, 
            'headers' => [
                'X-API-Key' => $options['nostr_profile_api_key'],
                'User-Agent' => 'Pulse Nostr Affiliate Plugin'
            ]
        ]);

        // Check for errors
        if (is_wp_error($response)) {
            error_log('Pulse: API connection error: ' . $response->get_error_message());
            return [
                'success' => false,
                'message' => sprintf(__('Connection failed: %s', 'pulse'), $response->get_error_message())
            ];
        }

        // Log response
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        error_log('Pulse: API response code: ' . $status_code);
        error_log('Pulse: API response body: ' . $body);

        // Check response code
        if ($status_code !== 200) {
            return [
                'success' => false,
                'message' => sprintf(__('API returned error code: %d', 'pulse'), $status_code)
            ];
        }

        // Parse JSON response
        $data = json_decode($body, true);

        if ($data === null || json_last_error() !== JSON_ERROR_NONE) {
            error_log('Pulse: API JSON parse error: ' . json_last_error_msg());
            return [
                'success' => false,
                'message' => sprintf(__('API returned invalid JSON response: %s', 'pulse'), json_last_error_msg())
            ];
        }

        // Log parsed data structure
        error_log('Pulse: API response data: ' . print_r($data, true));

        // Try to detect the structure of the response
        if (isset($data['relays']) && is_array($data['relays'])) {
            return [
                'success' => true,
                'message' => sprintf(__('Connection successful! Connected to %d relays.', 'pulse'), count($data['relays']))
            ];
        } elseif (is_array($data) && !empty($data)) {
            // If data is an array but doesn't have 'relays' key, it might be directly returning the relays
            return [
                'success' => true,
                'message' => sprintf(__('Connection successful! Connected to %d relays.', 'pulse'), count($data))
            ];
        } else {
            // Provide detailed debug info in the error message
            return [
                'success' => false,
                'message' => sprintf(__('API response format unexpected: %s', 'pulse'), 
                    substr(json_encode($data), 0, 100) . (strlen(json_encode($data)) > 100 ? '...' : ''))
            ];
        }
    }

    /**
     * Clear the API cache
     *
     * @param string $npub Specific npub to clear, or empty for all
     */
    public static function clear_cache($npub = '') {
        if (empty($npub)) {
            global $wpdb;
            // Delete all API cache entries
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $wpdb->options WHERE option_name LIKE %s",
                '_transient_pulse_nostr_profile_%'
            ));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $wpdb->options WHERE option_name LIKE %s",
                '_transient_timeout_pulse_nostr_profile_%'
            ));
        } else {
            // Delete cache for specific npub
            delete_transient('pulse_nostr_profile_' . $npub);
        }
    }
}