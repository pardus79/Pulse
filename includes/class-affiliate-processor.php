<?php
/**
 * Affiliate Processor
 *
 * @package Pulse
 */

namespace Pulse;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Affiliate_Processor {
    private $btcpay_integration;
    private $affiliate_link_handler;
    
    public function __construct($btcpay_integration, $affiliate_link_handler) {
        $this->btcpay_integration = $btcpay_integration;
        $this->affiliate_link_handler = $affiliate_link_handler;
        
        add_action('init', array($this, 'capture_affiliate'));
        add_action('woocommerce_checkout_order_processed', array($this, 'add_affiliate_to_order'), 10, 3);
        add_action('woocommerce_order_status_completed', array($this, 'process_affiliate_payout'));
    }
    
    /**
     * Capture affiliate from URL and set cookie
     */
    public function capture_affiliate() {
        if (isset($_GET['aff'])) {
            $encoded_affiliate = sanitize_text_field($_GET['aff']);
            
            // Check if this is a Nostr npub
            $options = get_option('pulse_options');
            $enable_nostr = isset($options['enable_nostr']) ? $options['enable_nostr'] : false;
            
            if ($enable_nostr && strpos($encoded_affiliate, 'npub1') === 0) {
                // This is a Nostr npub - try to refresh the lightning address cache when link is used
                \Pulse\Nostr_Handler::get_lightning_address_from_npub($encoded_affiliate, false);
                error_log('Pulse: Refreshed npub lightning address cache for ' . $encoded_affiliate);
            }
            
            setcookie('pulse_affiliate', $encoded_affiliate, time() + (86400 * 30), "/"); // 30 days expiry
        }
    }

    /**
     * Add affiliate to order meta
     */
    public function add_affiliate_to_order($order_id, $posted_data, $order) {
        if (isset($_COOKIE['pulse_affiliate'])) {
            $encoded_affiliate = sanitize_text_field($_COOKIE['pulse_affiliate']);
            
            // Check if this is a Nostr npub and refresh the lightning address at order time
            $options = get_option('pulse_options');
            $enable_nostr = isset($options['enable_nostr']) ? $options['enable_nostr'] : false;
            
            if ($enable_nostr && strpos($encoded_affiliate, 'npub1') === 0) {
                // Try to get a fresh lightning address from npub at order placement time
                // This ensures we have the most up-to-date address when the order is processed
                \Pulse\Nostr_Handler::get_lightning_address_from_npub($encoded_affiliate, true);
                error_log('Pulse: Refreshed lightning address at order placement for npub ' . $encoded_affiliate);
            }
            
            $order->update_meta_data('pulse_affiliate', $encoded_affiliate);
            $order->save();
        }
    }

    /**
     * Process affiliate payment when an order is completed
     */
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
            $lightning_address = \Pulse\Nostr_Handler::get_lightning_address_from_npub($encoded_affiliate, true); // Skip cache for payment processing
            if (!$lightning_address) {
                error_log('Pulse: Failed to get Lightning address from npub ' . $encoded_affiliate . ' for order ' . $order_id);
                $order->add_order_note(__('Failed to process affiliate payout. Could not retrieve Lightning address from npub.', 'pulse'));
                return false;
            }
        } else {
            // Not a custom mapping or npub, try to decode it
            $lightning_address = $this->affiliate_link_handler->decode_affiliate_link($encoded_affiliate);
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
}