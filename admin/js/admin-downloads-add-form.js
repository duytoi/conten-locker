(function($) {
    'use strict';

    console.log('Script loaded');
    console.log('wcl_admin:', wcl_admin);
    console.log('jQuery:', 'loaded');

    class WCL_DownloadForm {
        constructor(options) {
            this.options = options;
            this.$form = $(options.formSelector);
            this.$messages = $(options.messagesSelector);
            this.$submit = $(options.submitButtonSelector);
            this.$spinner = $(options.spinnerSelector);
            this.$sourceRows = $(options.sourceRowSelector);
            this.$fileUpload = $(options.fileUploadSelector);
            this.$selectFileBtn = $('#select_file');
            this.$selectedFileName = $('.selected-file-name');
            this.$url = $(options.urlSelector);
            this.$fileUploadRow = $('#file_upload_row');
            this.$urlRow = $('#url_row');
            
            this.initializeHandlers();

            // Trigger initial source type
            const initialSourceType = $('input[name="source_type"]:checked').val() || 'file';
            this.toggleSourceFields(initialSourceType);
        }

        initializeHandlers() {
            console.log('Initializing event handlers...');

            // Source type change handler
            $('input[name="source_type"]').on('change', (e) => {
                const sourceType = e.target.value;
                console.log('Source type changed:', sourceType);
                this.toggleSourceFields(sourceType);
            });

            // File selection button handler
            this.$selectFileBtn.on('click', (e) => {
                e.preventDefault();
                this.$fileUpload.trigger('click');
            });

            // File input change handler
            this.$fileUpload.on('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    this.$selectedFileName.text(file.name);
                    this.$selectFileBtn.text('Change File');
                } else {
                    this.$selectedFileName.text('');
                    this.$selectFileBtn.text('Select File');
                }
            });

            // Form submit handler
            this.$form.on('submit', (e) => {
                e.preventDefault();
                this.handleSubmit(e);
            });
        }

        toggleSourceFields(sourceType) {
            console.log('Toggling source type:', sourceType);
            
            // Hide all source rows first
            this.$sourceRows.hide();
            
            if (sourceType === 'file') {
                this.$fileUploadRow.show();
                this.$urlRow.hide();
                this.$selectFileBtn.show(); // Show the select file button
            } else if (sourceType === 'url') {
                this.$fileUploadRow.hide();
                this.$urlRow.show();
                this.$selectFileBtn.hide(); // Hide the select file button
            }
        }

        handleSubmit(e) {
    e.preventDefault();
    console.log('Form submission started');

    // Basic validation
    const title = this.$form.find('#title').val();
    if (!title) {
        this.showMessage(wcl_admin.translations.title_required, 'error');
        return;
    }

    // Show loading state
    this.$submit.prop('disabled', true);
    this.$spinner.addClass('is-active');

    // Debug: Log form data before submission
    const formData = new FormData(this.$form[0]);
    formData.append('action', 'wcl_save_download');
    formData.append('security', wcl_admin.nonce);

    // Debug log
    console.log('Submitting form with data:', {
        title: formData.get('title'),
        description: formData.get('description'),
        category_id: formData.get('category_id'),
        source_type: formData.get('source_type'),
        file: formData.get('file_upload'),
        url: formData.get('url'),
        security: formData.get('security'),
        action: formData.get('action')
    });

    // Send AJAX request
    $.ajax({
        url: wcl_admin.ajax_url,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: (response) => {
            console.log('AJAX Response:', response);
            if (response.success) {
                this.showMessage(response.data.message || wcl_admin.translations.success, 'success');
                if (response.data.redirect) {
                    window.location.href = response.data.redirect;
                }
            } else {
                this.showMessage(response.data.message || wcl_admin.translations.error, 'error');
            }
        },
        error: (xhr, status, error) => {
            console.error('AJAX Error Details:', {
                status: xhr.status,
                statusText: xhr.statusText,
                responseText: xhr.responseText,
                error: error
            });
            this.showMessage(wcl_admin.translations.error, 'error');
        },
        complete: () => {
            this.$submit.prop('disabled', false);
            this.$spinner.removeClass('is-active');
        }
    });
}

        showMessage(message, type) {
            this.$messages.html(`
                <div class="wcl-message ${type}">
                    <p>${message}</p>
                </div>
            `);
        }
    }

    // Initialize when document is ready
    $(function() {
        console.log('Initializing form...');
        console.log('wcl_admin:', typeof wcl_admin !== 'undefined' ? 'defined' : 'not defined');
        console.log('WCL_DownloadForm:', typeof WCL_DownloadForm !== 'undefined' ? 'defined' : 'not defined');

        const downloadForm = new WCL_DownloadForm({
            formSelector: '#wcl-download-form',
            messagesSelector: '#wcl-messages',
            submitButtonSelector: '#submit',
            spinnerSelector: '.spinner',
            sourceRowSelector: '.source-row',
            fileUploadSelector: '#file_upload',
            urlSelector: '#url'
        });

        // Trigger initial source type check
        const initialSourceType = $('input[name="source_type"]:checked').val() || 'file';
        downloadForm.toggleSourceFields(initialSourceType);

        console.log('Form initialized successfully');
    });

})(jQuery);