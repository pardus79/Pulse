<?php
/**
 * Plugin Name: Pulse
 * Plugin URI: https://github.com/pardus79/Pulse
 * Description: Automated affiliate payouts for WooCommerce using Bitcoin Lightning Network and BTCPayServer
 * Version: 0.01
 * Author: Your Btcpins
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

// Define plugin constants
define('PULSE_VERSION', '1.0.0');
define('PULSE_PATH', plugin_dir_path(__FILE__));
define('PULSE_URL', plugin_dir_url(__FILE__));

/**
 * The code that runs during plugin activation.
 */
function activate_pulse() {
    // Activation code here
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_pulse() {
    // Deactivation code here
}

register_activation_hook(__FILE__, 'activate_pulse');
register_deactivation_hook(__FILE__, 'deactivate_pulse');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-pulse.php';

/**
 * Begins execution of the plugin.
 */
function run_pulse() {
    // Check if WooCommerce is active
    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        $plugin = new Pulse();
        $plugin->run();
    } else {
        add_action('admin_notices', 'pulse_woocommerce_missing_notice');
    }
}

/**
 * Notice for missing WooCommerce
 */
function pulse_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p><?php _e('Pulse requires WooCommerce to be installed and active.', 'pulse'); ?></p>
    </div>
    <?php
}

run_pulse();