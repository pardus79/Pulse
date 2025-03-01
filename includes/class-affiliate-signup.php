<?php
/**
 * Pulse Affiliate Signup
 *
 * @package Pulse
 */

namespace Pulse;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Affiliate_Signup {
    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode('pulse_affiliate_signup', array($this, 'affiliate_signup_shortcode'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_pulse_generate_affiliate_link', array($this, 'generate_affiliate_link'));
        add_action('wp_ajax_nopriv_pulse_generate_affiliate_link', array($this, 'generate_affiliate_link'));
    }

    /**
     * Affiliate signup shortcode
     *
     * @return string The affiliate signup form HTML
     */
    public function affiliate_signup_shortcode() {
        ob_start();
        include PULSE_PATH . 'templates/affiliate-signup-form.php';
        return ob_get_clean();
    }

    /**
     * Enqueue necessary scripts
     */
    public function enqueue_scripts() {
        // Correct path to JS file
        wp_enqueue_script('pulse-affiliate-signup', PULSE_URL . 'js/affiliate-signup.js', array('jquery'), PULSE_VERSION, true);
        wp_localize_script('pulse-affiliate-signup', 'pulseAffiliateSignup', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pulse_affiliate_signup_nonce')
        ));
    }

    /**
     * Generate affiliate link
     */
    public function generate_affiliate_link() {
        check_ajax_referer('pulse_affiliate_signup_nonce', 'nonce');

        // Get input type and value
        $input_type = isset($_POST['input_type']) ? sanitize_text_field($_POST['input_type']) : 'lightning';
        $input_value = isset($_POST['input_value']) ? sanitize_text_field($_POST['input_value']) : '';
        
        $options = get_option('pulse_options');
        $store_url = isset($options['store_url']) ? $options['store_url'] : site_url();
        $enable_nostr = isset($options['enable_nostr']) ? $options['enable_nostr'] : false;
        
        // Process based on input type
        if ($input_type === 'npub' && $enable_nostr) {
            // Validate npub format
            if (!\Pulse\Nostr_Handler::validate_npub($input_value)) {
                wp_send_json_error(__('Invalid Nostr npub format.', 'pulse'));
                return;
            }
            
            // Generate direct npub link
            $nostr_link = add_query_arg('aff', urlencode($input_value), $store_url);
            
            // Try to get lightning address from npub
            $lightning_address = \Pulse\Nostr_Handler::get_lightning_address_from_npub($input_value);
            
            // If we found a lightning address, also generate an encrypted link
            if ($lightning_address && Lightning_Address_Validator::validate($lightning_address)) {
                // Create unencrypted lightning address link
                $unencrypted_link = add_query_arg('aff', urlencode($lightning_address), $store_url);
                
                // Create encrypted link
                $encryption_handler = new Encryption_Handler();
                $encrypted_address = $encryption_handler->encrypt($lightning_address);
                
                if ($encrypted_address !== false) {
                    $encrypted_link = add_query_arg('aff', urlencode($encrypted_address), $store_url);
                    
                    wp_send_json_success(array(
                        'nostr_link' => esc_url($nostr_link),
                        'unencrypted_link' => esc_url($unencrypted_link),
                        'encrypted_link' => esc_url($encrypted_link),
                        'has_lightning' => true
                    ));
                    return;
                }
            }
            
            // If no lightning address found or encryption failed, just return the npub link
            wp_send_json_success(array(
                'nostr_link' => esc_url($nostr_link),
                'has_lightning' => false
            ));
            return;
        } 
        else {
            // Process as lightning address
            $lightning_address = $input_value;
            
            if (!Lightning_Address_Validator::validate($lightning_address)) {
                wp_send_json_error(__('Invalid Lightning address format.', 'pulse'));
                return;
            }
            
            // Create unencrypted link
            $unencrypted_link = add_query_arg('aff', urlencode($lightning_address), $store_url);
            
            // Create encrypted link
            $encryption_handler = new Encryption_Handler();
            $encrypted_address = $encryption_handler->encrypt($lightning_address);
            
            if ($encrypted_address === false) {
                wp_send_json_error(__('Encryption failed. Please try again.', 'pulse'));
                return;
            }
            
            $encrypted_link = add_query_arg('aff', urlencode($encrypted_address), $store_url);
            
            wp_send_json_success(array(
                'unencrypted_link' => esc_url($unencrypted_link),
                'encrypted_link' => esc_url($encrypted_link)
            ));
        }
    }
}