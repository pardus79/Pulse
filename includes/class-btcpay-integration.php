class BTCPay_Integration {
    public function send_payout($lightning_address, $amount) {
        if (!Lightning_Address_Validator::validate($lightning_address)) {
            error_log("Invalid Lightning address format: $lightning_address");
            return false;
        }

        // Proceed with BTCPay Server API call
        $api_url = get_option('btcpay_api_url');
        $api_key = get_option('btcpay_api_key');

        $response = wp_remote_post($api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'destination' => $lightning_address,
                'amount' => $amount,
                'currency' => 'BTC'
            ])
        ]);

        if (is_wp_error($response)) {
            error_log("BTCPay Server API error: " . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return isset($body['id']); // Assuming successful payout returns an ID
    }
}
