/**
 * WCLCountdown Class
 * Handles countdown functionality with traffic verification and GA4 tracking
 */
class WCLCountdown {
    constructor(settings) {
        // Initialize settings
        this.settings = settings;
        this.isActive = false;
        this.isPaused = false;
        this.currentTime = 0;
        this.timerInterval = null;
        this.stage = 'first';
        this.verificationToken = null;

        // DOM elements
        this.container = document.querySelector('.wcl-countdown');
        this.timerDisplay = document.querySelector('.wcl-countdown-timer');
        this.messageDisplay = document.querySelector('.wcl-countdown-message');
        this.passwordContainer = document.querySelector('.wcl-password-container');

        // Bind methods
        this.handleVisibilityChange = this.handleVisibilityChange.bind(this);
        this.startCountdown = this.startCountdown.bind(this);
        this.updateTimer = this.updateTimer.bind(this);
        this.completeCountdown = this.completeCountdown.bind(this);

        // Start verification process
        this.verifyTraffic();
    }

    /**
     * Verify traffic through API
     */
    async verifyTraffic() {
    try {
        this.showLoading();
        const clientId = await this.getClientId();
        
        console.log('Verifying traffic with client ID:', clientId); // Debug log

        const apiUrl = `${this.settings.apiEndpoint}/verify-traffic`;
        
        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': this.settings.nonce
            },
            body: JSON.stringify({
                protection_id: this.settings.protectionId,
                client_id: clientId,
                gtm_data: await this.getGTMData()
            })
        });

        console.log('API Response status:', response.status); // Debug log

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        console.log('API Response data:', data); // Debug log
        
        if (data.success) {
            this.verificationToken = data.token;
            this.isActive = true;
            this.init();
            
            if (this.settings.ga4Enabled) {
                this.trackEvent('traffic_verified', {
                    protection_id: this.settings.protectionId
                });
            }
        } else {
            this.showError(data.message || 'Traffic verification failed');
        }
    } catch (error) {
        console.error('Traffic verification error:', error);
        this.showError('Unable to verify traffic. Please try again.');
    } finally {
        this.hideLoading();
    }
}

    /**
     * Initialize countdown after verification
     */
    init() {
        if (!this.isActive) return;

        document.addEventListener('visibilitychange', this.handleVisibilityChange);
        this.messageDisplay.textContent = this.settings.messages.first;
        this.currentTime = parseInt(this.settings.firstTime);
        this.startCountdown();

        if (this.settings.ga4Enabled) {
            this.initGA4();
        }
    }

    /**
     * Initialize Google Analytics 4
     */
    initGA4() {
        if (typeof gtag !== 'undefined') {
            gtag('config', this.settings.ga4MeasurementId);
        }
    }

    /**
     * Handle page visibility changes
     */
    handleVisibilityChange() {
        if (document.hidden) {
            this.pauseCountdown();
        } else {
            this.resumeCountdown();
        }
    }

    /**
     * Start countdown timer
     */
    startCountdown() {
        if (this.timerInterval) {
            clearInterval(this.timerInterval);
        }

        this.updateTimerDisplay();
        
        this.timerInterval = setInterval(() => {
            if (!this.isPaused) {
                this.updateTimer();
            }
        }, 1000);
    }

    /**
     * Update countdown timer
     */
    updateTimer() {
        if (this.currentTime > 0) {
            this.currentTime--;
            this.updateTimerDisplay();
            
            if (this.settings.ga4Enabled && this.currentTime % 10 === 0) {
                this.trackProgress();
            }
        } else {
            this.completeCountdown();
        }
    }

    /**
     * Update timer display
     */
    updateTimerDisplay() {
        const minutes = Math.floor(this.currentTime / 60);
        const seconds = this.currentTime % 60;
        this.timerDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
    }

    /**
     * Pause countdown
     */
    pauseCountdown() {
        this.isPaused = true;
        if (this.settings.ga4Enabled) {
            this.trackEvent('countdown_paused', {
                stage: this.stage,
                remaining_time: this.currentTime
            });
        }
    }

    /**
     * Resume countdown
     */
    resumeCountdown() {
        this.isPaused = false;
        if (this.settings.ga4Enabled) {
            this.trackEvent('countdown_resumed', {
                stage: this.stage,
                remaining_time: this.currentTime
            });
        }
    }

    /**
     * Handle countdown completion
     */
    completeCountdown() {
        clearInterval(this.timerInterval);

        if (this.stage === 'first') {
            if (this.settings.mode === 'double') {
                this.handleFirstCountdownComplete();
            } else {
                this.handleFinalCountdownComplete();
            }
        } else {
            this.handleFinalCountdownComplete();
        }
    }

    /**
     * Handle first countdown completion
     */
    handleFirstCountdownComplete() {
        this.stage = 'second';
        this.messageDisplay.textContent = this.settings.messages.redirect;
        
        if (this.settings.ga4Enabled) {
            this.trackEvent('first_countdown_complete');
        }

        document.addEventListener('click', (e) => {
            if (e.target.tagName === 'A') {
                this.startSecondCountdown();
            }
        }, { once: true });
    }

    /**
     * Start second countdown
     */
    startSecondCountdown() {
        this.currentTime = parseInt(this.settings.secondTime);
        this.messageDisplay.textContent = this.settings.messages.second;
        this.startCountdown();
    }

    /**
     * Handle final countdown completion
     */
    handleFinalCountdownComplete() {
        if (this.settings.ga4Enabled) {
            this.trackEvent('countdown_complete');
        }

        this.getPassword();
    }

    /**
     * Get password via AJAX
     */
    getPassword() {
        const data = {
            action: 'wcl_get_password',
            nonce: this.settings.nonce,
            protection_id: this.settings.protectionId,
            verification_token: this.verificationToken
        };

        jQuery.post(this.settings.ajaxUrl, data)
            .done((response) => {
                if (response.success) {
                    this.showPassword(response.data.password);
                } else {
                    this.showError(response.data.message);
                }
            })
            .fail(() => {
                this.showError('An error occurred. Please try again.');
            });
    }

    /**
     * Show password in container
     */
    showPassword(password) {
        this.messageDisplay.style.display = 'none';
        this.timerDisplay.style.display = 'none';
        this.passwordContainer.style.display = 'block';
        this.passwordContainer.innerHTML = `
            <div class="wcl-password-reveal">
                <p class="wcl-success-message">${this.settings.messages.success}</p>
                <div class="wcl-password-box">${password}</div>
            </div>
        `;
    }

    /**
     * Show error message
     */
    showError(message) {
        this.messageDisplay.textContent = message;
        this.messageDisplay.classList.add('wcl-error');
    }

    /**
     * Show loading state
     */
    showLoading() {
        this.container.classList.add('loading');
        this.messageDisplay.textContent = 'Verifying...';
    }

    /**
     * Hide loading state
     */
    hideLoading() {
        this.container.classList.remove('loading');
    }

    /**
     * Track GA4 event
     */
    trackEvent(eventName, params = {}) {
        if (typeof gtag !== 'undefined') {
            gtag('event', eventName, {
                ...params,
                protection_id: this.settings.protectionId,
                countdown_mode: this.settings.mode
            });
        }
    }

    /**
     * Track countdown progress
     */
    trackProgress() {
        this.trackEvent('countdown_progress', {
            stage: this.stage,
            remaining_time: this.currentTime,
            percentage_complete: Math.round(((this.settings.firstTime - this.currentTime) / this.settings.firstTime) * 100)
        });
    }

    /**
     * Get GTM data
     */
    getGTMData() {
        return {
            client_id: this.getClientId(),
            page_url: window.location.href,
            referrer: document.referrer,
            user_agent: navigator.userAgent
        };
    }

    /**
     * Get/Generate client ID
     */
    getClientId() {
    return new Promise((resolve) => {
        // Try GA4
        if (typeof gtag !== 'undefined') {
            gtag('get', this.settings.ga4MeasurementId, 'client_id', (clientId) => {
                resolve(clientId);
            });
            return;
        }

        // Try localStorage fallback
        let clientId = localStorage.getItem('wcl_client_id');
        if (!clientId) {
            clientId = 'wcl_' + Math.random().toString(36).substr(2, 9);
            localStorage.setItem('wcl_client_id', clientId);
        }
        resolve(clientId);
    });
}

    /**
     * Cleanup
     */
    destroy() {
        document.removeEventListener('visibilitychange', this.handleVisibilityChange);
        if (this.timerInterval) {
            clearInterval(this.timerInterval);
        }
    }
}

// Initialize countdown when DOM is ready
jQuery(document).ready(function($) {
    const countdownElements = document.querySelectorAll('.wcl-countdown');
    
    countdownElements.forEach(element => {
        const settings = {
            mode: element.dataset.mode,
            firstTime: parseInt(element.dataset.firstTime),
            secondTime: parseInt(element.dataset.secondTime),
            protectionId: element.dataset.protectionId,
            messages: {
                first: element.dataset.firstMessage,
                second: element.dataset.secondMessage,
                redirect: element.dataset.redirectMessage,
                success: element.dataset.successMessage,
                error: element.dataset.errorMessage || 'An error occurred'
            },
            ga4Enabled: element.dataset.ga4Enabled === 'true',
            ga4MeasurementId: element.dataset.ga4MeasurementId,
            ajaxUrl: wclCountdown.ajaxUrl,
            nonce: wclCountdown.nonce,
            apiEndpoint: wclCountdown.apiEndpoint || '/wp-json/wp-content-locker/v1'
        };

        new WCLCountdown(settings);
    });
});

// Add CSS styles
const styles = `
    .wcl-countdown {
        max-width: 600px;
        margin: 20px auto;
        padding: 20px;
        text-align: center;
        background: #f9f9f9;
        border-radius: 5px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        transition: opacity 0.3s ease;
    }

    .wcl-countdown.loading {
        opacity: 0.7;
        pointer-events: none;
    }

    .wcl-countdown-timer {
        font-size: 2em;
        font-weight: bold;
        color: #333;
        margin: 10px 0;
    }

    .wcl-countdown-message {
        margin: 15px 0;
        color: #666;
    }

    .wcl-error {
        color: #dc3545;
    }

    .wcl-loading-message {
        color: #666;
        font-style: italic;
    }

    .wcl-password-container {
        display: none;
    }

    .wcl-password-reveal {
        background: #fff;
        padding: 20px;
        border-radius: 5px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .wcl-password-box {
        font-size: 1.5em;
        font-weight: bold;
        padding: 10px;
        background: #e9ecef;
        border-radius: 3px;
        margin: 10px 0;
    }

    .wcl-success-message {
        color: #28a745;
        margin-bottom: 15px;
    }
`;

// Add styles to head
const styleSheet = document.createElement("style");
styleSheet.textContent = styles;
document.head.appendChild(styleSheet);