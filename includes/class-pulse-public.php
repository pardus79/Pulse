<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @package Pulse
 */

namespace Pulse;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Public_Facing {

    private $plugin_name;
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @param string $plugin_name The name of the plugin.
     * @param string $version The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/pulse-public.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/pulse-public.js',
            array('jquery'),
            $this->version,
            false
        );
    }

    /**
     * Localize the script with new data
     */
    public function localize_script() {
        wp_localize_script(
            $this->plugin_name,
            'pulse_ajax',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('pulse-public-nonce')
            )
        );
    }

    /**
     * Register the shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('pulse_affiliate_signup', array($this, 'affiliate_signup_shortcode'));
    }

    /**
     * Callback for the affiliate signup shortcode
     *
     * @return string The HTML output for the affiliate signup form
     */
    public function affiliate_signup_shortcode() {
        ob_start();
        include plugin_dir_path(dirname(__FILE__)) . 'templates/affiliate-signup-form.php';
        return ob_get_clean();
    }

    /**
     * Process the affiliate link generation
     */
    public function generate_affiliate_link() {
        check_ajax_referer('pulse-public-nonce', 'nonce');

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