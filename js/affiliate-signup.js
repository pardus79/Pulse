(function($) {
    'use strict';

    $(document).ready(function() {
        $('#pulse-affiliate-signup-form').on('submit', function(e) {
            e.preventDefault();

            var lightningAddress = $('#pulse-lightning-address').val();
            var $submitButton = $(this).find('button[type="submit"]');
            var $resultContainer = $('#pulse-result');

            $submitButton.prop('disabled', true).text('Processing...');
            $resultContainer.hide();

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
})(jQuery);