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
        <?php 
        $options = get_option('pulse_options');
        $enable_nostr = isset($options['enable_nostr']) ? $options['enable_nostr'] : false;
        ?>
        
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
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching
    const tabs = document.querySelectorAll('.pulse-tab');
    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            // Remove active class from all tabs
            tabs.forEach(t => t.classList.remove('active'));
            
            // Add active class to clicked tab
            this.classList.add('active');
            
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.pulse-tab-content');
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Show active tab content
            const tabName = this.getAttribute('data-tab');
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Enable/disable input fields based on active tab
            if (tabName === 'lightning') {
                document.getElementById('pulse-lightning-address').disabled = false;
                if (document.getElementById('pulse-nostr-npub')) {
                    document.getElementById('pulse-nostr-npub').disabled = true;
                }
            } else if (tabName === 'nostr') {
                document.getElementById('pulse-lightning-address').disabled = true;
                document.getElementById('pulse-nostr-npub').disabled = false;
            }
        });
    });
});
</script>