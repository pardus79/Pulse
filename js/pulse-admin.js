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

        // BTCPay Server cache clearing
        $('#pulse-clear-cache').on('click', function() {
            var $button = $(this);
            var $status = $('#cache-status');
            
            $button.prop('disabled', true);
            $status.text('Clearing cache...');
            
            $.ajax({
                url: pulseData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'pulse_clear_btcpay_cache',
                    nonce: pulseData.clearCacheNonce
                },
                success: function(response) {
                    if (response.success) {
                        $status.text(response.data.message);
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        $status.text('Error clearing cache');
                        $button.prop('disabled', false);
                    }
                },
                error: function() {
                    $status.text('Error clearing cache');
                    $button.prop('disabled', false);
                }
            });
        });

        // Handle dismissal of BTCPay v2 notice
        $(document).on('click', '.pulse-v2-notice .notice-dismiss', function() {
            $.ajax({
                url: ajaxurl,
                data: {
                    action: 'pulse_dismiss_v2_notice',
                    nonce: pulseData.dismissNoticeNonce
                }
            });
        });

        // Add any other admin-specific JavaScript here
    });
})(jQuery);