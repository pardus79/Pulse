<?php
/**
 * Shortcode Handler
 *
 * @package Pulse
 */

namespace Pulse;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Shortcode_Handler {
    private $affiliate_link_handler;
    
    public function __construct($affiliate_link_handler) {
        $this->affiliate_link_handler = $affiliate_link_handler;
        
        add_action('init', array($this, 'register_shortcodes'));
        add_action('wp_ajax_pulse_affiliate_signup', array($this, 'process_affiliate_signup'));
        add_action('wp_ajax_nopriv_pulse_affiliate_signup', array($this, 'process_affiliate_signup'));
    }
    
    /**
     * Register shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('pulse_affiliate_signup', array($this, 'affiliate_signup_shortcode'));
        add_shortcode('nostr_lightning', array($this, 'nostr_lightning_shortcode'));
        add_shortcode('nostr_lightning_link', array($this, 'nostr_lightning_link_shortcode'));
    }
    
    /**
     * Display Lightning address for a Nostr npub
     *
     * @param array $atts Shortcode attributes
     * @return string The Lightning address or error message
     */
    public function nostr_lightning_shortcode($atts) {
        $atts = shortcode_atts(
            array(
                'npub' => '',
            ),
            $atts,
            'nostr_lightning'
        );
        
        if (empty($atts['npub'])) {
            return '<span class="pulse-error">' . esc_html__('Error: npub parameter is required', 'pulse') . '</span>';
        }
        
        $npub = sanitize_text_field($atts['npub']);
        
        // Validate the npub
        if (!\Pulse\Nostr_Handler::validate_npub($npub)) {
            return '<span class="pulse-error">' . esc_html__('Error: Invalid Nostr npub format', 'pulse') . '</span>';
        }
        
        // Get lightning address using our existing handler with priority for Profile API
        $lightning_address = \Pulse\Nostr_Handler::get_lightning_address_from_npub($npub, false);
        
        if (!$lightning_address) {
            return '<span class="pulse-error">' . esc_html__('Error: No Lightning address found for this npub', 'pulse') . '</span>';
        }
        
        return '<span class="pulse-lightning-address">' . esc_html($lightning_address) . '</span>';
    }
    
    /**
     * Create a Lightning payment link for a Nostr npub
     *
     * @param array $atts Shortcode attributes
     * @return string HTML for the Lightning payment link
     */
    public function nostr_lightning_link_shortcode($atts) {
        $atts = shortcode_atts(
            array(
                'npub' => '',
                'label' => __('Pay with Lightning', 'pulse'),
                'amount' => '',
                'message' => '',
                'class' => 'pulse-lightning-button',
            ),
            $atts,
            'nostr_lightning_link'
        );
        
        if (empty($atts['npub'])) {
            return '<span class="pulse-error">' . esc_html__('Error: npub parameter is required', 'pulse') . '</span>';
        }
        
        $npub = sanitize_text_field($atts['npub']);
        
        // Validate the npub
        if (!\Pulse\Nostr_Handler::validate_npub($npub)) {
            return '<span class="pulse-error">' . esc_html__('Error: Invalid Nostr npub format', 'pulse') . '</span>';
        }
        
        // Get lightning address using our existing handler with priority for Profile API
        $lightning_address = \Pulse\Nostr_Handler::get_lightning_address_from_npub($npub, false);
        
        if (!$lightning_address) {
            return '<span class="pulse-error">' . esc_html__('Error: No Lightning address found for this npub', 'pulse') . '</span>';
        }
        
        // Build the Lightning payment URL
        $url = 'lightning:' . esc_attr($lightning_address);
        
        // Add optional amount parameter (in sats)
        if (!empty($atts['amount']) && is_numeric($atts['amount'])) {
            $url .= '?amount=' . intval($atts['amount']);
            
            // Add optional message parameter
            if (!empty($atts['message'])) {
                $url .= '&message=' . urlencode($atts['message']);
            }
        } else if (!empty($atts['message'])) {
            // Just message without amount
            $url .= '?message=' . urlencode($atts['message']);
        }
        
        // Return formatted link with optional CSS class
        return sprintf(
            '<a href="%s" class="%s">%s</a>',
            esc_url($url),
            esc_attr($atts['class']),
            esc_html($atts['label'])
        );
    }
    
    /**
     * Affiliate signup shortcode
     */
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
    
    /**
     * Process affiliate signup AJAX request
     */
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
                // For signup, use the cache normally to avoid unnecessary API calls
                $lightning_address = \Pulse\Nostr_Handler::get_lightning_address_from_npub($input_value, false);
                
                if (!$lightning_address) {
                    error_log('Pulse: Could not find Lightning address for npub: ' . $input_value);
                    // More helpful error message
                    $error_message = 'Could not find a Lightning address associated with this npub. ';
                    $error_message .= "Please make sure your Nostr profile has a Lightning address set in the 'lud16' field. ";
                    $error_message .= 'Alternatively, you can ask the site administrator to create a custom mapping for your npub.';
                    wp_send_json_error($error_message);
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
        $response['encrypted_link'] = $this->affiliate_link_handler->generate_affiliate_link($lightning_address);
        error_log('Pulse: Compact encrypted link generated');

        if (empty($response)) {
            error_log('Pulse: Unable to generate any affiliate links');
            wp_send_json_error('Unable to generate affiliate link. Please contact the administrator.');
        } else {
            error_log('Pulse: Successfully generated affiliate link(s): ' . print_r($response, true));
            wp_send_json_success($response);
        }
    }
    
    /**
     * Create the affiliate signup page
     */
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
}