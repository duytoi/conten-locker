(function($) {
    'use strict';

    class DownloadHandler {
    constructor() {
        this.initEvents();
    }

    initEvents() {
        // Show password form
        $(document).on('click', '.wcl-initial-button', (e) => {
            const $wrapper = $(e.currentTarget).closest('.wcl-download-wrapper');
            $(e.currentTarget).hide();
            $wrapper.find('.wcl-password-form-wrapper').slideDown();
        });

        // Hide password form
        $(document).on('click', '.wcl-cancel-btn', (e) => {
            const $wrapper = $(e.currentTarget).closest('.wcl-download-wrapper');
            $wrapper.find('.wcl-password-form-wrapper').slideUp();
            $wrapper.find('.wcl-initial-button').show();
        });

        // Handle form submit
        $(document).on('submit', '.wcl-password-form', (e) => {
            e.preventDefault();
            this.handlePasswordSubmit(e);
        });
    }

    handlePasswordSubmit(e) {
        const $form = $(e.currentTarget);
        const $wrapper = $form.closest('.wcl-download-wrapper');
        const $message = $wrapper.find('.wcl-message');
        const $submitBtn = $form.find('.wcl-submit-btn');

        $submitBtn.prop('disabled', true).text('Verifying...');
        $message.html('').removeClass('error success');

        $.ajax({
            url: wclParams.ajaxurl,
            type: 'POST',
            data: $form.serialize(),
            success: (response) => {
                if (response.success) {
                    $wrapper.replaceWith(response.data.html);
                } else {
                    $message
                        .html(response.data.message)
                        .addClass('error');
                    $submitBtn.prop('disabled', false).text('Unlock Download');
                }
            },
            error: () => {
                $message
                    .html('An error occurred. Please try again.')
                    .addClass('error');
                $submitBtn.prop('disabled', false).text('Unlock Download');
            }
        });
    }
}

// Initialize
jQuery(document).ready(() => new DownloadHandler());

})(jQuery);