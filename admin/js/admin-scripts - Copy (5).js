(function($) {
    'use strict';

    // 1. Constants and Utilities
    const WCLNotification = {
        success: function(message) {
            $('<div class="notice notice-success is-dismissible"><p>' + message + '</p></div>')
                .insertAfter('.wrap h1:first')
                .delay(3000)
                .fadeOut();
        },
        error: function(message) {
            $('<div class="notice notice-error is-dismissible"><p>' + message + '</p></div>')
                .insertAfter('.wrap h1:first');
        }
    };

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function deleteDownload(id) {
        $.ajax({
            url: wcl_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wcl_delete_download',
                download_id: id,
                nonce: wcl_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    WCLNotification.error(response.data.message);
                }
            },
            error: function() {
                WCLNotification.error(wcl_ajax.messages.error);
            }
        });
    }

    function processBulkAction(action, ids) {
        $.ajax({
            url: wcl_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wcl_bulk_action',
                bulk_action: action,
                download_ids: ids,
                nonce: wcl_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    WCLNotification.error(response.data.message);
                }
            },
            error: function() {
                WCLNotification.error(wcl_ajax.messages.error);
            }
        });
    }

    // Main initialization function
    function initializeWCL() {
        // 2. Tab Navigation
        $(document).on('click', '.nav-tab', function(e) {
            e.preventDefault();
            var tab = $(this).attr('href').split('tab=')[1];
            window.location.href = '?page=wcl-settings&tab=' + tab;
        });

        // 3. Form Handling
        $(document).on('submit', 'form', function() {
            return true;
        });

        // 4. File Upload
        $(document).on('change', '#download_file', function(e) {
            const file = e.target.files[0];
            if (file) {
                $('.wcl-file-upload .description').text(
                    `Selected file: ${file.name} (${formatFileSize(file.size)})`
                );
            }
        });

        // 5. Protection Settings
        $(document).on('change', 'input[name="protection_types[]"]', function() {
            const type = $(this).val();
            const settings = $(`.wcl-${type}-settings`);
            if ($(this).is(':checked')) {
                settings.slideDown();
            } else {
                settings.slideUp();
            }
        });

        // 6. Category Management
        $(document).on('click', '.wcl-add-category', function(e) {
            e.preventDefault();
            $('#wcl-category-modal').show();
        });

        $(document).on('click', '.wcl-modal-close', function() {
            $('#wcl-category-modal').hide();
        });

        $(document).on('submit', '#wcl-category-form', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'wcl_add_category');

            $.ajax({
                url: wcl_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        const option = new Option(response.data.name, response.data.id);
                        $('#category').append(option);
                        $('#wcl-category-modal').hide();
                        $('#wcl-category-form')[0].reset();
                        WCLNotification.success('Category added successfully');
                    } else {
                        WCLNotification.error(response.data.message);
                    }
                },
                error: function() {
                    WCLNotification.error(wcl_ajax.messages.error);
                }
            });
        });

        // 7. Download Management
        $(document).on('click', '.wcl-delete-download', function(e) {
            e.preventDefault();
            if (confirm(wcl_ajax.messages.confirm_delete)) {
                const downloadId = $(this).data('id');
                deleteDownload(downloadId);
            }
        });

        // Bulk Actions
		function processPasswordBulkAction(action, ids) {
    $.ajax({
        url: wcl_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'wcl_password_bulk_action',
            bulk_action: action,
            password_ids: ids,
            nonce: wcl_ajax.nonce
        },
        success: function(response) {
            if (response.success) {
                location.reload();
            } else {
                WCLNotification.error(response.data.message);
            }
        },
        error: function() {
            WCLNotification.error(wcl_ajax.messages.error);
        }
    });
}
		// Bulk Actions Handler
$(document).on('click', '.bulkactions input[type="submit"]', function(e) {
    e.preventDefault();
    
    // Debug để kiểm tra nonce
    console.log('Nonce values:', {
        wpnonce: $('#_wpnonce').val(),
        wclNonce: $('#wcl_nonce').val()
    });

    const $form = $('#passwords-list-form');
    const action = $(this).prev('select').val();
    const ids = [];

    $('input[name="passwords[]"]:checked').each(function() {
        ids.push($(this).val());
    });

    if (ids.length === 0) {
        alert(wcl_ajax.messages.no_items);
        return;
    }

    if (action === '-1') {
        alert('Please select an action.');
        return;
    }

    // Xác nhận khi xóa
    if (action === 'delete' && !confirm(wcl_ajax.messages.confirm_bulk_delete)) {
        return;
    }

    // Debug data trước khi gửi
    console.log('Sending data:', {
        action: 'wcl_password_bulk_action',
        bulk_action: action,
        password_ids: ids,
        _wpnonce: $('#_wpnonce').val(),
        wcl_nonce: $('#wcl_nonce').val()
    });

    // AJAX request
    $.ajax({
        url: wcl_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'wcl_password_bulk_action',
            bulk_action: action,
            password_ids: ids,
            _wpnonce: $('#_wpnonce').val(),
            wcl_nonce: $('#wcl_nonce').val()
        },
        beforeSend: function(xhr) {
            // Debug headers
            console.log('Ajax URL:', wcl_ajax.ajax_url);
            console.log('Request headers:', xhr.getAllResponseHeaders());
        },
        success: function(response) {
            console.log('Success response:', response);
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message || wcl_ajax.messages.error);
            }
        },
        error: function(xhr, status, error) {
            console.error('Ajax error:', {
                status: status,
                error: error,
                response: xhr.responseText
            });
            alert(wcl_ajax.messages.error);
        }
    });
});

        // 8. Protection Settings Page
        if ($('.wcl-protection-settings').length) {
            $(document).on('change', 'input[name="wcl_protection_settings[enable_password]"]', function() {
                $('.wcl-password-settings').toggle(this.checked);
            });

            $(document).on('change', 'input[name="wcl_protection_settings[enable_countdown]"]', function() {
                $('.wcl-countdown-settings').toggle(this.checked);
            });

            $(document).on('change', 'input[name="wcl_protection_settings[encrypt_files]"]', function() {
                if (this.checked) {
                    alert(wcl_ajax.messages.encryption_warning);
                }
            });
        }

        // 9. Settings Form (GA4 & GTM)
        $(document).on('submit', '#wcl-settings-form', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'wcl_save_settings');
            formData.append('nonce', wcl_ajax.nonce);
            formData.append('ga4_enabled', $('#ga4_enabled').is(':checked') ? 1 : 0);

            $.ajax({
                url: wcl_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        WCLNotification.success('Settings saved successfully');
                    } else {
                        WCLNotification.error('Failed to save settings');
                    }
                },
                error: function() {
                    WCLNotification.error('An error occurred while saving settings');
                }
            });
        });

        // 10. Initialize Tooltips
        if ($.fn.tooltip) {
            $('.wcl-tooltip').tooltip();
        }
    }

    // Modern way to handle document ready
    $(function() {
        initializeWCL();
    });

})(jQuery);