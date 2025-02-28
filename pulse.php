<?php
/**
 * Plugin Name: Pulse
 * Plugin URI: https://github.com/pardus79/Pulse
 * Description: Automated affiliate payouts for WooCommerce using Bitcoin Lightning Network and BTCPayServer (Compatible with BTCPay Server 1.x and 2.0 and Nostr npubs)
 * Version: 0.3.0
 * Author: BtcPins
 * Author URI: https://btcpins.com
 * License: The Unlicense
 * License URI: https://unlicense.org
 * Text Domain: pulse
 * Domain Path: /languages
 *
 * @package Pulse
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

spl_autoload_register(function ($class) {
    // Debug to show every class request
    error_log("Pulse: Autoloader called for class: " . $class);

    // Base directory for the namespace prefix
    $base_dir = __DIR__ . '/includes/';

    // Namespace prefix
    $prefix = 'Pulse\\';

    // Does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // No, move to the next registered autoloader
        error_log("Pulse: Class $class doesn't use Pulse namespace, skipping");
        return;
    }

    // Get the relative class name
    $relative_class = substr($class, $len);
    error_log("Pulse: Looking for relative class: " . $relative_class);

    // Try different file naming conventions
    $file_variants = [
        $base_dir . 'class-' . strtolower(str_replace('\\', '-', $relative_class)) . '.php',
        $base_dir . 'class-' . strtolower(str_replace(['\\', '_'], '-', $relative_class)) . '.php',
        $base_dir . 'class-' . strtolower(str_replace(['\\', '_'], '', $relative_class)) . '.php',
    ];

    error_log("Pulse: Will try these files: " . implode(', ', $file_variants));

    // Check if files exist before trying to require them
    foreach ($file_variants as $file) {
        error_log("Pulse: Checking if file exists: " . $file . " - " . (file_exists($file) ? 'YES' : 'NO'));
        if (file_exists($file)) {
            error_log("Pulse: Loading file: " . $file);
            require_once $file;
            error_log("Pulse: Successfully loaded class file: " . $file);
            return true;
        }
    }

    error_log("Pulse: Failed to load class file for: " . $class);
    error_log("Pulse: Tried files: " . implode(', ', $file_variants));
});

// Define plugin constants
define('PULSE_VERSION', '0.3.0');
define('PULSE_PATH', plugin_dir_path(__FILE__));
define('PULSE_URL', plugin_dir_url(__FILE__));

// Direct includes for required files instead of relying on autoloader
require_once PULSE_PATH . 'includes/class-btcpay-integration.php';
require_once PULSE_PATH . 'includes/class-admin-settings.php';
require_once PULSE_PATH . 'includes/class-affiliate-link-handler.php';
require_once PULSE_PATH . 'includes/class-affiliate-processor.php';
require_once PULSE_PATH . 'includes/class-shortcode-handler.php';
require_once PULSE_PATH . 'includes/class-nostr-handler.php';
require_once PULSE_PATH . 'includes/class-encryption-handler.php';
require_once PULSE_PATH . 'includes/class-nostr-profile-api.php';
// Don't load class-pulse-admin.php to avoid duplicate admin menus
// Classes are loaded directly

// Main plugin class
if (!class_exists('Pulse')) {
    class Pulse {
        private $affiliate_link_handler;
        private $btcpay_integration;
        private $admin_settings;
        private $affiliate_processor;
        private $shortcode_handler;
        
        public function __construct() {
            // Debug loading
            error_log('Pulse: Starting constructor');
            
            // Check if classes exist before instantiating
            if (!class_exists('\\Pulse\\BTCPay_Integration')) {
                error_log('Pulse: BTCPay_Integration class not found');
                return;
            }
            
            // Initialize core components
            try {
                error_log('Pulse: Initializing BTCPay_Integration');
                $this->btcpay_integration = new \Pulse\BTCPay_Integration();
                
                error_log('Pulse: Initializing Admin_Settings');
                $this->admin_settings = new \Pulse\Admin_Settings();
                
                error_log('Pulse: Initializing Affiliate_Link_Handler');
                $this->affiliate_link_handler = new \Pulse\Affiliate_Link_Handler();
                
                // Initialize modules
                error_log('Pulse: Initializing Affiliate_Processor');
                $this->affiliate_processor = new \Pulse\Affiliate_Processor($this->btcpay_integration, $this->affiliate_link_handler);
                
                error_log('Pulse: Initializing Shortcode_Handler');
                $this->shortcode_handler = new \Pulse\Shortcode_Handler($this->affiliate_link_handler);
                
                // Register activation hook
                register_activation_hook(__FILE__, array($this, 'activate'));
                
                error_log('Pulse: Constructor completed successfully');
            } catch (\Exception $e) {
                error_log('Pulse: Exception in constructor: ' . $e->getMessage());
            }
        }
        
        public function run() {
            // Nothing to do here - module initialization is done in constructor
        }

        public function activate() {
            // Create default options if they don't exist
            if (false == get_option('pulse_options')) {
                $default_options = array(
                    'store_url' => '',
                    'commission_rate' => 10,
                    'btcpay_url' => '',
                    'btcpay_api_key' => '',
                    'btcpay_store_id' => '',
                    'public_key' => '',
                    'private_key' => '',
                    'allow_unencrypted_addresses' => false,
                    'auto_approve_claims' => true,
                    'custom_affiliate_mappings' => array(),
                    'enable_nostr' => true,
                    'nostr_relays' => 'wss://relay.damus.io,wss://nos.lol,wss://relay.nostr.band',
                    'nostr_profile_api_url' => '',
                    'nostr_profile_api_key' => '',
                    'nostr_profile_api_cache_duration' => 1 // Default to 1 hour
                );
                add_option('pulse_options', $default_options);
            }
            
            // Create the affiliate signup page
            $this->shortcode_handler->create_affiliate_signup_page();
        }

        /**
         * Initialize the plugin
         */
        public static function init() {
            $plugin = new self();
            $plugin->run();
        }

        /**
         * Admin notice for missing core class
         */
        public static function missing_core_notice() {
            ?>
            <div class="error">
                <p><?php _e('Pulse plugin error: Core class is missing.', 'pulse'); ?></p>
            </div>
            <?php
        }
    }

    // Initialize plugin
    if (class_exists('Pulse')) {
        Pulse::init();
    } else {
        add_action('admin_notices', array('Pulse', 'missing_core_notice'));
    }
}
