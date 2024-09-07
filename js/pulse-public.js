(function($) {
    'use strict';

    $(function() {
        // Handle affiliate form submission
        $('#pulse-affiliate-form').on('submit', function(e) {
            e.preventDefault();
            var lightningAddress = $('#pulse-lightning-address').val();
            var $submitButton = $(this).find('button[type="submit"]');
            var $resultContainer = $('#pulse-affiliate-result');

            $submitButton.prop('disabled', true).text('Processing...');
            $resultContainer.hide();

            $.ajax({
                url: pulse_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'pulse_generate_affiliate_link',
                    nonce: pulse_ajax.nonce,
                    lightning_address: lightningAddress
                },
                success: function(response) {
                    if (response.success) {
                        $('#pulse-affiliate-link').text(response.data.affiliate_link);
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

        // Add any other public-facing JavaScript here
    });
})(jQuery);