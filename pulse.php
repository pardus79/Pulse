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
    // Base directory for the namespace prefix
    $base_dir = __DIR__ . '/includes/';

    // Namespace prefix
    $prefix = 'Pulse\\';

    // Does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // No, move to the next registered autoloader
        return;
    }

    // Get the relative class name
    $relative_class = substr($class, $len);

    // Try different file naming conventions
    $file_variants = [
        $base_dir . 'class-' . strtolower(str_replace('\\', '-', $relative_class)) . '.php',
        $base_dir . 'class-' . strtolower(str_replace(['\\', '_'], '', $relative_class)) . '.php',
    ];

    foreach ($file_variants as $file) {
        if (file_exists($file)) {
            require $file;
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
use Pulse\Encryption_Handler;
require_once plugin_dir_path(__FILE__) . 'includes/class-encryption-handler.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-btcpay-integration.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-nostr-handler.php';
// Main plugin class
if (!class_exists('Pulse')) {
    class Pulse {
        private $encryption_key;
        private $btcpay_integration;
        
        public function __construct() {
            $this->btcpay_integration = new Pulse_BTCPay_Integration();
            
            add_action('admin_menu', array($this, 'add_plugin_page'));
            add_action('admin_init', array($this, 'page_init'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
            add_action('admin_notices', array($this, 'admin_notices'));
            add_action('init', array($this, 'register_shortcodes'));
            add_action('init', array($this, 'capture_affiliate'));
            add_action('woocommerce_checkout_order_processed', array($this, 'add_affiliate_to_order'), 10, 3);
            add_action('woocommerce_order_status_completed', array($this, 'process_affiliate_payout'));
            add_action('wp_ajax_pulse_generate_affiliate_link', array($this, 'process_affiliate_signup'));
            add_action('wp_ajax_nopriv_pulse_generate_affiliate_link', array($this, 'process_affiliate_signup'));
            
            // Add AJAX actions
            add_action('wp_ajax_pulse_clear_btcpay_cache', array($this, 'ajax_clear_btcpay_cache'));
            add_action('wp_ajax_pulse_dismiss_v2_notice', array($this, 'dismiss_v2_notice'));
            add_action('wp_ajax_pulse_clear_nostr_cache', array($this, 'ajax_clear_nostr_cache'));
            
            // Register options & activation
            $this->register_options();
            register_activation_hook(__FILE__, array($this, 'activate'));
            
            $this->encryption_key = get_option('pulse_encryption_key');
            if (!$this->encryption_key) {
                $this->encryption_key = bin2hex(random_bytes(16));
                update_option('pulse_encryption_key', $this->encryption_key);
            }
        }
        public function run() {
            // Main plugin execution code here
        }

        /**
         * AJAX handler for clearing BTCPay cache
         */
        public function ajax_clear_btcpay_cache() {
            check_ajax_referer('pulse_clear_btcpay_cache', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error('You do not have permission to clear the cache.');
                return;
            }
            
            $this->clear_cache();
            wp_send_json_success(array('message' => 'BTCPay Server cache cleared successfully!'));
        }
        
        /**
         * AJAX handler for clearing Nostr cache
         */
        public function ajax_clear_nostr_cache() {
            check_ajax_referer('pulse_clear_nostr_cache', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_send_json_error('You do not have permission to clear the cache.');
                return;
            }
            
            \Pulse\Nostr_Handler::clear_cache();
            wp_send_json_success(array('message' => 'Nostr npub cache cleared successfully!'));
        }
        
        /**
         * AJAX handler for dismissing v2 notice
         */
        public function dismiss_v2_notice() {
            check_ajax_referer('pulse-dismiss-notice', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized');
            }
            
            set_transient('pulse_v2_notice_dismissed', true, 30 * DAY_IN_SECONDS);
            wp_die();
        }
        
        /**
         * Clear all BTCPay Server caches
         */
        public function clear_cache() {
            $this->btcpay_integration->clear_version_cache();
            return true;
        }
        
        /**
         * Display admin notices
         */
        public function admin_notices() {
            // Check if we're on our settings page
            $screen = get_current_screen();
            if (!$screen || $screen->id !== 'settings_page_pulse-settings') {
                return;
            }
            
            // If BTCPay Server 2.0 is detected, show a notice about API permissions
            $is_v2 = $this->btcpay_integration->is_v2();
            if ($is_v2 === true) {
                // Only show if the notice hasn't been dismissed
                if (!get_transient('pulse_v2_notice_dismissed')) {
                    ?>
                    <div class="notice notice-info is-dismissible pulse-v2-notice">
                        <p>
                            <strong><?php _e('BTCPay Server 2.0 Detected', 'pulse'); ?></strong> - 
                            <?php _e('This plugin version is compatible with BTCPay Server 2.0. Please ensure your API key has the required permissions.', 'pulse'); ?>
                        </p>
                        <p>
                            <?php _e('Required permissions for BTCPay Server 2.0:', 'pulse'); ?>
                            <ul>
                                <li>btcpay.store.canmanagepullpayments</li>
                                <li>btcpay.store.canarchivepullpayments</li>
                                <li>btcpay.store.cancreatepullpayments</li>
                                <li>btcpay.store.canviewpullpayments</li>
                                <li>btcpay.store.cancreatenonapprovedpullpayments</li>
                                <li>btcpay.store.canmanagepayouts</li>
                                <li>btcpay.store.canviewpayouts</li>
                                <li>btcpay.store.canviewstoresettings</li>
                            </ul>
                        </p>
                    </div>
                    <script>
                    jQuery(document).ready(function($) {
                        $(document).on('click', '.pulse-v2-notice .notice-dismiss', function() {
                            $.ajax({
                                url: ajaxurl,
                                data: {
                                    action: 'pulse_dismiss_v2_notice',
                                    nonce: '<?php echo wp_create_nonce('pulse-dismiss-notice'); ?>'
                                }
                            });
                        });
                    });
                    </script>
                    <?php
                }
            }
        }
        public function activate() {
            $this->register_options();
            $this->create_affiliate_signup_page();
        }

        public function enqueue_admin_scripts($hook) {
            if ('settings_page_pulse-settings' !== $hook) {
                return;
            }
            wp_enqueue_script('jquery');
            wp_enqueue_style('pulse-admin-style', PULSE_URL . 'css/pulse-admin.css', array(), PULSE_VERSION);
            
            // Add script for BTCPay Server version detection
            wp_enqueue_script('pulse-admin', PULSE_URL . 'js/pulse-admin.js', array('jquery'), PULSE_VERSION, true);
            wp_localize_script('pulse-admin', 'pulseData', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'clearCacheNonce' => wp_create_nonce('pulse_clear_btcpay_cache'),
                'clearNostrCacheNonce' => wp_create_nonce('pulse_clear_nostr_cache')
            ));
        }

        public function add_plugin_page() {
            add_options_page(
                'Pulse Settings',
                'Pulse',
                'manage_options',
                'pulse-settings',
                array($this, 'create_admin_page')
            );
        }

        public function create_admin_page() {
            ?>
            <div class="wrap">
                <h1>Pulse Settings</h1>
                <form method="post" action="options.php">
                    <?php
                    settings_fields('pulse_option_group');
                    do_settings_sections('pulse-settings');
                    submit_button();
                    ?>
                </form>
            </div>
            <?php
        }

        public function page_init() {
            register_setting(
                'pulse_option_group',
                'pulse_options',
                array($this, 'sanitize')
            );

            add_settings_section(
                'pulse_general_section',
                'General Settings',
                array($this, 'general_section_info'),
                'pulse-settings'
            );

            add_settings_field(
                'store_url', 
                'Store URL', 
                array($this, 'store_url_callback'), 
                'pulse-settings', 
                'pulse_general_section'
            );

            add_settings_field(
                'commission_rate', 
                'Commission Rate (%)', 
                array($this, 'commission_rate_callback'), 
                'pulse-settings', 
                'pulse_general_section'
            );

            add_settings_section(
                'pulse_btcpay_section',
                'BTCPay Server Settings',
                array($this, 'btcpay_section_info'),
                'pulse-settings'
            );

            add_settings_field(
                'btcpay_url', 
                'BTCPay Server URL', 
                array($this, 'btcpay_url_callback'), 
                'pulse-settings', 
                'pulse_btcpay_section'
            );

            add_settings_field(
                'btcpay_api_key', 
                'BTCPay Server API Key', 
                array($this, 'btcpay_api_key_callback'), 
                'pulse-settings', 
                'pulse_btcpay_section'
            );

            add_settings_field(
                'btcpay_store_id', 
                'BTCPay Server Store ID', 
                array($this, 'btcpay_store_id_callback'), 
                'pulse-settings', 
                'pulse_btcpay_section'
            );
            
            add_settings_field(
                'auto_approve_claims', 
                'Auto-approve Claims', 
                array($this, 'auto_approve_claims_callback'), 
                'pulse-settings', 
                'pulse_btcpay_section'
            );

            // Add Cache Management field
            add_settings_field(
                'clear_btcpay_cache', 
                'Clear BTCPay Cache', 
                array($this, 'clear_btcpay_cache_callback'), 
                'pulse-settings', 
                'pulse_btcpay_section'
            );
            
            // Add Nostr section
            add_settings_section(
                'pulse_nostr_section',
                'Nostr Integration Settings',
                array($this, 'nostr_section_info'),
                'pulse-settings'
            );
            
            add_settings_field(
                'enable_nostr', 
                'Enable Nostr Integration', 
                array($this, 'enable_nostr_callback'), 
                'pulse-settings', 
                'pulse_nostr_section'
            );
            
            add_settings_field(
                'nostr_relays', 
                'Nostr Relays', 
                array($this, 'nostr_relays_callback'), 
                'pulse-settings', 
                'pulse_nostr_section'
            );
            
            add_settings_field(
                'clear_nostr_cache', 
                'Clear Nostr Cache', 
                array($this, 'clear_nostr_cache_callback'), 
                'pulse-settings', 
                'pulse_nostr_section'
            );
            
            add_settings_section(
                'pulse_encryption_section',
                'Encryption Settings',
                array($this, 'encryption_section_info'),
                'pulse-settings'
            );

            add_settings_field(
                'public_key', 
                'Public Key', 
                array($this, 'public_key_callback'), 
                'pulse-settings', 
                'pulse_encryption_section'
            );

            add_settings_field(
                'private_key', 
                'Private Key', 
                array($this, 'private_key_callback'), 
                'pulse-settings', 
                'pulse_encryption_section'
            );
            
            add_settings_section(
                'pulse_affiliate_section',
                'Affiliate Settings',
                array($this, 'affiliate_section_info'),
                'pulse-settings'
            );

            add_settings_field(
                'allow_unencrypted_addresses', 
                'Allow Unencrypted Addresses', 
                array($this, 'allow_unencrypted_addresses_callback'), 
                'pulse-settings', 
                'pulse_affiliate_section'
            );
        
            add_settings_field(
                'custom_affiliate_mappings', 
                'Custom Affiliate Mappings', 
                array($this, 'custom_affiliate_mappings_callback'), 
                'pulse-settings', 
                'pulse_affiliate_section'
            );
        }

        public function clear_btcpay_cache_callback() {
            ?>
            <button type="button" id="pulse-clear-cache" class="button button-secondary">Clear BTCPay Server Cache</button>
            <span id="cache-status" style="margin-left: 10px;"></span>
            <p class="description">Clear the cached BTCPay Server version information. Use this if you've upgraded your BTCPay Server.</p>
            <script>
                jQuery(document).ready(function($) {
                    $('#pulse-clear-cache').on('click', function() {
                        var $button = $(this);
                        var $status = $('#cache-status');
                        
                        $button.prop('disabled', true);
                        $status.text('Clearing cache...');
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'pulse_clear_btcpay_cache',
                                nonce: '<?php echo wp_create_nonce("pulse_clear_btcpay_cache"); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    $status.text(response.data.message);
                                    setTimeout(function() {
                                        location.reload();
                                    }, 1000);
                                } else {
                                    $status.text('Error clearing cache');
                                    $button.prop('disabled', false);
                                }
                            },
                            error: function() {
                                $status.text('Error clearing cache');
                                $button.prop('disabled', false);
                            }
                        });
                    });
                });
            </script>
            <?php
        }
        
        public function clear_nostr_cache_callback() {
            ?>
            <button type="button" id="pulse-clear-nostr-cache" class="button button-secondary">Clear Nostr Cache</button>
            <span id="nostr-cache-status" style="margin-left: 10px;"></span>
            <p class="description">Clear the cached Nostr npub information. Use this if lightning addresses have changed for npubs.</p>
            <script>
                jQuery(document).ready(function($) {
                    $('#pulse-clear-nostr-cache').on('click', function() {
                        var $button = $(this);
                        var $status = $('#nostr-cache-status');
                        
                        $button.prop('disabled', true);
                        $status.text('Clearing cache...');
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'pulse_clear_nostr_cache',
                                nonce: '<?php echo wp_create_nonce("pulse_clear_nostr_cache"); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    $status.text(response.data.message);
                                    setTimeout(function() {
                                        $button.prop('disabled', false);
                                        $status.text('');
                                    }, 2000);
                                } else {
                                    $status.text('Error clearing cache');
                                    $button.prop('disabled', false);
                                }
                            },
                            error: function() {
                                $status.text('Error clearing cache');
                                $button.prop('disabled', false);
                            }
                        });
                    });
                });
            </script>
            <?php
        }
        
        public function nostr_section_info() {
            echo 'Configure Nostr integration settings below:';
        }
        
        public function enable_nostr_callback() {
            $options = get_option('pulse_options');
            $enable_nostr = isset($options['enable_nostr']) ? $options['enable_nostr'] : false;
            echo '<input type="checkbox" id="enable_nostr" name="pulse_options[enable_nostr]" value="1" ' . checked(1, $enable_nostr, false) . '/>';
            echo '<label for="enable_nostr">Enable Nostr npub integration</label>';
            echo '<p class="description">When enabled, users can enter their Nostr npub in the affiliate signup form.</p>';
        }
        
        public function nostr_relays_callback() {
            $options = get_option('pulse_options');
            $nostr_relays = isset($options['nostr_relays']) ? $options['nostr_relays'] : 'wss://relay.damus.io,wss://nos.lol,wss://relay.nostr.band';
            echo '<textarea id="nostr_relays" name="pulse_options[nostr_relays]" rows="3" cols="50">' . esc_textarea($nostr_relays) . '</textarea>';
            echo '<p class="description"><strong>Note:</strong> This setting is reserved for future use. The plugin currently uses public APIs to fetch profile information for npubs.</p>';
        }

        public function sanitize($input) {
            $sanitary_values = array();
            $default_options = $this->get_default_options();

            foreach ($default_options as $key => $default_value) {
                if (isset($input[$key])) {
                    switch ($key) {
                        case 'store_url':
                        case 'btcpay_url':
                            $sanitary_values[$key] = esc_url_raw($input[$key]);
                            error_log("Pulse: Sanitizing {$key}: " . $input[$key] . " -> " . $sanitary_values[$key]);
                            break;
                        case 'btcpay_api_key':
                        case 'btcpay_store_id':
                            $sanitary_values[$key] = sanitize_text_field($input[$key]);
                            break;
                        case 'public_key':
                        case 'private_key':
                        case 'nostr_relays':
                            $sanitary_values[$key] = sanitize_textarea_field($input[$key]);
                            break;
                        case 'allow_unencrypted_addresses':
                        case 'auto_approve_claims':
                        case 'enable_nostr':
                            $sanitary_values[$key] = (bool) $input[$key];
                            break;
                        case 'custom_affiliate_mappings':
                            $sanitary_values[$key] = $this->sanitize_custom_mappings($input[$key]);
                            break;
                        default:
                            $sanitary_values[$key] = $input[$key];
                    }
               } else {
                    $sanitary_values[$key] = $default_value;
                    error_log("Pulse: Using default value for {$key}: " . $default_value);
                }
            }

            return $sanitary_values;
        }
        private function sanitize_custom_mappings($mappings) {
            $sanitized = array();
            if (is_array($mappings) && isset($mappings['custom_string']) && isset($mappings['lightning_address'])) {
                $custom_strings = array_map('sanitize_text_field', $mappings['custom_string']);
                $lightning_addresses = array_map('sanitize_email', $mappings['lightning_address']);
                $sanitized = array_combine($custom_strings, $lightning_addresses);
            }
            return $sanitized;
        }
        
        public function general_section_info() {
            echo 'Enter your general settings below:';
        }

        public function btcpay_section_info() {
            echo 'Enter your BTCPay Server settings below:';
            
            // Display detected BTCPay Server version if available
            if (!empty($this->btcpay_integration->get_api_url()) && 
                !empty($this->btcpay_integration->get_api_key()) && 
                !empty($this->btcpay_integration->get_store_id())) {
                
                $version = $this->btcpay_integration->get_version_status();
                $version_class = ($this->btcpay_integration->is_v2() === true) ? 'updated' : 'notice';
                
                echo '<div class="' . esc_attr($version_class) . ' notice inline">';
                echo '<p><strong>Detected: </strong>' . esc_html($version) . '</p>';
                echo '</div>';
            }
        }

        public function encryption_section_info() {
            echo 'Enter your RSA encryption keys below. These should be in PEM format. If you don\'t have a key pair, you can generate one using the button below.';
            echo '<br><button id="generate-keys" class="button button-secondary">Generate New Key Pair</button>';
            echo '<script>
                jQuery(document).ready(function($) {
                    $("#generate-keys").on("click", function(e) {
                        e.preventDefault();
                        $.ajax({
                            url: ajaxurl,
                            type: "POST",
                            data: {
                                action: "pulse_generate_keys",
                                nonce: "' . wp_create_nonce('pulse_generate_keys') . '"
                            },
                            success: function(response) {
                                if(response.success) {
                                    $("#public_key").val(response.data.public_key);
                                    $("#private_key").val(response.data.private_key);
                                    alert("New key pair generated successfully!");
                                } else {
                                    alert("Failed to generate key pair. Please try again.");
                                }
                            }
                        });
                    });
                });
            </script>';
        }

        public function affiliate_section_info() {
            echo 'Configure affiliate link settings below:';
        }

        public function store_url_callback() {
            $options = get_option('pulse_options');
            $store_url = isset($options['store_url']) ? $options['store_url'] : '';
            printf(
                '<input type="text" id="store_url" name="pulse_options[store_url]" value="%s" />',
                esc_attr($store_url)
            );
        }

        public function commission_rate_callback() {
            $options = get_option('pulse_options');
            $commission_rate = isset($options['commission_rate']) ? $options['commission_rate'] : '';
            printf(
                '<input type="number" step="0.01" min="0" max="100" id="commission_rate" name="pulse_options[commission_rate]" value="%s" /> %%',
                esc_attr($commission_rate)
            );
        }

        public function btcpay_url_callback() {
            $options = get_option('pulse_options');
            $btcpay_url = isset($options['btcpay_url']) ? $options['btcpay_url'] : '';
            printf(
                '<input type="text" id="btcpay_url" name="pulse_options[btcpay_url]" value="%s" />',
                esc_attr($btcpay_url)
            );
            echo '<p class="description">Enter the URL of your BTCPay Server instance.</p>';
        }
        public function btcpay_api_key_callback() {
            $options = get_option('pulse_options');
            $btcpay_api_key = isset($options['btcpay_api_key']) ? $options['btcpay_api_key'] : '';
            printf(
                '<input type="text" id="btcpay_api_key" name="pulse_options[btcpay_api_key]" value="%s" />',
                esc_attr($btcpay_api_key)
            );
            
            // Show v2 specific permissions if v2 is detected
            if ($this->btcpay_integration->is_v2() === true) {
                echo '<p class="description">Enter your BTCPay Server API Key. For BTCPay Server 2.0, ensure your API key has all required permissions shown in the notice above.</p>';
            } else {
                echo '<p class="description">Enter your BTCPay Server API Key.</p>';
            }
        }

        public function btcpay_store_id_callback() {
            $options = get_option('pulse_options');
            $btcpay_store_id = isset($options['btcpay_store_id']) ? $options['btcpay_store_id'] : '';
            printf(
                '<input type="text" id="btcpay_store_id" name="pulse_options[btcpay_store_id]" value="%s" />',
                esc_attr($btcpay_store_id)
            );
            echo '<p class="description">Enter your BTCPay Server Store ID.</p>';
        }

        public function auto_approve_claims_callback() {
            $options = get_option('pulse_options');
            $auto_approve = isset($options['auto_approve_claims']) ? $options['auto_approve_claims'] : true;
            echo '<input type="checkbox" id="auto_approve_claims" name="pulse_options[auto_approve_claims]" value="1" ' . checked(true, $auto_approve, false) . '/>';
            echo '<label for="auto_approve_claims">Automatically approve payout claims (If unchecked, payouts will require manual approval in BTCPay Server)</label>';
        }

        public function public_key_callback() {
            $options = get_option('pulse_options');
            $public_key = isset($options['public_key']) ? $options['public_key'] : '';
            echo '<textarea id="public_key" name="pulse_options[public_key]" rows="5" cols="50">' . esc_textarea($public_key) . '</textarea>';
            echo '<p class="description">This should be an RSA public key in PEM format. It typically starts with "-----BEGIN PUBLIC KEY-----".</p>';
        }

        public function private_key_callback() {
            $options = get_option('pulse_options');
            $private_key = isset($options['private_key']) ? $options['private_key'] : '';
            echo '<textarea id="private_key" name="pulse_options[private_key]" rows="5" cols="50">' . esc_textarea($private_key) . '</textarea>';
            echo '<p class="description">This should be an RSA private key in PEM format. It typically starts with "-----BEGIN PRIVATE KEY-----". Keep this secret and secure!</p>';
        }

        public function allow_unencrypted_addresses_callback() {
            $options = get_option('pulse_options');
            $checked = isset($options['allow_unencrypted_addresses']) ? $options['allow_unencrypted_addresses'] : false;
            echo '<input type="checkbox" id="allow_unencrypted_addresses" name="pulse_options[allow_unencrypted_addresses]" value="1" ' . checked(1, $checked, false) . '/>';
            echo '<label for="allow_unencrypted_addresses">Allow unencrypted lightning addresses in affiliate links</label>';
        }
        public function custom_affiliate_mappings_callback() {
            $options = get_option('pulse_options');
            $mappings = isset($options['custom_affiliate_mappings']) ? $options['custom_affiliate_mappings'] : array();
            
            echo '<div id="custom-mappings-container">';
            foreach ($mappings as $custom_string => $lightning_address) {
                $full_url = home_url('?aff=' . urlencode($custom_string));
                echo '<div class="mapping-row">';
                echo '<input type="text" name="pulse_options[custom_affiliate_mappings][custom_string][]" value="' . esc_attr($custom_string) . '" placeholder="Custom String" />';
                echo '<input type="text" name="pulse_options[custom_affiliate_mappings][lightning_address][]" value="' . esc_attr($lightning_address) . '" placeholder="Lightning Address" />';
                echo '<input type="text" value="' . esc_url($full_url) . '" readonly />';
                echo '<button type="button" class="copy-url">Copy URL</button>';
                echo '<button type="button" class="remove-mapping">Remove</button>';
                echo '</div>';
            }
            echo '</div>';
            echo '<button type="button" id="add-mapping">Add Mapping</button>';

            echo '<script>
                jQuery(document).ready(function($) {
                    $("#add-mapping").on("click", function() {
                        var newRow = $("<div class=\'mapping-row\'>" +
                            "<input type=\'text\' name=\'pulse_options[custom_affiliate_mappings][custom_string][]\' placeholder=\'Custom String\' />" +
                            "<input type=\'text\' name=\'pulse_options[custom_affiliate_mappings][lightning_address][]\' placeholder=\'Lightning Address\' />" +
                            "<input type=\'text\' readonly />" +
                            "<button type=\'button\' class=\'copy-url\'>Copy URL</button>" +
                            "<button type=\'button\' class=\'remove-mapping\'>Remove</button>" +
                            "</div>");
                        $("#custom-mappings-container").append(newRow);
                    });

                    $(document).on("click", ".remove-mapping", function() {
                        $(this).parent().remove();
                    });

                    $(document).on("click", ".copy-url", function() {
                        var urlInput = $(this).prev("input[readonly]")[0];
                        urlInput.select();
                        document.execCommand("copy");
                        alert("URL copied to clipboard!");
                    });

                    $(document).on("input", "input[name^=\'pulse_options[custom_affiliate_mappings][custom_string]\']", function() {
                        var customString = $(this).val();
                        var fullUrl = "' . esc_js(home_url('?aff=')) . '" + encodeURIComponent(customString);
                        $(this).parent().find("input[readonly]").val(fullUrl);
                    });
                });
            </script>';

            echo '<style>
                .mapping-row { margin-bottom: 10px; }
                .mapping-row input { margin-right: 5px; }
                .mapping-row input[readonly] { width: 300px; }
                #add-mapping { margin-top: 10px; }
            </style>';
        }

        public function register_options() {
            if (false == get_option('pulse_options')) {
                add_option('pulse_options', $this->get_default_options());
            }
        }

        private function get_default_options() {
            return array(
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
                'nostr_relays' => 'wss://relay.damus.io,wss://nos.lol,wss://relay.nostr.band'
            );
        }
        public function register_shortcodes() {
            add_shortcode('pulse_affiliate_signup', array($this, 'affiliate_signup_shortcode'));
        }

        public function affiliate_signup_shortcode() {
            $options = get_option('pulse_options');
            $public_key = isset($options['public_key']) ? $options['public_key'] : '';
            $enable_nostr = isset($options['enable_nostr']) ? $options['enable_nostr'] : false;

            ob_start();
            ?>
            <form id="pulse-affiliate-signup-form">
                <div class="input-type-selector">
                    <label><input type="radio" name="input_type" value="lightning" checked> Lightning Address</label>
                    <?php if ($enable_nostr): ?>
                    <label><input type="radio" name="input_type" value="nostr"> Nostr npub</label>
                    <?php endif; ?>
                </div>
                
                <div id="lightning-input-section">
                    <label for="pulse-lightning-address">Lightning Address:</label>
                    <input type="text" id="pulse-lightning-address" name="lightning_address" placeholder="you@lightning.address">
                </div>
                
                <?php if ($enable_nostr): ?>
                <div id="nostr-input-section" style="display:none;">
                    <label for="pulse-nostr-npub">Nostr npub:</label>
                    <input type="text" id="pulse-nostr-npub" name="nostr_npub" placeholder="npub1...">
                </div>
                <?php endif; ?>
                
                <button type="submit">Generate Affiliate Link</button>
            </form>
            <div id="pulse-result" style="display:none;"></div>
            <div id="pulse-public-key-info">
                <h3>Public Encryption Key:</h3>
                <pre><?php echo esc_html($public_key); ?></pre>
                <p>You can use this public key to independently verify your encrypted affiliate link.</p>
            </div>
            <script>
            jQuery(document).ready(function($) {
                // Toggle input fields based on selected type
                $('input[name="input_type"]').change(function() {
                    if ($(this).val() === 'lightning') {
                        $('#lightning-input-section').show();
                        $('#nostr-input-section').hide();
                    } else {
                        $('#lightning-input-section').hide();
                        $('#nostr-input-section').show();
                    }
                });
                
                $('#pulse-affiliate-signup-form').on('submit', function(e) {
                    e.preventDefault();
                    var inputType = $('input[name="input_type"]:checked').val();
                    var inputValue = '';
                    
                    if (inputType === 'lightning') {
                        inputValue = $('#pulse-lightning-address').val();
                    } else {
                        inputValue = $('#pulse-nostr-npub').val();
                    }
                    
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'pulse_affiliate_signup',
                            input_type: inputType,
                            input_value: inputValue,
                            nonce: '<?php echo wp_create_nonce('pulse_affiliate_signup'); ?>'
                        },
                        success: function(response) {
                            console.log('Received response:', response);
                            if (response.success) {
                                var resultHtml = '<h3>Your Affiliate Links:</h3><ul>';
                                if (response.data.lightning_link) {
                                    resultHtml += '<li>Lightning Address Link: ' + response.data.lightning_link + '</li>';
                                }
                                if (response.data.custom_link) {
                                    resultHtml += '<li>Custom Link: ' + response.data.custom_link + '</li>';
                                }
                                if (response.data.unencrypted_link) {
                                    resultHtml += '<li>Unencrypted Link: ' + response.data.unencrypted_link + '</li>';
                                }
                                if (response.data.encrypted_link) {
                                    resultHtml += '<li>Encrypted Link: ' + response.data.encrypted_link + '</li>';
                                }
                                resultHtml += '</ul>';
                                $('#pulse-result').html(resultHtml).show();
                            } else {
                                alert('Error: ' + response.data);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX Error:', status, error);
                            alert('An error occurred while processing your request. Please try again later.');
                        }
                    });
                });
            });
            </script>
            <?php
            return ob_get_clean();
        }
        
        public function process_affiliate_signup() {
            error_log('Pulse: Starting process_affiliate_signup');
            
            if (!check_ajax_referer('pulse_affiliate_signup', 'nonce', false)) {
                error_log('Pulse: Nonce check failed');
                wp_send_json_error('Security check failed');
                return;
            }

            if (!isset($_POST['input_type']) || !isset($_POST['input_value'])) {
                error_log('Pulse: Input data not set in POST data');
                wp_send_json_error('No input provided');
                return;
            }

            $input_type = sanitize_text_field($_POST['input_type']);
            $input_value = sanitize_text_field($_POST['input_value']);
            
            error_log('Pulse: Processing affiliate signup for input type: ' . $input_type . ', value: ' . $input_value);

            if (empty($input_value)) {
                error_log('Pulse: Input is empty');
                wp_send_json_error('No input provided');
                return;
            }
            
            $options = get_option('pulse_options');
            $allow_unencrypted = isset($options['allow_unencrypted_addresses']) ? $options['allow_unencrypted_addresses'] : false;
            $custom_mappings = isset($options['custom_affiliate_mappings']) ? $options['custom_affiliate_mappings'] : array();
            $enable_nostr = isset($options['enable_nostr']) ? $options['enable_nostr'] : false;

            $response = array();
            $lightning_address = '';

            if ($input_type === 'lightning') {
                if (filter_var($input_value, FILTER_VALIDATE_EMAIL)) {
                    $lightning_address = $input_value;
                    error_log('Pulse: Valid Lightning address entered: ' . $lightning_address);
                } else {
                    error_log('Pulse: Invalid Lightning address format: ' . $input_value);
                    wp_send_json_error('Invalid Lightning address format. Must be an email address.');
                    return;
                }
            } elseif ($input_type === 'nostr' && $enable_nostr) {
                if (\Pulse\Nostr_Handler::validate_npub($input_value)) {
                    $lightning_address = \Pulse\Nostr_Handler::get_lightning_address_from_npub($input_value);
                    if (!$lightning_address) {
                        error_log('Pulse: Could not find Lightning address for npub: ' . $input_value);
                        wp_send_json_error('Could not find a Lightning address associated with this npub. Please make sure your Nostr profile has a Lightning address set.');
                        return;
                    }
                    error_log('Pulse: Found Lightning address for npub: ' . $lightning_address);
                } else {
                    error_log('Pulse: Invalid Nostr npub format: ' . $input_value);
                    wp_send_json_error('Invalid Nostr npub format. It should start with "npub1".');
                    return;
                }
            } else {
                error_log('Pulse: Invalid input type: ' . $input_type);
                wp_send_json_error('Invalid input type.');
                return;
            }

            // Check if there's a custom mapping for this lightning address
            $custom_string = array_search($lightning_address, $custom_mappings);
            if ($custom_string !== false) {
                $response['custom_link'] = home_url('?aff=' . urlencode($custom_string));
                error_log('Pulse: Custom mapping found for ' . $lightning_address);
            }

            if ($allow_unencrypted) {
                $response['unencrypted_link'] = home_url('?aff=' . urlencode($lightning_address));
                error_log('Pulse: Unencrypted link generated');
            }

            // Generate compact encrypted link
            $response['encrypted_link'] = $this->generate_affiliate_link($lightning_address);
            error_log('Pulse: Compact encrypted link generated');

            if (empty($response)) {
                error_log('Pulse: Unable to generate any affiliate links');
                wp_send_json_error('Unable to generate affiliate link. Please contact the administrator.');
            } else {
                error_log('Pulse: Successfully generated affiliate link(s): ' . print_r($response, true));
                wp_send_json_success($response);
            }
        }
        
        public function capture_affiliate() {
            if (isset($_GET['aff'])) {
                $encoded_affiliate = sanitize_text_field($_GET['aff']);
                setcookie('pulse_affiliate', $encoded_affiliate, time() + (86400 * 30), "/"); // 30 days expiry
            }
        }

        public function add_affiliate_to_order($order_id, $posted_data, $order) {
            if (isset($_COOKIE['pulse_affiliate'])) {
                $encoded_affiliate = sanitize_text_field($_COOKIE['pulse_affiliate']);
                $order->update_meta_data('pulse_affiliate', $encoded_affiliate);
                $order->save();
            }
        }

        public function generate_affiliate_link($lightning_address) {
            $padded = str_pad($lightning_address, 64, "\0"); // Pad to fixed length
            $encrypted = openssl_encrypt($padded, 'aes-128-ecb', hex2bin($this->encryption_key), OPENSSL_RAW_DATA);
            $encoded = rtrim(strtr(base64_encode($encrypted), '+/', '-_'), '=');
            return home_url('?aff=' . $encoded);
        }

        public function decode_affiliate_link($encoded) {
            $encrypted = base64_decode(strtr($encoded, '-_', '+/') . '==');
            $decrypted = openssl_decrypt($encrypted, 'aes-128-ecb', hex2bin($this->encryption_key), OPENSSL_RAW_DATA);
            return rtrim($decrypted, "\0");
        }

        public function process_affiliate_payout($order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                error_log('Pulse: Invalid order ID ' . $order_id);
                return false;
            }

            $encoded_affiliate = $order->get_meta('pulse_affiliate');
            if (!$encoded_affiliate) {
                error_log('Pulse: No affiliate associated with order ' . $order_id);
                return false;
            }

            // Get plugin options
            $options = get_option('pulse_options');
            $custom_mappings = isset($options['custom_affiliate_mappings']) ? $options['custom_affiliate_mappings'] : array();
            $enable_nostr = isset($options['enable_nostr']) ? $options['enable_nostr'] : false;
            
            // Check if this is a custom affiliate mapping
            if (isset($custom_mappings[$encoded_affiliate])) {
                $lightning_address = $custom_mappings[$encoded_affiliate];
            } elseif ($enable_nostr && strpos($encoded_affiliate, 'npub1') === 0) {
                // This is a Nostr npub
                $lightning_address = \Pulse\Nostr_Handler::get_lightning_address_from_npub($encoded_affiliate);
                if (!$lightning_address) {
                    error_log('Pulse: Failed to get Lightning address from npub ' . $encoded_affiliate . ' for order ' . $order_id);
                    $order->add_order_note(__('Failed to process affiliate payout. Could not retrieve Lightning address from npub.', 'pulse'));
                    return false;
                }
            } else {
                // Not a custom mapping or npub, try to decode it
                $lightning_address = $this->decode_affiliate_link($encoded_affiliate);
            }

            if (!$lightning_address) {
                error_log('Pulse: Failed to decode affiliate for order ' . $order_id);
                return false;
            }

            // Get commission rate from plugin settings
            $options = get_option('pulse_options');
            $commission_rate = isset($options['commission_rate']) ? floatval($options['commission_rate']) : 10; // Default to 10%

            // Calculate payout amount
            $order_subtotal = $order->get_subtotal();
            $payout_amount = $order_subtotal * ($commission_rate / 100);
            $currency = $order->get_currency();

            // Process payout via BTCPay Server with version awareness
            $payout_id = $this->btcpay_integration->create_payout($lightning_address, $payout_amount, $currency);

            if ($payout_id) {
                $order->add_order_note(sprintf(__('Affiliate payout processed. Payout ID: %s, Amount: %s %s', 'pulse'), $payout_id, $payout_amount, $currency));
                $order->update_meta_data('pulse_affiliate_payout_id', $payout_id);
                $order->save();
                error_log('Pulse: Successfully processed payout for order ' . $order_id . '. Payout ID: ' . $payout_id);
                return true;
            } else {
                error_log('Pulse: Failed to process affiliate payout for order ' . $order_id);
                $order->add_order_note(__('Failed to process affiliate payout. Please check the logs for more information.', 'pulse'));
                return false;
            }
        }

        public function create_affiliate_signup_page() {
            $page_title = 'Affiliate Signup';
            $page_content = '[pulse_affiliate_signup]';
            $page_check = get_page_by_title($page_title);

            if (!$page_check) {
                $page = array(
                    'post_type' => 'page',
                    'post_title' => $page_title,
                    'post_content' => $page_content,
                    'post_status' => 'publish',
                    'post_author' => 1,
                );
                $page_id = wp_insert_post($page);

                if (!is_wp_error($page_id)) {
                    update_option('pulse_affiliate_signup_page_id', $page_id);
                }
            }
        }

        public static function generate_keys() {
            check_ajax_referer('pulse_generate_keys', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error('You do not have permission to perform this action.');
                return;
            }

            $config = array(
                "digest_alg" => "sha256",
                "private_key_bits" => 2048,
                "private_key_type" => OPENSSL_KEYTYPE_RSA,
            );

            $res = openssl_pkey_new($config);

            if ($res === false) {
                wp_send_json_error('Failed to generate new key pair');
                return;
            }

            openssl_pkey_export($res, $privKey);

            $pubKey = openssl_pkey_get_details($res);
            $pubKey = $pubKey["key"];

            wp_send_json_success(array(
                'public_key' => $pubKey,
                'private_key' => $privKey
            ));
        }
    

        /**
         * Initialize the plugin
         */
        public static function init() {
            $plugin = new self();
            $plugin->run();

            // Add AJAX action for key generation
            add_action('wp_ajax_pulse_generate_keys', array($plugin, 'generate_keys'));

            // Add AJAX action for affiliate signup
            add_action('wp_ajax_pulse_affiliate_signup', array($plugin, 'process_affiliate_signup'));
            add_action('wp_ajax_nopriv_pulse_affiliate_signup', array($plugin, 'process_affiliate_signup'));
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