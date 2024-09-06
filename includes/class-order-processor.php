<?php
class Pulse_Order_Processor {
    public function __construct() {
        add_action('woocommerce_order_status_completed', array($this, 'process_affiliate_payment'));
    }

    public function process_affiliate_payment($order_id) {
        $order = wc_get_order($order_id);
        $affiliate_link = $order->get_meta('affiliate_link');

        if (!$affiliate_link) {
            return; // No affiliate link associated with this order
        }

        if ($order->get_meta('affiliate_payment_sent')) {
            return; // Payment already sent
        }

        $encryption_handler = new Pulse_Encryption_Handler();
        $btcpay_integration = new Pulse_BTCPay_Integration();

        $encrypted_address = str_replace(site_url() . '/?aff=', '', $affiliate_link);
        $lightning_address = $encryption_handler->decrypt($encrypted_address);

        if (!Pulse_Lightning_Address_Validator::validate($lightning_address)) {
            error_log("Invalid Lightning address for order $order_id: $lightning_address");
            return;
        }

        $options = get_option('pulse_options');
        $commission_rate = isset($options['commission_rate']) ? floatval($options['commission_rate']) : 0.1; // Default to 10%
        $payout_amount = $order->get_total() * $commission_rate;

        $payout_successful = $btcpay_integration->send_payout($lightning_address, $payout_amount);

        if ($payout_successful) {
            $order->update_meta_data('affiliate_payment_sent', true);
            $order->save();
        } else {
            error_log("Failed to send affiliate payment for order $order_id");
        }
    }
}