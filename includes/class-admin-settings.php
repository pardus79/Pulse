<?php
/**
 * Pulse Admin Settings
 *
 * @package Pulse
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Pulse_Admin_Settings {
    private $options;

    public function __construct() {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
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
        $this->options = get_option('pulse_options');
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
            array($this, 'print_general_section_info'),
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
            array($this, 'print_btcpay_section_info'),
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

        add_settings_section(
            'pulse_encryption_section',
            'Encryption Settings',
            array($this, 'print_encryption_section_info'),
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
    }

    public function sanitize($input) {
        $new_input = array();
        
        if(isset($input['store_url']))
            $new_input['store_url'] = esc_url_raw($input['store_url']);
        
        if(isset($input['commission_rate']))
            $new_input['commission_rate'] = floatval($input['commission_rate']);
        
        if(isset($input['btcpay_url']))
            $new_input['btcpay_url'] = esc_url_raw($input['btcpay_url']);
        
        if(isset($input['btcpay_api_key']))
            $new_input['btcpay_api_key'] = sanitize_text_field($input['btcpay_api_key']);
        
        if(isset($input['public_key']))
            $new_input['public_key'] = sanitize_textarea_field($input['public_key']);
        
        if(isset($input['private_key']))
            $new_input['private_key'] = sanitize_textarea_field($input['private_key']);
        
        return $new_input;
    }

    public function print_general_section_info() {
        print 'Enter your general settings below:';
    }

    public function print_btcpay_section_info() {
        print 'Enter your BTCPay Server settings below:';
    }

    public function print_encryption_section_info() {
        print 'Enter your encryption keys below:';
    }

    public function store_url_callback() {
        printf(
            '<input type="text" id="store_url" name="pulse_options[store_url]" value="%s" class="regular-text" />',
            isset($this->options['store_url']) ? esc_attr($this->options['store_url']) : ''
        );
    }

    public function commission_rate_callback() {
        printf(
            '<input type="number" id="commission_rate" name="pulse_options[commission_rate]" value="%s" class="small-text" step="0.1" min="0" max="100" /> %%',
            isset($this->options['commission_rate']) ? esc_attr($this->options['commission_rate']) : ''
        );
    }

    public function btcpay_url_callback() {
        printf(
            '<input type="text" id="btcpay_url" name="pulse_options[btcpay_url]" value="%s" class="regular-text" />',
            isset($this->options['btcpay_url']) ? esc_attr($this->options['btcpay_url']) : ''
        );
    }

    public function btcpay_api_key_callback() {
        printf(
            '<input type="text" id="btcpay_api_key" name="pulse_options[btcpay_api_key]" value="%s" class="regular-text" />',
            isset($this->options['btcpay_api_key']) ? esc_attr($this->options['btcpay_api_key']) : ''
        );
    }

    public function public_key_callback() {
        printf(
            '<textarea id="public_key" name="pulse_options[public_key]" rows="5" cols="50">%s</textarea>',
            isset($this->options['public_key']) ? esc_textarea($this->options['public_key']) : ''
        );
    }

    public function private_key_callback() {
        printf(
            '<textarea id="private_key" name="pulse_options[private_key]" rows="5" cols="50">%s</textarea>',
            isset($this->options['private_key']) ? esc_textarea($this->options['private_key']) : ''
        );
    }
}

if (is_admin())
    $pulse_settings = new Pulse_Admin_Settings();