<?php
/**
 * Order Processor
 *
 * @package Pulse
 */

namespace Pulse;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Order_Processor {
    const PAYOUT_SENT_TAG = 'pulse_payout_sent';

    public function __construct() {
        add_action('woocommerce_order_status_completed', array($this, 'process_affiliate_payment'));
    }

    /**
     * Process affiliate payment when an order is completed
     *
     * @param int $order_id The ID of the completed order
     * @return bool True if payment was processed successfully, false otherwise
     */
    public function process_affiliate_payment($order_id) {
        $order = wc_get_order($order_id);
        $affiliate_link = $order->get_meta('affiliate_link');

        if (!$affiliate_link) {
            return false; // No affiliate link associated with this order
        }

        if ($this->is_payout_sent($order)) {
            return true; // Payout already sent
        }

        $encryption_handler = new Encryption_Handler();
        $btcpay_integration = new BTCPay_Integration();

        // Sanitize affiliate link to prevent malicious input
        $encrypted_address = sanitize_text_field(
            str_replace(site_url() . '/?aff=', '', $affiliate_link)
        );
        
        // Determine if this is an npub affiliate
        $options = get_option('pulse_options');
        $enable_nostr = isset($options['enable_nostr']) ? $options['enable_nostr'] : false;
        
        // If this starts with npub1, it's a Nostr public key
        if ($enable_nostr && strpos($encrypted_address, 'npub1') === 0) {
            // Validate the npub format before attempting to use it
            if (!\Pulse\Nostr_Handler::validate_npub($encrypted_address)) {
                $this->log_error("Invalid npub format for order $order_id: " . esc_html($encrypted_address));
                return false;
            }
            
            // Try to get a fresh lightning address from the npub, but fall back to cached one if API fails
            $lightning_address = \Pulse\Nostr_Handler::get_lightning_address_from_npub($encrypted_address, true);
            if (!$lightning_address) {
                $this->log_error("Failed to get Lightning address from npub for order $order_id");
                return false;
            }
        } else {
            // For encrypted addresses, try to decrypt
            try {
                $lightning_address = $encryption_handler->decrypt($encrypted_address);
            } catch (\Exception $e) {
                $this->log_error("Decryption error for order $order_id: " . $e->getMessage());
                return false;
            }
        }

        if (!$lightning_address || !Lightning_Address_Validator::validate($lightning_address)) {
            $this->log_error("Invalid Lightning address for order $order_id: " . esc_html($lightning_address));
            return false;
        }

        $options = get_option('pulse_options');
        $commission_rate = isset($options['commission_rate']) ? floatval($options['commission_rate']) : 0.1; // Default to 10%
        $payout_amount = $order->get_total() * $commission_rate;

        // Create pull payment
        $pull_payment_id = $btcpay_integration->create_pull_payment($payout_amount, $order->get_currency());

        if (!$pull_payment_id) {
            $this->log_error("Failed to create pull payment for order $order_id");
            return false;
        }

        // Create payout
        $payout_id = $btcpay_integration->create_payout($pull_payment_id, $lightning_address, $payout_amount);

        if ($payout_id) {
            $this->tag_payout_sent($order, $payout_id);
            $this->log_info("Payout sent for order $order_id. Payout ID: $payout_id");
            return true;
        } else {
            $this->log_error("Failed to create payout for order $order_id");
            return false;
        }
    }

    /**
     * Check if payout has been sent for an order
     *
     * @param WC_Order $order The order object
     * @return bool True if payout has been sent, false otherwise
     */
    private function is_payout_sent($order) {
        return $order->get_meta(self::PAYOUT_SENT_TAG) === 'yes';
    }

    /**
     * Tag an order as having had its payout sent
     *
     * @param WC_Order $order The order object
     * @param string $payout_id The ID of the payout
     */
    private function tag_payout_sent($order, $payout_id) {
        $order->update_meta_data(self::PAYOUT_SENT_TAG, 'yes');
        $order->update_meta_data('pulse_payout_id', $payout_id);
        $order->add_order_note(sprintf(__("Affiliate payout sent. Payout ID: %s", 'pulse'), $payout_id));
        $order->save();
    }

    /**
     * Log error messages
     *
     * @param string $message The error message to log
     */
    private function log_error($message) {
        error_log('Pulse Order Processor Error: ' . $message);
    }

    /**
     * Log informational messages
     *
     * @param string $message The info message to log
     */
    private function log_info($message) {
        error_log('Pulse Order Processor Info: ' . $message);
    }
}