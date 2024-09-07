<?php
/**
 * Affiliate Signup Form Template
 *
 * @package Pulse
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>

<div class="pulse-affiliate-signup">
    <form id="pulse-affiliate-signup-form" class="pulse-affiliate-form">
        <label for="pulse-lightning-address"><?php esc_html_e('Lightning Address:', 'pulse'); ?></label>
        <input type="text" id="pulse-lightning-address" name="lightning-address" required>
        <div id="pulse-address-error" class="pulse-error-message" aria-live="polite"></div>
        <button type="submit"><?php esc_html_e('Generate Affiliate Link', 'pulse'); ?></button>
    </form>

    <div id="pulse-result" class="pulse-affiliate-result" style="display:none;">
        <h3><?php esc_html_e('Your Affiliate Link:', 'pulse'); ?></h3>
        <p id="pulse-affiliate-link"></p>
        
        <h3><?php esc_html_e('Public Encryption Key:', 'pulse'); ?></h3>
        <pre id="pulse-public-key"></pre>
        <p><?php esc_html_e('You can use this public key to independently verify your affiliate link.', 'pulse'); ?></p>
    </div>
</div>