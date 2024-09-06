(function( $ ) {
    'use strict';

    $(function() {
        // Copy to clipboard functionality
        $('.pulse-copy-to-clipboard').on('click', function(e) {
            e.preventDefault();
            var copyText = $(this).data('copy-text');
            var $temp = $("<input>");
            $("body").append($temp);
            $temp.val(copyText).select();
            document.execCommand("copy");
            $temp.remove();
            $(this).text('Copied!').prop('disabled', true);
            setTimeout(() => {
                $(this).text('Copy').prop('disabled', false);
            }, 2000);
        });

        // Add any other admin-specific JavaScript here
    });

})( jQuery );