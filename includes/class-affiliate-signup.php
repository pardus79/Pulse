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
        wp_enqueue_script('pulse-affiliate-signup', PULSE_URL . 'assets/js/affiliate-signup.js', array('jquery'), PULSE_VERSION, true);
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

        $lightning_address = sanitize_text_field($_POST['lightning_address']);

        if (!Lightning_Address_Validator::validate($lightning_address)) {
            wp_send_json_error(__('Invalid Lightning address format.', 'pulse'));
        }

        $encryption_handler = new Encryption_Handler();
        $encrypted_address = $encryption_handler->encrypt($lightning_address);

        if ($encrypted_address === false) {
            wp_send_json_error(__('Encryption failed. Please try again.', 'pulse'));
        }

        $options = get_option('pulse_options');
        $store_url = isset($options['store_url']) ? $options['store_url'] : site_url();

        $affiliate_link = add_query_arg('aff', urlencode($encrypted_address), $store_url);

        wp_send_json_success(array(
            'affiliate_link' => esc_url($affiliate_link),
            'public_key' => $encryption_handler->get_public_key()
        ));
    }
}