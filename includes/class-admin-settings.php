<?php
/**
 * Pulse Admin Settings
 *
 * @package Pulse
 */

namespace Pulse;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Admin_Settings {
    private $options;

    public function __construct() {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
        add_action('wp_ajax_pulse_test_nostr_api', array($this, 'test_nostr_api_callback'));
        add_action('wp_ajax_pulse_clear_nostr_api_cache', array($this, 'clear_nostr_api_cache_callback'));
    }

    public function add_plugin_page() {
        add_options_page(
            __('Pulse Settings', 'pulse'),
            __('Pulse', 'pulse'),
            'manage_options',
            'pulse-settings',
            array($this, 'create_admin_page')
        );
    }

    public function create_admin_page() {
        $this->options = get_option('pulse_options');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
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
            __('General Settings', 'pulse'),
            array($this, 'print_general_section_info'),
            'pulse-settings'
        );

        add_settings_field(
            'store_url', 
            __('Store URL', 'pulse'), 
            array($this, 'store_url_callback'), 
            'pulse-settings', 
            'pulse_general_section'
        );

        add_settings_field(
            'commission_rate', 
            __('Commission Rate (%)', 'pulse'), 
            array($this, 'commission_rate_callback'), 
            'pulse-settings', 
            'pulse_general_section'
        );
        
        add_settings_field(
            'allow_unencrypted_addresses', 
            __('Allow Unencrypted Addresses', 'pulse'), 
            array($this, 'allow_unencrypted_callback'), 
            'pulse-settings', 
            'pulse_general_section'
        );
        
        add_settings_field(
            'auto_approve_claims', 
            __('Auto-approve Claims', 'pulse'), 
            array($this, 'auto_approve_claims_callback'), 
            'pulse-settings', 
            'pulse_general_section'
        );

        add_settings_section(
            'pulse_btcpay_section',
            __('BTCPay Server Settings', 'pulse'),
            array($this, 'print_btcpay_section_info'),
            'pulse-settings'
        );

        add_settings_field(
            'btcpay_url', 
            __('BTCPay Server URL', 'pulse'), 
            array($this, 'btcpay_url_callback'), 
            'pulse-settings', 
            'pulse_btcpay_section'
        );

        add_settings_field(
            'btcpay_api_key', 
            __('BTCPay Server API Key', 'pulse'), 
            array($this, 'btcpay_api_key_callback'), 
            'pulse-settings', 
            'pulse_btcpay_section'
        );
        
        add_settings_field(
            'btcpay_store_id', 
            __('BTCPay Store ID', 'pulse'), 
            array($this, 'btcpay_store_id_callback'), 
            'pulse-settings', 
            'pulse_btcpay_section'
        );

        add_settings_section(
            'pulse_encryption_section',
            __('Encryption Settings', 'pulse'),
            array($this, 'print_encryption_section_info'),
            'pulse-settings'
        );

        add_settings_field(
            'public_key', 
            __('Public Key', 'pulse'), 
            array($this, 'public_key_callback'), 
            'pulse-settings', 
            'pulse_encryption_section'
        );

        add_settings_field(
            'private_key', 
            __('Private Key', 'pulse'), 
            array($this, 'private_key_callback'), 
            'pulse-settings', 
            'pulse_encryption_section'
        );
        
        add_settings_section(
            'pulse_nostr_section',
            __('Nostr Integration', 'pulse'),
            array($this, 'print_nostr_section_info'),
            'pulse-settings'
        );
        
        add_settings_field(
            'enable_nostr', 
            __('Enable Nostr Integration', 'pulse'), 
            array($this, 'enable_nostr_callback'), 
            'pulse-settings', 
            'pulse_nostr_section'
        );
        
//         add_settings_field(
//             'nostr_relays', 
//             __('Nostr Relays', 'pulse'), 
//             array($this, 'nostr_relays_callback'), 
//             'pulse-settings', 
//             'pulse_nostr_section'
//         );
//         
        // Nostr Profile API Settings
        add_settings_section(
            'pulse_nostr_profile_api_section',
            __('Nostr Profile API Settings', 'pulse'),
            array($this, 'print_nostr_profile_api_section_info'),
            'pulse-settings'
        );
        
        add_settings_field(
            'nostr_profile_api_url', 
            __('Nostr Profile API URL', 'pulse'), 
            array($this, 'nostr_profile_api_url_callback'), 
            'pulse-settings', 
            'pulse_nostr_profile_api_section'
        );
        
        add_settings_field(
            'nostr_profile_api_key', 
            __('Nostr Profile API Key', 'pulse'), 
            array($this, 'nostr_profile_api_key_callback'), 
            'pulse-settings', 
            'pulse_nostr_profile_api_section'
        );
        
        add_settings_field(
            'nostr_profile_api_cache_duration', 
            __('Cache Duration (hours)', 'pulse'), 
            array($this, 'nostr_profile_api_cache_duration_callback'), 
            'pulse-settings', 
            'pulse_nostr_profile_api_section'
        );
        
        add_settings_field(
            'nostr_profile_api_test', 
            __('Test Connection', 'pulse'), 
            array($this, 'nostr_profile_api_test_callback'), 
            'pulse-settings', 
            'pulse_nostr_profile_api_section'
        );
        
        add_settings_field(
            'nostr_profile_api_clear_cache', 
            __('Cache Management', 'pulse'), 
            array($this, 'nostr_profile_api_clear_cache_callback'), 
            'pulse-settings', 
            'pulse_nostr_profile_api_section'
        );
        
        add_settings_section(
            'pulse_affiliate_mappings_section',
            __('Custom Affiliate Mappings', 'pulse'),
            array($this, 'print_affiliate_mappings_section_info'),
            'pulse-settings'
        );
        
        add_settings_field(
            'custom_affiliate_mappings', 
            __('Custom Mappings', 'pulse'), 
            array($this, 'custom_affiliate_mappings_callback'), 
            'pulse-settings', 
            'pulse_affiliate_mappings_section'
        );
    }

    public function sanitize($input) {
        $new_input = array();
        
        // Preserve existing values that might not be submitted in this form
        $current_options = get_option('pulse_options', array());
        
        // General settings
        if(isset($input['store_url']))
            $new_input['store_url'] = esc_url_raw($input['store_url']);
        
        if(isset($input['commission_rate']))
            $new_input['commission_rate'] = floatval($input['commission_rate']);
        
        if(isset($input['allow_unencrypted_addresses']))
            $new_input['allow_unencrypted_addresses'] = (bool) $input['allow_unencrypted_addresses'];
        else
            $new_input['allow_unencrypted_addresses'] = false;
            
        if(isset($input['auto_approve_claims']))
            $new_input['auto_approve_claims'] = (bool) $input['auto_approve_claims'];
        else
            $new_input['auto_approve_claims'] = false;
        
        // BTCPay settings
        if(isset($input['btcpay_url']))
            $new_input['btcpay_url'] = esc_url_raw($input['btcpay_url']);
        
        if(isset($input['btcpay_api_key']))
            $new_input['btcpay_api_key'] = sanitize_text_field($input['btcpay_api_key']);
            
        if(isset($input['btcpay_store_id']))
            $new_input['btcpay_store_id'] = sanitize_text_field($input['btcpay_store_id']);
        
        // Encryption settings
        if(isset($input['public_key']))
            $new_input['public_key'] = sanitize_textarea_field($input['public_key']);
        
        if(isset($input['private_key']))
            $new_input['private_key'] = sanitize_textarea_field($input['private_key']);
        
        // Nostr settings
        if(isset($input['enable_nostr']))
            $new_input['enable_nostr'] = (bool) $input['enable_nostr'];
        else
            $new_input['enable_nostr'] = false;
            
//         if(isset($input['nostr_relays']))
//             $new_input['nostr_relays'] = sanitize_text_field($input['nostr_relays']);
//             
        // Nostr Profile API settings
        if(isset($input['nostr_profile_api_url']))
            $new_input['nostr_profile_api_url'] = esc_url_raw($input['nostr_profile_api_url']);
            
        if(isset($input['nostr_profile_api_key']))
            $new_input['nostr_profile_api_key'] = sanitize_text_field($input['nostr_profile_api_key']);
            
        if(isset($input['nostr_profile_api_cache_duration']))
            $new_input['nostr_profile_api_cache_duration'] = intval($input['nostr_profile_api_cache_duration']);
        else
            $new_input['nostr_profile_api_cache_duration'] = 1; // Default to 1 hour
        
        // Custom mappings - Handle special case for the keys/values arrays
        if(isset($input['custom_affiliate_mappings_keys']) && isset($input['custom_affiliate_mappings_values']) &&
           is_array($input['custom_affiliate_mappings_keys']) && is_array($input['custom_affiliate_mappings_values'])) {
            
            $mappings = array();
            $keys = $input['custom_affiliate_mappings_keys'];
            $values = $input['custom_affiliate_mappings_values'];
            
            $count = min(count($keys), count($values));
            
            for($i = 0; $i < $count; $i++) {
                $key = trim(sanitize_text_field($keys[$i]));
                $value = trim(sanitize_text_field($values[$i]));
                
                if(!empty($key) && !empty($value)) {
                    $mappings[$key] = $value;
                }
            }
            
            $new_input['custom_affiliate_mappings'] = $mappings;
        } else {
            // Preserve existing mappings if none submitted
            $new_input['custom_affiliate_mappings'] = isset($current_options['custom_affiliate_mappings']) ? 
                $current_options['custom_affiliate_mappings'] : array();
        }
        
        // Clear Nostr Profile API cache when settings are changed
        if (isset($input['nostr_profile_api_url']) || isset($input['nostr_profile_api_key'])) {
            \Pulse\Nostr_Profile_API::clear_cache();
        }
        
        return $new_input;
    }

    public function print_general_section_info() {
        esc_html_e('Enter your general settings below:', 'pulse');
    }

    public function print_btcpay_section_info() {
        esc_html_e('Enter your BTCPay Server settings below:', 'pulse');
    }

    public function print_encryption_section_info() {
        esc_html_e('Enter your encryption keys below:', 'pulse');
    }
    
    public function print_nostr_section_info() {
        esc_html_e('Configure Nostr integration settings:', 'pulse');
    }
    
    public function print_nostr_profile_api_section_info() {
        esc_html_e('Configure Nostr Profile API service to lookup Lightning addresses for Nostr npubs:', 'pulse');
    }
    
    public function print_affiliate_mappings_section_info() {
        esc_html_e('Create custom mappings for affiliate links (short code to lightning address):', 'pulse');
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
    
    public function allow_unencrypted_callback() {
        printf(
            '<input type="checkbox" id="allow_unencrypted_addresses" name="pulse_options[allow_unencrypted_addresses]" value="1" %s />
            <label for="allow_unencrypted_addresses">%s</label>',
            isset($this->options['allow_unencrypted_addresses']) && $this->options['allow_unencrypted_addresses'] ? 'checked' : '',
            __('Allow unencrypted Lightning addresses in affiliate links', 'pulse')
        );
    }
    
    public function auto_approve_claims_callback() {
        printf(
            '<input type="checkbox" id="auto_approve_claims" name="pulse_options[auto_approve_claims]" value="1" %s />
            <label for="auto_approve_claims">%s</label>',
            isset($this->options['auto_approve_claims']) && $this->options['auto_approve_claims'] ? 'checked' : '',
            __('Automatically approve BTCPay Server claims', 'pulse')
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
    
    public function btcpay_store_id_callback() {
        printf(
            '<input type="text" id="btcpay_store_id" name="pulse_options[btcpay_store_id]" value="%s" class="regular-text" />',
            isset($this->options['btcpay_store_id']) ? esc_attr($this->options['btcpay_store_id']) : ''
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
    
    public function enable_nostr_callback() {
        printf(
            '<input type="checkbox" id="enable_nostr" name="pulse_options[enable_nostr]" value="1" %s />
            <label for="enable_nostr">%s</label>',
            isset($this->options['enable_nostr']) && $this->options['enable_nostr'] ? 'checked' : '',
            __('Enable Nostr npub support for affiliate links', 'pulse')
        );
    }
//     
//     public function nostr_relays_callback() {
//         printf(
//             '<input type="text" id="nostr_relays" name="pulse_options[nostr_relays]" value="%s" class="large-text" />
//             <p class="description">%s</p>',
//             isset($this->options['nostr_relays']) ? esc_attr($this->options['nostr_relays']) : '',
//             __('Comma-separated list of Nostr relay URLs (e.g., wss://relay.damus.io,wss://nos.lol)', 'pulse')
//         );
//     }
    
    /**
     * Callback for the Nostr Profile API URL field
     */
    public function nostr_profile_api_url_callback() {
        printf(
            '<input type="url" id="nostr_profile_api_url" name="pulse_options[nostr_profile_api_url]" value="%s" class="regular-text" placeholder="https://api.example.com" />
            <p class="description">%s</p>',
            isset($this->options['nostr_profile_api_url']) ? esc_attr($this->options['nostr_profile_api_url']) : '',
            __('The URL of the Nostr Profile API service (e.g., https://api.example.com)', 'pulse')
        );
    }
    
    /**
     * Callback for the Nostr Profile API Key field
     */
    public function nostr_profile_api_key_callback() {
        printf(
            '<input type="text" id="nostr_profile_api_key" name="pulse_options[nostr_profile_api_key]" value="%s" class="regular-text" />
            <p class="description">%s</p>',
            isset($this->options['nostr_profile_api_key']) ? esc_attr($this->options['nostr_profile_api_key']) : '',
            __('Your API key for authentication with the Nostr Profile API service', 'pulse')
        );
    }
    
    /**
     * Callback for the Nostr Profile API Cache Duration field
     */
    public function nostr_profile_api_cache_duration_callback() {
        printf(
            '<input type="number" id="nostr_profile_api_cache_duration" name="pulse_options[nostr_profile_api_cache_duration]" value="%s" class="small-text" min="1" max="24" step="1" />
            <p class="description">%s</p>',
            isset($this->options['nostr_profile_api_cache_duration']) ? esc_attr($this->options['nostr_profile_api_cache_duration']) : '1',
            __('How long to cache API responses in hours (1-24)', 'pulse')
        );
    }
    
    /**
     * Callback for the Nostr Profile API Test Connection button
     */
    public function nostr_profile_api_test_callback() {
        ?>
        <button type="button" id="nostr-api-test-btn" class="button"><?php _e('Test Connection', 'pulse'); ?></button>
        <span id="nostr-api-test-result" style="margin-left: 10px; display: none;"></span>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#nostr-api-test-btn').on('click', function() {
                var btn = $(this);
                var result = $('#nostr-api-test-result');
                
                btn.prop('disabled', true).text('<?php echo esc_js(__('Testing...', 'pulse')); ?>');
                result.hide();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'pulse_test_nostr_api',
                        nonce: '<?php echo wp_create_nonce('pulse_test_nostr_api'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            result.removeClass('notice-error').addClass('notice-success').text(response.data.message).show();
                        } else {
                            result.removeClass('notice-success').addClass('notice-error').text(response.data.message).show();
                        }
                    },
                    error: function() {
                        result.removeClass('notice-success').addClass('notice-error').text('<?php echo esc_js(__('Connection test failed', 'pulse')); ?>').show();
                    },
                    complete: function() {
                        btn.prop('disabled', false).text('<?php echo esc_js(__('Test Connection', 'pulse')); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Callback for the Nostr Profile API Clear Cache button
     */
    public function nostr_profile_api_clear_cache_callback() {
        ?>
        <button type="button" id="nostr-api-clear-cache-btn" class="button"><?php _e('Clear Cache', 'pulse'); ?></button>
        <span id="nostr-api-clear-cache-result" style="margin-left: 10px; display: none;"></span>
        <p class="description"><?php _e('Clear the cached API responses to fetch fresh data on the next request', 'pulse'); ?></p>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#nostr-api-clear-cache-btn').on('click', function() {
                var btn = $(this);
                var result = $('#nostr-api-clear-cache-result');
                
                btn.prop('disabled', true).text('<?php echo esc_js(__('Clearing...', 'pulse')); ?>');
                result.hide();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'pulse_clear_nostr_api_cache',
                        nonce: '<?php echo wp_create_nonce('pulse_clear_nostr_api_cache'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            result.removeClass('notice-error').addClass('notice-success').text(response.data.message).show();
                        } else {
                            result.removeClass('notice-success').addClass('notice-error').text(response.data.message).show();
                        }
                    },
                    error: function() {
                        result.removeClass('notice-success').addClass('notice-error').text('<?php echo esc_js(__('Failed to clear cache', 'pulse')); ?>').show();
                    },
                    complete: function() {
                        btn.prop('disabled', false).text('<?php echo esc_js(__('Clear Cache', 'pulse')); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX callback for testing the Nostr Profile API connection
     */
    public function test_nostr_api_callback() {
        // Check nonce
        if (!check_ajax_referer('pulse_test_nostr_api', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'pulse')]);
            return;
        }
        
        // Check user permission
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'pulse')]);
            return;
        }
        
        // Test the connection
        $result = \Pulse\Nostr_Profile_API::test_connection();
        
        if ($result['success']) {
            wp_send_json_success(['message' => $result['message']]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }
    
    /**
     * AJAX callback for clearing the Nostr Profile API cache
     */
    public function clear_nostr_api_cache_callback() {
        // Check nonce
        if (!check_ajax_referer('pulse_clear_nostr_api_cache', 'nonce', false)) {
            wp_send_json_error(['message' => __('Security check failed', 'pulse')]);
            return;
        }
        
        // Check user permission
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'pulse')]);
            return;
        }
        
        // Clear the cache
        \Pulse\Nostr_Profile_API::clear_cache();
        
        wp_send_json_success(['message' => __('Cache cleared successfully', 'pulse')]);
    }
    
    public function custom_affiliate_mappings_callback() {
        $mappings = isset($this->options['custom_affiliate_mappings']) ? $this->options['custom_affiliate_mappings'] : array();
        
        // Always display at least one empty row for new mappings
        if (empty($mappings)) {
            $mappings = array('' => '');
        }
        
        ?>
        <div id="custom-mappings-container">
            <table class="widefat" style="width: auto;">
                <thead>
                    <tr>
                        <th><?php _e('Custom Code', 'pulse'); ?></th>
                        <th><?php _e('Lightning Address', 'pulse'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mappings as $code => $address): ?>
                    <tr class="mapping-row">
                        <td><input type="text" name="pulse_options[custom_affiliate_mappings_keys][]" value="<?php echo esc_attr($code); ?>" class="regular-text" placeholder="<?php _e('custom-code', 'pulse'); ?>" /></td>
                        <td><input type="text" name="pulse_options[custom_affiliate_mappings_values][]" value="<?php echo esc_attr($address); ?>" class="regular-text" placeholder="<?php _e('user@lightning.address', 'pulse'); ?>" /></td>
                        <td><button type="button" class="button remove-mapping"><?php _e('Remove', 'pulse'); ?></button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><button type="button" id="add-mapping" class="button"><?php _e('Add Mapping', 'pulse'); ?></button></p>
        </div>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Add new mapping row
            $('#add-mapping').on('click', function() {
                var newRow = '<tr class="mapping-row">' +
                    '<td><input type="text" name="pulse_options[custom_affiliate_mappings_keys][]" value="" class="regular-text" placeholder="<?php echo esc_js(__('custom-code', 'pulse')); ?>" /></td>' +
                    '<td><input type="text" name="pulse_options[custom_affiliate_mappings_values][]" value="" class="regular-text" placeholder="<?php echo esc_js(__('user@lightning.address', 'pulse')); ?>" /></td>' +
                    '<td><button type="button" class="button remove-mapping"><?php echo esc_js(__('Remove', 'pulse')); ?></button></td>' +
                    '</tr>';
                $('#custom-mappings-container tbody').append(newRow);
            });
            
            // Remove mapping (delegated event)
            $('#custom-mappings-container').on('click', '.remove-mapping', function() {
                $(this).closest('tr').remove();
            });
        });
        </script>
        <?php
    }
}
// The class is instantiated by the main Pulse class - no direct instantiation here