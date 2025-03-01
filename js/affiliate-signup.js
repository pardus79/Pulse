(function($) {
    'use strict';

    $(document).ready(function() {
        $('#pulse-affiliate-signup-form').on('submit', function(e) {
            e.preventDefault();

            var $submitButton = $(this).find('button[type="submit"]');
            var $resultContainer = $('#pulse-result');
            
            // Determine which tab is active
            var activeTab = $('.pulse-tab.active').data('tab');
            var isNostr = (activeTab === 'nostr');
            
            // Get the appropriate input value
            var inputValue = isNostr 
                ? $('#pulse-nostr-npub').val() 
                : $('#pulse-lightning-address').val();
            
            if (!inputValue) {
                alert('Please enter a ' + (isNostr ? 'Nostr npub' : 'Lightning address'));
                return;
            }
            
            $submitButton.prop('disabled', true).text('Processing...');
            $resultContainer.hide();

            $.ajax({
                url: pulseAffiliateSignup.ajaxurl,
                type: 'POST',
                data: {
                    action: 'pulse_generate_affiliate_link',
                    nonce: pulseAffiliateSignup.nonce,
                    input_type: isNostr ? 'npub' : 'lightning',
                    input_value: inputValue
                },
                success: function(response) {
                    if (response.success) {
                        if (isNostr) {
                            // Show Nostr link section
                            $('#pulse-nostr-link').text(response.data.nostr_link);
                            $('#pulse-nostr-link-container').show();
                            
                            // Hide or show lightning links
                            if (!response.data.has_lightning) {
                                $('#pulse-lightning-container').hide();
                            } else {
                                // Show lightning-related links
                                $('#pulse-unencrypted-link').text(response.data.unencrypted_link);
                                $('#pulse-encrypted-link').text(response.data.encrypted_link);
                                $('#pulse-lightning-container').show();
                            }
                        } else {
                            // For lightning address response
                            $('#pulse-unencrypted-link').text(response.data.unencrypted_link);
                            $('#pulse-encrypted-link').text(response.data.encrypted_link);
                            $('#pulse-lightning-container').show();
                            
                            // Hide Nostr section
                            $('#pulse-nostr-link-container').hide();
                        }
                        
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