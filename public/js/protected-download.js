jQuery(document).ready(function($) {
	console.log('Protected download JS loaded');
    
    // Debug click event
    $('.wcl-initial-button').on('click', function(e) {
        console.log('Download button clicked');
        e.preventDefault();
        const $wrapper = $(this).closest('.wcl-download-wrapper');
        console.log('Wrapper found:', $wrapper.length > 0);
        $(this).hide();
        $wrapper.find('.wcl-password-form-wrapper').show();
    });
    // Click handler cho nút Download Now
    $('.wcl-initial-button').on('click', function(e) {
        e.preventDefault();
        const $wrapper = $(this).closest('.wcl-download-wrapper');
        $(this).hide();
        $wrapper.find('.wcl-password-form-wrapper').show();
    });

    // Click handler cho nút Cancel
    $('.wcl-cancel-btn').on('click', function(e) {
        e.preventDefault();
        const $wrapper = $(this).closest('.wcl-download-wrapper');
        $wrapper.find('.wcl-password-form-wrapper').hide();
        $wrapper.find('.wcl-initial-button').show();
    });

    // Submit handler cho form password
    $('.wcl-password-form').on('submit', function(e) {
        e.preventDefault();
        const $form = $(this);
        const $wrapper = $form.closest('.wcl-download-wrapper');
        const $message = $wrapper.find('.wcl-message');

        // Clear previous messages
        $message.removeClass('wcl-error wcl-success').empty();

        // Show loading
        $form.find('.wcl-submit-btn').prop('disabled', true);
        
        // Send AJAX request
        $.ajax({
            url: wcl_ajax.ajaxurl,
            type: 'POST',
            data: $form.serialize(),
            success: function(response) {
                if (response.success) {
                    // Replace form with download button
                    $wrapper.html(response.data.html);
                } else {
                    // Show error message
                    $message
                        .addClass('wcl-error')
                        .html(response.data.message);
                }
            },
            error: function() {
                $message
                    .addClass('wcl-error')
                    .html('An error occurred. Please try again.');
            },
            complete: function() {
                $form.find('.wcl-submit-btn').prop('disabled', false);
            }
        });
    });
});