<?php
/**
 * The core plugin class.
 *
 * @package Pulse
 */

namespace Pulse;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Pulse {

    protected $loader;
    protected $plugin_name;
    protected $version;

    public function __construct() {
        $this->version = PULSE_VERSION;
        $this->plugin_name = 'pulse';
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    private function load_dependencies() {
        require_once PULSE_PATH . 'includes/class-pulse-loader.php';
        require_once PULSE_PATH . 'includes/class-pulse-i18n.php';
        require_once PULSE_PATH . 'includes/class-pulse-admin.php';
        require_once PULSE_PATH . 'includes/class-pulse-public.php';
        require_once PULSE_PATH . 'includes/class-lightning-address-validator.php';
        require_once PULSE_PATH . 'includes/class-btcpay-integration.php';
        require_once PULSE_PATH . 'includes/class-encryption-handler.php';
        require_once PULSE_PATH . 'includes/class-order-processor.php';
        require_once PULSE_PATH . 'includes/class-affiliate-signup.php';
        require_once PULSE_PATH . 'includes/class-admin-settings.php';

        $this->loader = new Pulse_Loader();
    }

    private function set_locale() {
        $plugin_i18n = new I18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    private function define_admin_hooks() {
        $plugin_admin = new Admin($this->get_plugin_name(), $this->get_version());
        $admin_settings = new Admin_Settings();

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        
        // Add settings page
        $this->loader->add_action('admin_menu', $admin_settings, 'add_plugin_page');
        $this->loader->add_action('admin_init', $admin_settings, 'page_init');
    }

    private function define_public_hooks() {
        $plugin_public = new Pulse_Public($this->get_plugin_name(), $this->get_version());
        $affiliate_signup = new Affiliate_Signup();
        $order_processor = new Order_Processor();

        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'localize_script');
        
        // Add affiliate signup shortcode
        $this->loader->add_shortcode('pulse_affiliate_signup', $affiliate_signup, 'affiliate_signup_shortcode');
        
        // Hook order processor
        $this->loader->add_action('woocommerce_order_status_completed', $order_processor, 'process_affiliate_payment');
    }

    public function run() {
        $this->loader->run();
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_loader() {
        return $this->loader;
    }

    public function get_version() {
        return $this->version;
    }
}