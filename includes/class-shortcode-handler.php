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
        $enable_nostr = isset($options['enable_nostr']) ? $options['enable_nostr'] : false;

        ob_start();
        ?>
        <div class="pulse-affiliate-signup">
            <form id="pulse-affiliate-signup-form" class="pulse-affiliate-form">
                <div class="pulse-tab-container">
                    <div class="pulse-tabs">
                        <button type="button" class="pulse-tab active" data-tab="lightning"><?php esc_html_e('Lightning Address', 'pulse'); ?></button>
                        <?php if ($enable_nostr): ?>
                        <button type="button" class="pulse-tab" data-tab="nostr"><?php esc_html_e('Nostr npub', 'pulse'); ?></button>
                        <?php endif; ?>
                    </div>
                    
                    <div class="pulse-tab-content active" id="lightning-tab">
                        <label for="pulse-lightning-address"><?php esc_html_e('Lightning Address:', 'pulse'); ?></label>
                        <input type="text" id="pulse-lightning-address" name="lightning-address" placeholder="you@lightning.address" required>
                        <div id="pulse-address-error" class="pulse-error-message" aria-live="polite"></div>
                    </div>
                    
                    <?php if ($enable_nostr): ?>
                    <div class="pulse-tab-content" id="nostr-tab">
                        <label for="pulse-nostr-npub"><?php esc_html_e('Nostr npub:', 'pulse'); ?></label>
                        <input type="text" id="pulse-nostr-npub" name="nostr-npub" placeholder="npub1..." required disabled>
                        <div id="pulse-npub-error" class="pulse-error-message" aria-live="polite"></div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <button type="submit"><?php esc_html_e('Generate Affiliate Link', 'pulse'); ?></button>
            </form>

            <div id="pulse-result" class="pulse-affiliate-result" style="display:none;">
                <h3><?php esc_html_e('Your Affiliate Links:', 'pulse'); ?></h3>
                
                <div id="pulse-links-container">
                    <?php if ($enable_nostr): ?>
                    <div id="pulse-nostr-link-container" style="display:none;">
                        <h4><?php esc_html_e('Nostr npub Link:', 'pulse'); ?></h4>
                        <p id="pulse-nostr-link"></p>
                    </div>
                    <?php endif; ?>
                    
                    <div id="pulse-lightning-container">
                        <h4><?php esc_html_e('Unencrypted Link:', 'pulse'); ?></h4>
                        <p id="pulse-unencrypted-link"></p>
                        
                        <h4><?php esc_html_e('Encrypted Link:', 'pulse'); ?></h4>
                        <p id="pulse-encrypted-link"></p>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .pulse-tab-container {
            margin-bottom: 20px;
        }
        .pulse-tabs {
            display: flex;
            margin-bottom: 15px;
        }
        .pulse-tab {
            padding: 8px 16px;
            background: #f1f1f1;
            border: 1px solid #ddd;
            border-bottom: none;
            cursor: pointer;
            margin-right: 5px;
        }
        .pulse-tab.active {
            background: #fff;
            border-bottom: 1px solid #fff;
            position: relative;
            z-index: 1;
        }
        .pulse-tab-content {
            display: none;
            padding: 15px;
            border: 1px solid #ddd;
            margin-top: -1px;
        }
        .pulse-tab-content.active {
            display: block;
        }
        #pulse-links-container h4 {
            margin-top: 20px;
            margin-bottom: 5px;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Tab switching
            $('.pulse-tab').on('click', function() {
                // Remove active class from all tabs
                $('.pulse-tab').removeClass('active');
                
                // Add active class to clicked tab
                $(this).addClass('active');
                
                // Hide all tab contents
                $('.pulse-tab-content').removeClass('active');
                
                // Show active tab content
                const tabName = $(this).data('tab');
                $('#' + tabName + '-tab').addClass('active');
                
                // Enable/disable input fields based on active tab
                if (tabName === 'lightning') {
                    $('#pulse-lightning-address').prop('disabled', false);
                    $('#pulse-nostr-npub').prop('disabled', true);
                } else if (tabName === 'nostr') {
                    $('#pulse-lightning-address').prop('disabled', true);
                    $('#pulse-nostr-npub').prop('disabled', false);
                }
            });
            
            // Form submission
            $('#pulse-affiliate-signup-form').on('submit', function(e) {
                e.preventDefault();
                var $submitButton = $(this).find('button[type="submit"]');
                var $resultContainer = $('#pulse-result');
                
                // Determine which tab is active
                var activeTab = $('.pulse-tab.active').data('tab');
                var isNostr = (activeTab === 'nostr');
                
                // Get the appropriate input value
                var inputValue = isNostr 
                    ? $('#pulse-nostr-npub').val() 
                    : $('#pulse-lightning-address').val();
                
                if (!inputValue) {
                    alert('Please enter a ' + (isNostr ? 'Nostr npub' : 'Lightning address'));
                    return;
                }
                
                $submitButton.prop('disabled', true).text('Processing...');
                $resultContainer.hide();

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'pulse_affiliate_signup',
                        input_type: isNostr ? 'nostr' : 'lightning',
                        input_value: inputValue,
                        nonce: '<?php echo wp_create_nonce('pulse_affiliate_signup'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            if (isNostr) {
                                // Show Nostr link section
                                if (response.data.custom_link) {
                                    $('#pulse-nostr-link').text(response.data.custom_link);
                                    $('#pulse-nostr-link-container').show();
                                } else {
                                    $('#pulse-nostr-link-container').hide();
                                }
                                
                                // Show lightning links if available
                                if (response.data.unencrypted_link) {
                                    $('#pulse-unencrypted-link').text(response.data.unencrypted_link);
                                    $('#pulse-encrypted-link').text(response.data.encrypted_link);
                                    $('#pulse-lightning-container').show();
                                } else {
                                    $('#pulse-lightning-container').hide();
                                }
                            } else {
                                // For lightning address response
                                $('#pulse-unencrypted-link').text(response.data.unencrypted_link);
                                $('#pulse-encrypted-link').text(response.data.encrypted_link);
                                $('#pulse-lightning-container').show();
                                
                                // Hide Nostr section
                                $('#pulse-nostr-link-container').hide();
                            }
                            
                            $resultContainer.show();
                        } else {
                            alert(response.data || 'An error occurred. Please try again.');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        alert('An error occurred while processing your request. Please try again later.');
                    },
                    complete: function() {
                        $submitButton.prop('disabled', false).text('Generate Affiliate Link');
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

        $lightning_address = '';
        $response = array();

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
                // Generate direct npub link
                $response['custom_link'] = home_url('?aff=' . urlencode($input_value));
                error_log('Pulse: Generated npub affiliate link');
                
                // Also try to get lightning address
                $lightning_address = \Pulse\Nostr_Handler::get_lightning_address_from_npub($input_value, false);
                
                if (!$lightning_address) {
                    error_log('Pulse: No Lightning address found for npub, but we have the npub link');
                    $response['unencrypted_link'] = false; // Make sure we indicate no lightning links
                    wp_send_json_success($response);
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