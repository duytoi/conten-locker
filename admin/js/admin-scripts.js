(function($) {
    'use strict';

    // Constants and Utilities
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
        },
        loading: function() {
            return $('<div id="wcl-loading" class="wcl-loading"><span class="spinner is-active"></span></div>')
                .appendTo('body');
        }
    };

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // AJAX Actions
    const WCLAjax = {
        deleteDownload: function(id) {
            $.ajax({
                url: wcl_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wcl_delete_download',
                    download_id: id,
                    nonce: wcl_ajax.nonce
                },
                beforeSend: function() {
                    WCLNotification.loading();
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
                },
                complete: function() {
                    $('#wcl-loading').remove();
                }
            });
        },

        processBulkAction: function(action, ids) {
            $.ajax({
                url: wcl_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wcl_password_bulk_action',
                    bulk_action: action,
                    password_ids: ids,
                    nonce: wcl_ajax.nonce
                },
                beforeSend: function() {
                    WCLNotification.loading();
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        WCLNotification.error(response.data.message || wcl_ajax.messages.error);
                    }
                },
                error: function() {
                    WCLNotification.error(wcl_ajax.messages.error);
                },
                complete: function() {
                    $('#wcl-loading').remove();
                }
            });
        }
    };

    function initializeWCL() {
        // Tab Navigation
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            var tab = $(this).attr('href').split('tab=')[1];
            window.location.href = '?page=wcl-settings&tab=' + tab;
        });

        // Bulk Actions Handler - FIX CHO FILTER/SEARCH
        $('#doaction, #doaction2').on('click', function(e) {
            const buttonValue = $(this).val();
            const buttonType = $(this).attr('type');
            
            // Cho phép Filter và Search hoạt động bình thường
            if (buttonValue === 'Filter' || 
                buttonValue === 'filter' || 
                $(this).hasClass('button-primary') ||
                buttonType === 'submit') {
                return true;
            }

            // Xử lý bulk actions
            const bulkSelect = $(this).prev('select');
            const action = bulkSelect.val();

            if (action === '-1') {
                alert('Please select an action.');
                return false;
            }

            const checkedBoxes = $('input[name="passwords[]"]:checked');
            if (checkedBoxes.length === 0) {
                alert('Please select items first.');
                return false;
            }

            if (action === 'delete') {
                return confirm(wcl_ajax.messages.confirm_bulk_delete);
            }
        });

        // File Upload Handler
        $('#download_file').on('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                $('.wcl-file-upload .description').text(
                    `Selected file: ${file.name} (${formatFileSize(file.size)})`
                );
            }
        });

        // Protection Settings
        $('input[name="protection_types[]"]').on('change', function() {
            const type = $(this).val();
            const settings = $(`.wcl-${type}-settings`);
            $(this).is(':checked') ? settings.slideDown() : settings.slideUp();
        });

        // Category Management
        $('.wcl-add-category').on('click', function(e) {
            e.preventDefault();
            $('#wcl-category-modal').show();
        });

        $('.wcl-modal-close').on('click', function() {
            $('#wcl-category-modal').hide();
        });

        // Category Form Submit
        $('#wcl-category-form').on('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'wcl_add_category');
            formData.append('nonce', wcl_ajax.nonce);

            $.ajax({
                url: wcl_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    WCLNotification.loading();
                },
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
                },
                complete: function() {
                    $('#wcl-loading').remove();
                }
            });
        });

        // Download Management
        $('.wcl-delete-download').on('click', function(e) {
            e.preventDefault();
            if (confirm(wcl_ajax.messages.confirm_delete)) {
                const downloadId = $(this).data('id');
                WCLAjax.deleteDownload(downloadId);
            }
        });

        // Settings Page Handlers
        if ($('.wcl-protection-settings').length) {
            $('input[name="wcl_protection_settings[enable_password]"]').on('change', function() {
                $('.wcl-password-settings').toggle(this.checked);
            });

            $('input[name="wcl_protection_settings[enable_countdown]"]').on('change', function() {
                $('.wcl-countdown-settings').toggle(this.checked);
            });

            $('input[name="wcl_protection_settings[encrypt_files]"]').on('change', function() {
                if (this.checked) {
                    alert(wcl_ajax.messages.encryption_warning);
                }
            });
        }

        // Settings Form Handler
        $('#wcl-settings-form').on('submit', function(e) {
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
                beforeSend: function() {
                    WCLNotification.loading();
                },
                success: function(response) {
                    if (response.success) {
                        WCLNotification.success('Settings saved successfully');
                    } else {
                        WCLNotification.error('Failed to save settings');
                    }
                },
                error: function() {
                    WCLNotification.error('An error occurred while saving settings');
                },
                complete: function() {
                    $('#wcl-loading').remove();
                }
            });
        });

        // Initialize Tooltips
        if ($.fn.tooltip) {
            $('.wcl-tooltip').tooltip();
        }

        // Đảm bảo form filter/search luôn submit được
        $('.search-box input[type="submit"], .filter_action').on('click', function() {
            return true;
        });
    }

    // Initialize when document is ready
    $(function() {
        initializeWCL();
    });

})(jQuery);