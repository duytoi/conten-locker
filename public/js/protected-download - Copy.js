class WCLProtectedDownload {
    constructor(container) {
        this.container = container;
        this.downloadId = container.dataset.downloadId;
        this.countdown = null;
        this.init();
    }

    init() {
        // Start download button
        this.container.querySelector('.wcl-start-download')
            .addEventListener('click', () => this.startDownload());

        // Password form
        this.container.querySelector('.wcl-password-form')
            .addEventListener('submit', (e) => this.handlePasswordSubmit(e));
    }

    startDownload() {
        this.container.querySelector('.wcl-download-initial').style.display = 'none';
        this.container.querySelector('.wcl-countdown-container').style.display = 'block';
        this.startCountdown();
    }

    startCountdown() {
        const countdownElement = this.container.querySelector('.wcl-countdown');
        this.countdown = new WCLCountdown(countdownElement);
        this.countdown.onComplete = () => this.handleCountdownComplete();
    }

    handleCountdownComplete() {
        this.getPassword();
    }

    getPassword() {
        const passwordContainer = this.container.querySelector('.wcl-password-container');
        passwordContainer.style.display = 'block';
        
        jQuery.ajax({
            url: wclDownload.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wcl_get_countdown_password',
                security: wclDownload.nonce,
                download_id: this.downloadId
            },
            success: (response) => {
                if (response.success) {
                    this.showPassword(response.data.password);
                } else {
                    this.showError(response.data.message);
                }
            },
            error: () => {
                this.showError(wclDownload.messages.error);
            }
        });
    }

    showPassword(password) {
        const passwordContainer = this.container.querySelector('.wcl-password-container');
        passwordContainer.innerHTML = `
            <div class="wcl-password-box">
                <p>${wclDownload.messages.countdownComplete}</p>
                <div class="wcl-password-value">${password}</div>
            </div>
        `;
        
        // Show password form
        this.container.querySelector('.wcl-password-form-container').style.display = 'block';
    }

    handlePasswordSubmit(e) {
        e.preventDefault();
        const password = this.container.querySelector('.wcl-password-input').value;
        
        jQuery.ajax({
            url: wclDownload.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wcl_verify_countdown_password',
                security: wclDownload.nonce,
                password: password,
                download_id: this.downloadId
            },
            success: (response) => {
                if (response.success) {
                    this.showDownloadLink(response.data.download_url);
                } else {
                    this.showError(response.data.message);
                }
            },
            error: () => {
                this.showError(wclDownload.messages.error);
            }
        });
    }

    showDownloadLink(url) {
        this.container.querySelector('.wcl-password-form-container').style.display = 'none';
        const downloadLink = this.container.querySelector('.wcl-download-link');
        downloadLink.style.display = 'block';
        downloadLink.querySelector('.wcl-download-button').href = url;
    }

    showError(message) {
        const messageElement = this.container.querySelector('.wcl-message');
        messageElement.innerHTML = message;
        messageElement.className = 'wcl-message error';
    }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.wcl-protected-download').forEach(container => {
        new WCLProtectedDownload(container);
    });
});