class WCLCountdown {
    constructor(element) {
        this.element = element;
        this.time = parseInt(element.dataset.time, 10);
        this.resetOnLeave = element.dataset.reset === '1';
        this.completeAction = element.dataset.completeAction;
        this.targetId = element.dataset.target;
        
        this.minutesElement = element.querySelector('.minutes');
        this.secondsElement = element.querySelector('.seconds');
        this.progressBar = element.querySelector('.wcl-progress-bar');
        
		 // Thêm elements cho password
        this.passwordContainer = element.querySelector('.wcl-password-container');
        this.passwordDisplay = element.querySelector('.wcl-password-display');
		
        this.initialTime = this.time;
        this.timeLeft = this.time;
        this.interval = null;
        
        this.init();
    }

    init() {
        if (this.resetOnLeave) {
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    this.pause();
                } else {
                    this.reset();
                }
            });
        }

        this.start();
    }

    start() {
        this.interval = setInterval(() => {
            this.tick();
        }, 1000);
    }

    pause() {
        clearInterval(this.interval);
    }

    reset() {
        this.timeLeft = this.initialTime;
        this.updateDisplay();
        this.start();
    }

    tick() {
        if (this.timeLeft > 0) {
            this.timeLeft--;
            this.updateDisplay();
        } else {
            this.complete();
        }
    }

    updateDisplay() {
        const minutes = Math.floor(this.timeLeft / 60);
        const seconds = this.timeLeft % 60;
        
        this.minutesElement.textContent = minutes.toString().padStart(2, '0');
        this.secondsElement.textContent = seconds.toString().padStart(2, '0');
        
        if (this.progressBar) {
            const progress = ((this.initialTime - this.timeLeft) / this.initialTime) * 100;
            this.progressBar.style.width = `${progress}%`;
        }
    }

    complete() {
        clearInterval(this.interval);
        
        if (this.completeAction === 'unlock') {
            this.unlockContent();
        } else if (this.completeAction === 'redirect') {
            this.redirect();
        }
		
		if (this.completeAction === 'unlock') {
            this.getPassword();
        } else if (this.completeAction === 'redirect') {
            this.redirect();
        }
    }

    unlockContent() {
        const target = document.querySelector(`#${this.targetId}`);
        if (target) {
            target.style.display = 'block';
            this.element.style.display = 'none';
        }
    }

    redirect() {
        if (this.targetId) {
            window.location.href = this.targetId;
        }
    }
}

	getPassword() {
        // Hiển thị loading
        if (this.passwordContainer) {
            this.passwordContainer.innerHTML = '<div class="wcl-loading">Loading password...</div>';
        }

        // Gọi AJAX lấy mật khẩu
        jQuery.ajax({
            url: wcl_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'wcl_get_countdown_password',
                security: wcl_ajax.nonce
            },
            success: (response) => {
                if (response.success) {
                    this.displayPassword(response.data.password);
                } else {
                    this.showError(response.data.message);
                }
            },
            error: () => {
                this.showError(wcl_ajax.error_message);
            }
        });
    }
	displayPassword(password) {
        if (this.passwordContainer) {
            // Hiển thị container mật khẩu
            this.passwordContainer.innerHTML = `
                <div class="wcl-password-box">
                    <span class="wcl-password-label">Your Password:</span>
                    <span class="wcl-password-value">${password}</span>
                    <button class="wcl-copy-password" data-password="${password}">
                        Copy Password
                    </button>
                </div>
                <div class="wcl-password-instructions">
                    Use this password to unlock your download.
                </div>
            `;

            // Thêm event listener cho nút copy
            const copyButton = this.passwordContainer.querySelector('.wcl-copy-password');
            if (copyButton) {
                copyButton.addEventListener('click', (e) => {
                    const pass = e.target.dataset.password;
                    navigator.clipboard.writeText(pass).then(() => {
                        e.target.textContent = 'Copied!';
                        setTimeout(() => {
                            e.target.textContent = 'Copy Password';
                        }, 2000);
                    });
                });
            }

            // Hiển thị form unlock nếu có
            if (this.targetId) {
                const target = document.querySelector(`#${this.targetId}`);
                if (target) {
                    target.style.display = 'block';
                }
            }
        }
    }
	showError(message) {
        if (this.passwordContainer) {
            this.passwordContainer.innerHTML = `
                <div class="wcl-error-message">
                    ${message}
                    <button class="wcl-retry-button">Try Again</button>
                </div>
            `;

            // Thêm retry handler
            const retryButton = this.passwordContainer.querySelector('.wcl-retry-button');
            if (retryButton) {
                retryButton.addEventListener('click', () => {
                    this.getPassword();
                });
            }
        }
    }

// Initialize countdowns
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.wcl-countdown').forEach(element => {
        new WCLCountdown(element);
    });
});