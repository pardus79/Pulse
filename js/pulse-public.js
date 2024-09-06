(function( $ ) {
    'use strict';

    $(function() {
        // Handle affiliate form submission
        $('#pulse-affiliate-form').on('submit', function(e) {
            e.preventDefault();
            var lightningAddress = $('#pulse-lightning-address').val();

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
                        $('#pulse-affiliate-result').show();
                    } else {
                        alert(response.data);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        });

        // Add any other public-facing JavaScript here
    });

})( jQuery );