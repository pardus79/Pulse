(function($) {
    'use strict';

    $(function() {
        // Copy to clipboard functionality
        $('.pulse-copy-to-clipboard').on('click', function(e) {
            e.preventDefault();
            var copyText = $(this).data('copy-text');
            var $temp = $("<input>");
            $("body").append($temp);
            $temp.val(copyText).select();
            
            try {
                var successful = document.execCommand('copy');
                var msg = successful ? 'Copied!' : 'Failed to copy';
                $(this).text(msg).prop('disabled', true);
            } catch (err) {
                console.error('Unable to copy', err);
                $(this).text('Copy failed').prop('disabled', true);
            }
            
            $temp.remove();
            
            setTimeout(() => {
                $(this).text('Copy').prop('disabled', false);
            }, 2000);
        });

        // Confirmation for resetting keys
        $('#pulse-reset-keys').on('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to reset the encryption keys? This will invalidate all existing affiliate links.')) {
                // Perform AJAX call to reset keys
                $.ajax({
                    url: pulseAdminData.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'pulse_reset_keys',
                        nonce: pulseAdminData.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Keys have been reset successfully.');
                            location.reload();
                        } else {
                            alert('Failed to reset keys. Please try again.');
                        }
                    },
                    error: function() {
                        alert('An error occurred. Please try again.');
                    }
                });
            }
        });

        // Add any other admin-specific JavaScript here
    });
})(jQuery);