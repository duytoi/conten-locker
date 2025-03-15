// public/js/password-verification.js
(function($) {
    'use strict';

    var WCL_PasswordVerification = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('submit', '.wcl-password-form', this.handleVerification);
        },

        handleVerification: function(e) {
            e.preventDefault();
            var $form = $(this);
            var $submitBtn = $form.find('button[type="submit"]');
            var $message = $form.find('.wcl-message');

            // Disable form
            $submitBtn.prop('disabled', true);
            
            $.ajax({
                url: wcl_vars.ajaxurl,
                type: 'POST',
                data: {
                    action: 'wcl_verify_password',
                    nonce: wcl_vars.nonce,
                    password: $form.find('input[name="password"]').val(),
                    protection_id: $form.find('input[name="protection_id"]').val()
                },
                success: function(response) {
                    if (response.success) {
                        $message.removeClass('error').addClass('success')
                            .html(response.data.message);
                        
                        setTimeout(function() {
                            window.location.href = response.data.redirect_url;
                        }, 1500);
                    } else {
                        $message.removeClass('success').addClass('error')
                            .html(response.data.message);
                        $submitBtn.prop('disabled', false);
                    }
                },
                error: function() {
                    $message.removeClass('success').addClass('error')
                        .html(wcl_vars.error_message);
                    $submitBtn.prop('disabled', false);
                }
            });
        }
    };

    $(document).ready(function() {
        WCL_PasswordVerification.init();
    });

})(jQuery);