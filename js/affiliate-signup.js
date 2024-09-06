jQuery(document).ready(function($) {
    $('#pulse-affiliate-signup-form').on('submit', function(e) {
        e.preventDefault();

        var lightningAddress = $('#pulse-lightning-address').val();

        $.ajax({
            url: pulseAffiliateSignup.ajaxurl,
            type: 'POST',
            data: {
                action: 'pulse_generate_affiliate_link',
                nonce: pulseAffiliateSignup.nonce,
                lightning_address: lightningAddress
            },
            success: function(response) {
                if (response.success) {
                    $('#pulse-affiliate-link').text(response.data.affiliate_link);
                    $('#pulse-public-key').text(response.data.public_key);
                    $('#pulse-result').show();
                } else {
                    alert(response.data);
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
            }
        });
    });
});
