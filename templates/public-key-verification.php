<?php
/**
 * Template for Public Key Verification
 *
 * @package Pulse
 */

// Ensure this file is being included by a parent file
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$options = get_option('pulse_options');
$public_key = isset($options['public_key']) ? $options['public_key'] : '';
?>

<div class="pulse-public-key-verification">
    <h2><?php _e('Pulse Public Key Verification', 'pulse'); ?></h2>
    
    <p><?php _e('To ensure the security of your affiliate link, you can verify the public key used for encryption. Follow these steps:', 'pulse'); ?></p>
    
    <ol>
        <li><?php _e('Copy the public key displayed below.', 'pulse'); ?></li>
        <li><?php _e('Use a trusted OpenSSL installation to verify the key.', 'pulse'); ?></li>
        <li><?php _e('Ensure that the key details match what you expect (e.g., key length, algorithm).', 'pulse'); ?></li>
    </ol>
    
    <h3><?php _e('Public Key:', 'pulse'); ?></h3>
    <pre id="pulse-public-key"><?php echo esc_html($public_key); ?></pre>
    
    <button id="pulse-copy-public-key" class="button"><?php _e('Copy Public Key', 'pulse'); ?></button>
    
    <h3><?php _e('Verify using OpenSSL:', 'pulse'); ?></h3>
    <p><?php _e('You can use the following OpenSSL command to verify the key:', 'pulse'); ?></p>
    <pre>echo "<?php echo esc_html($public_key); ?>" | openssl rsa -pubin -text -noout</pre>
    
    <p><?php _e('This command will display the details of the public key, including its length and modulus.', 'pulse'); ?></p>
    
    <h3><?php _e('What to Look For:', 'pulse'); ?></h3>
    <ul>
        <li><?php _e('Key length should be 2048 bits or higher.', 'pulse'); ?></li>
        <li><?php _e('The key should use the RSA algorithm.', 'pulse'); ?></li>
        <li><?php _e('Verify that the modulus matches what you expect.', 'pulse'); ?></li>
    </ul>
    
    <p><?php _e('If you have any concerns about the authenticity of this key, please contact the site administrator immediately.', 'pulse'); ?></p>
</div>

<script>
    jQuery(document).ready(function($) {
        $('#pulse-copy-public-key').on('click', function() {
            var $temp = $("<textarea>");
            $("body").append($temp);
            $temp.val($('#pulse-public-key').text()).select();
            document.execCommand("copy");
            $temp.remove();
            $(this).text('<?php _e('Copied!', 'pulse'); ?>');
            setTimeout(function() {
                $('#pulse-copy-public-key').text('<?php _e('Copy Public Key', 'pulse'); ?>');
            }, 2000);
        });
    });
</script>
