class WCLDownloadHandler {
    constructor() {
        this.initializeEventListeners();
    }

    initializeEventListeners() {
        // Password protection form submission
        document.querySelectorAll('.wcl-download-password-form').forEach(form => {
            form.addEventListener('submit', (e) => this.handlePasswordSubmit(e));
        });

        // Download button click
        document.querySelectorAll('.wcl-download-button').forEach(button => {
            button.addEventListener('click', (e) => this.handleDownloadClick(e));
        });
    }

    handlePasswordSubmit(e) {
        e.preventDefault();
        const form = e.target;
        const downloadId = form.querySelector('input[name="download_id"]').value;
        const password = form.querySelector('input[name="wcl_password"]').value;
        const messageEl = form.closest('.wcl-download-wrapper').querySelector('.wcl-message');

        this.verifyDownload(downloadId, {
            password: password
        }).then(response => {
            if (response.success) {
                this.initiateDownload(response.data.download_url, downloadId);
                this.showMessage(messageEl, response.data.message, 'success');
            } else {
                this.showMessage(messageEl, response.data.message, 'error');
            }
        }).catch(error => {
            this.showMessage(messageEl, 
                           wcl_ajax.messages.error, 
                           'error');
        });
    }

    handleDownloadClick(e) {
        const button = e.target.closest('.wcl-download-button');
        const downloadId = button.closest('.wcl-download-wrapper').dataset.id;

        // Log the download
        this.logDownload(downloadId);
    }

    verifyDownload(downloadId, data = {}) {
        return new Promise((resolve, reject) => {
            const formData = new FormData();
            formData.append('action', 'wcl_verify_download');
            formData.append('download_id', downloadId);
            formData.append('nonce', wcl_ajax.nonce);

            // Add additional data
            Object.keys(data).forEach(key => {
                formData.append(key, data[key]);
            });

            fetch(wcl_ajax.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(resolve)
            .catch(reject);
        });
    }

    logDownload(downloadId) {
        const formData = new FormData();
        formData.append('action', 'wcl_log_download');
        formData.append('download_id', downloadId);
        formData.append('nonce', wcl_ajax.nonce);

        fetch(wcl_ajax.ajax_url, {
            method: 'POST',
            body: formData
        }).catch(error => {
            console.error('Failed to log download:', error);
        });
    }

    initiateDownload(url, downloadId) {
        // Create hidden download link
        const link = document.createElement('a');
        link.href = url;
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        // Log the download
        this.logDownload(downloadId);
    }

    showMessage(element, message, type) {
        element.textContent = message;
        element.className = `wcl-message ${type}`;
        element.style.display = 'block';

        // Hide message after 5 seconds
        setTimeout(() => {
            element.style.display = 'none';
        }, 5000);
    }
}

// Initialize handler when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new WCLDownloadHandler();
});