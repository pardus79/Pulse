<?php
/**
 * BTCPay Server Integration Class
 *
 * @package Pulse
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class Pulse_BTCPay_Integration
 *
 * Handles integration with BTCPay Server for processing payouts.
 */
class Pulse_BTCPay_Integration {
    /**
     * BTCPay Server API URL
     *
     * @var string
     */
    private $api_url;

    /**
     * BTCPay Server API Key
     *
     * @var string
     */
    private $api_key;

    /**
     * BTCPay Server Store ID
     *
     * @var string
     */
    private $store_id;

    /**
     * Constructor
     */
    public function __construct() {
        $options = get_option('pulse_options');
        $this->api_url = isset($options['btcpay_url']) ? trailingslashit($options['btcpay_url']) : '';
        $this->api_key = isset($options['btcpay_api_key']) ? $options['btcpay_api_key'] : '';
        $this->store_id = isset($options['btcpay_store_id']) ? $options['btcpay_store_id'] : '';
    }

    /**
     * Create a payout via BTCPay Server
     *
     * @param string $lightning_address The Lightning address for the payout.
     * @param float $amount The amount to pay out.
     * @return string|bool The payout ID on success, false on failure.
     */
public function create_payout($lightning_address, $amount) {
    error_log('Pulse: Creating payout for lightning address: ' . $lightning_address . ', amount: ' . $amount);

    // Step 1: Create a Pull Payment
    $pull_payment_id = $this->create_pull_payment($amount);
    if (!$pull_payment_id) {
        error_log('Pulse: Failed to create pull payment');
        return false;
    }

    error_log('Pulse: Pull payment created successfully with ID: ' . $pull_payment_id);

    // Step 2: Verify Pull Payment
    $pull_payment = $this->get_pull_payment($pull_payment_id);
    if (!$pull_payment) {
        error_log('Pulse: Failed to verify pull payment after creation');
        return false;
    }

    error_log('Pulse: Pull payment verified successfully');

    // Step 3: Create a Payout for the Pull Payment
    $payout_id = $this->create_payout_for_pull_payment($pull_payment_id, $lightning_address, $amount);
    if (!$payout_id) {
        error_log('Pulse: Failed to create payout for pull payment');
        return false;
    }

    error_log('Pulse: Payout created successfully with ID: ' . $payout_id);
    return $payout_id;
}

private function get_pull_payment($pull_payment_id) {
    $endpoint = $this->api_url . 'api/v1/pull-payments/' . $pull_payment_id;

    $response = wp_remote_get($endpoint, array(
        'headers' => array(
            'Authorization' => 'token ' . $this->api_key
        )
    ));

    if (is_wp_error($response)) {
        error_log('Pulse: BTCPay Server API error when verifying pull payment: ' . $response->get_error_message());
        return false;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    error_log('Pulse: BTCPay Server API response code for pull payment verification: ' . $response_code);
    error_log('Pulse: BTCPay Server API response body for pull payment verification: ' . $response_body);

    if ($response_code !== 200) {
        error_log('Pulse: Failed to verify pull payment. Response code: ' . $response_code);
        return false;
    }

    return json_decode($response_body, true);
}
	
    /**
     * Create a Pull Payment
     *
     * @param float $amount The amount for the pull payment.
     * @return string|bool The pull payment ID on success, false on failure.
     */
private function create_pull_payment($amount) {
    if (empty($this->api_url) || empty($this->api_key) || empty($this->store_id)) {
        error_log('Pulse: BTCPay Server API URL, key, or Store ID is not set');
        return false;
    }

    $endpoint = $this->api_url . 'api/v1/stores/' . $this->store_id . '/pull-payments';

    $body = array(
        'name' => 'Affiliate Payout',
        'amount' => strval($amount),
        'currency' => 'USD',
        'paymentMethods' => ['BTC-LightningNetwork'],
        'autoApproveClaims' => true
    );

    error_log('Pulse: Sending pull payment request to BTCPay Server. Endpoint: ' . $endpoint . ', Body: ' . json_encode($body));

    $response = wp_remote_post($endpoint, array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'token ' . $this->api_key
        ),
        'body' => json_encode($body)
    ));

    if (is_wp_error($response)) {
        error_log('Pulse: BTCPay Server API error: ' . $response->get_error_message());
        return false;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);

    error_log('Pulse: BTCPay Server API response code for pull payment: ' . $response_code);
    error_log('Pulse: BTCPay Server API response body for pull payment: ' . $response_body);

    $data = json_decode($response_body, true);

    if ($response_code !== 200 && $response_code !== 201) {
        error_log('Pulse: Failed to create pull payment. Response code: ' . $response_code . ', Response body: ' . $response_body);
        return false;
    }

    if (!isset($data['id'])) {
        error_log('Pulse: Pull payment ID not found in response. Response body: ' . $response_body);
        return false;
    }

    return $data['id'];
}

private function create_payout_for_pull_payment($pull_payment_id, $lightning_address, $amount) {
    if (empty($this->api_url) || empty($this->api_key) || empty($this->store_id)) {
        error_log('Pulse: BTCPay Server API URL, key, or Store ID is not set');
        return false;
    }

    // Update this endpoint based on the correct API path
    $endpoint = $this->api_url . 'api/v1/stores/' . $this->store_id . '/payouts';

    $body = array(
        'pullPaymentId' => $pull_payment_id,
        'destination' => $lightning_address,
        'amount' => strval($amount),
        'paymentMethod' => 'BTC-LightningLike'
    );

    error_log('Pulse: Sending payout request to BTCPay Server. Endpoint: ' . $endpoint . ', Body: ' . json_encode($body));

    $response = wp_remote_post($endpoint, array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'token ' . $this->api_key
        ),
        'body' => json_encode($body)
    ));

    if (is_wp_error($response)) {
        error_log('Pulse: BTCPay Server API error: ' . $response->get_error_message());
        return false;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $response_headers = wp_remote_retrieve_headers($response);

    error_log('Pulse: BTCPay Server API response code for payout: ' . $response_code);
    error_log('Pulse: BTCPay Server API response body for payout: ' . $response_body);
    error_log('Pulse: BTCPay Server API response headers for payout: ' . print_r($response_headers, true));

    if ($response_code !== 200 && $response_code !== 201) {
        error_log('Pulse: Failed to create payout. Response code: ' . $response_code . ', Response body: ' . $response_body);
        return false;
    }

    $data = json_decode($response_body, true);

    if (!isset($data['id'])) {
        error_log('Pulse: Payout ID not found in response. Response body: ' . $response_body);
        return false;
    }

    return $data['id'];
}
    /**
     * Get the status of a payout
     *
     * @param string $payout_id The ID of the payout to check.
     * @return string|bool The status of the payout, or false on failure.
     */
    public function get_payout_status($payout_id) {
        if (empty($this->api_url) || empty($this->api_key) || empty($this->store_id)) {
            error_log('Pulse: BTCPay Server API URL, key, or Store ID is not set');
            return false;
        }

        $endpoint = $this->api_url . 'api/v1/stores/' . $this->store_id . '/payouts/' . $payout_id;

        error_log('Pulse: Sending payout status request to BTCPay Server. Endpoint: ' . $endpoint);

        $response = wp_remote_get($endpoint, array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'token ' . $this->api_key
            )
        ));

        if (is_wp_error($response)) {
            error_log('Pulse: BTCPay Server API error: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        error_log('Pulse: BTCPay Server API response: ' . print_r($data, true));

        if (isset($data['state'])) {
            return $data['state'];
        } else {
            error_log('Pulse: Failed to get payout status. Response: ' . print_r($data, true));
            return false;
        }
    }
}