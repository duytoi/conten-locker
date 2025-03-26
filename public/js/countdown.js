/**
 * WCLCountdown Class
 * Handles countdown functionality with traffic verification and GA4/GTM tracking
 */
class WCLCountdown {
    /**
     * Constructor
     * @param {Object} settings Configuration settings
     */
    constructor(settings) {
    // Merge with global settings from localized data
    this.settings = {
        ...{
            // Default settings
            mode: 'single',
            firstTime: 0,
            secondTime: 0,
            protectionId: '',
            messages: {
                first: 'Please wait...',
                second: 'Almost there...',
                redirect: 'Redirecting...',
                success: 'Success!',
                error: 'An error occurred'
            },
            ga4Enabled: false,
            ga4MeasurementId: '',
            gtmContainerId: '',
            apiEndpoint: '',
            nonce: '',
            debug: false
        },
        ...wclCountdown, // Merge with localized WordPress data
        ...settings // Merge with instance settings
    };

    // Debug logging
    if (this.settings.debug) {
        console.log('WCLCountdown initialized with settings:', this.settings);
        console.log('REST API Endpoint:', this.settings.apiEndpoint);
        console.log('Site URL:', this.settings.siteUrl);
        console.log('Base URL:', this.settings.baseUrl);
    }

    // Initialize state
    this.state = {
        isActive: false,
        isPaused: false,
        currentTime: this.settings.mode === 'double' ? this.settings.firstTime : this.settings.countdown_time,
        timerInterval: null,
        stage: 'first',
        verificationToken: null,
        clientId: null
    };

    // Find DOM elements
    try {
        this.elements = {
            container: document.querySelector('.wcl-countdown'),
            timerDisplay: document.querySelector('.wcl-countdown-timer'),
            messageDisplay: document.querySelector('.wcl-countdown-message'),
            passwordContainer: document.querySelector('.wcl-password-container'),
            loadingIndicator: document.querySelector('.wcl-loading-indicator'),
            errorDisplay: document.querySelector('.wcl-error-message')
        };

        if (!this.elements.container || !this.elements.timerDisplay || !this.elements.messageDisplay) {
            throw new Error('Required DOM elements not found');
        }
    } catch (error) {
        console.error('DOM initialization error:', error);
        return;
    }

    // Bind methods to maintain context
    this.boundMethods = {
        handleVisibilityChange: this.handleVisibilityChange.bind(this),
        startCountdown: this.startCountdown.bind(this),
        updateTimer: this.updateTimer.bind(this),
        completeCountdown: this.completeCountdown.bind(this),
        verifyTraffic: this.verifyTraffic.bind(this),
        handleError: this.handleError.bind(this)
    };

    // Add event listeners
    try {
        // Page visibility
        document.addEventListener('visibilitychange', this.boundMethods.handleVisibilityChange);

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            this.cleanup();
        });

        // Handle API errors
        window.addEventListener('unhandledrejection', (event) => {
            if (event.reason && event.reason.isApiError) {
                this.handleError(event.reason);
                event.preventDefault();
            }
        });
    } catch (error) {
        console.error('Event listener initialization error:', error);
    }

    // Initialize analytics
    this.initializeAnalytics()
        .then(() => {
            // Start the countdown process
            this.init();
        })
        .catch(error => {
            console.error('Analytics initialization error:', error);
            // Continue with initialization even if analytics fails
            this.init();
        });
}

// Add supporting methods:
async initializeAnalytics() {
    if (this.settings.ga4Enabled) {
        try {
            // Initialize GA4
            if (this.settings.ga4MeasurementId) {
                await this.loadGA4();
            }
            
            // Initialize GTM
            if (this.settings.gtmContainerId) {
                await this.loadGTM();
            }
            
            // Get or generate client ID
            this.state.clientId = await this.getClientId();
            
            if (this.settings.debug) {
                console.log('Analytics initialized successfully');
                console.log('Client ID:', this.state.clientId);
            }
        } catch (error) {
            console.error('Analytics initialization error:', error);
            throw error;
        }
    }
}

cleanup() {
    // Clear intervals
    if (this.state.timerInterval) {
        clearInterval(this.state.timerInterval);
    }

    // Remove event listeners
    document.removeEventListener('visibilitychange', this.boundMethods.handleVisibilityChange);

    // Clear any stored data
    if (this.settings.debug) {
        console.log('Cleanup completed');
    }
}

handleError(error) {
    // Log error
    console.error('WCLCountdown error:', error);

    // Display error to user
    if (this.elements.errorDisplay) {
        this.elements.errorDisplay.textContent = this.settings.messages.error;
        this.elements.errorDisplay.style.display = 'block';
    }

    // Track error event
    if (this.settings.ga4Enabled) {
        this.trackEvent('countdown_error', {
            error_message: error.message,
            error_code: error.code || 'unknown'
        });
    }
}

buildApiUrl(endpoint) {
    // Ensure we have a valid base URL
    const baseUrl = this.settings.siteUrl || window.location.origin;
    
    // Construct the full URL
    let apiUrl = `${baseUrl}${this.settings.baseUrl}/wp-json/wp-content-locker/${this.settings.apiVersion}/${endpoint}`;
    
    if (this.settings.debug) {
        console.log('Built API URL:', apiUrl);
    }
    
    return apiUrl;
}

    /**
     * Initialize countdown functionality
     */
    init() {
        console.log('Initializing countdown with settings:', this.settings);

        // Set initial stage and time
        if (this.settings.mode === 'double') {
            this.currentTime = this.settings.firstTime;
            this.messageDisplay.textContent = this.settings.messages.first;
        } else {
            this.currentTime = this.settings.countdown_time;
            this.messageDisplay.textContent = this.settings.messages.first;
        }

        // Update display
        this.updateTimerDisplay();

        // Start verification process
        if (this.settings.ga4Enabled) {
            this.initGA4AndGTM().then(() => this.verifyTraffic());
        } else {
            this.verifyTraffic();
        }

        // Add visibility change listener
        document.addEventListener('visibilitychange', this.handleVisibilityChange);
    }

    /**
     * Initialize GA4 and GTM
     */
    async initGA4AndGTM() {
        try {
            if (this.settings.gtmContainerId) {
                await this.loadGTM();
            }
            if (this.settings.ga4MeasurementId && !this.settings.gtmContainerId) {
                await this.loadGA4();
            }
        } catch (error) {
            console.error('Analytics initialization error:', error);
        }
    }

    /**
     * Load Google Tag Manager
     */
    loadGTM() {
        return new Promise((resolve) => {
            (function(w,d,s,l,i){
                w[l]=w[l]||[];
                w[l].push({'gtm.start': new Date().getTime(), event:'gtm.js'});
                var f=d.getElementsByTagName(s)[0],
                j=d.createElement(s),
                dl=l!='dataLayer'?'&l='+l:'';
                j.async=true;
                j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;
                j.onload = resolve;
                f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer',this.settings.gtmContainerId);
        });
    }

    /**
     * Load GA4
     */
    loadGA4() {
        return new Promise((resolve) => {
            const script = document.createElement('script');
            script.src = `https://www.googletagmanager.com/gtag/js?id=${this.settings.ga4MeasurementId}`;
            script.async = true;
            script.onload = () => {
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());
                gtag('config', this.settings.ga4MeasurementId);
                resolve();
            };
            document.head.appendChild(script);
        });
    }

    /**
     * Verify traffic before starting countdown
     */
    async verifyTraffic() {
		
		// Thêm debug log này vào đầu hàm verifyTraffic
console.log('WCL Settings:', {
    apiEndpoint: wclCountdown.apiEndpoint,
    siteUrl: wclCountdown.siteUrl,
    baseUrl: wclCountdown.baseUrl
});
        try {
            this.showLoading();
            const clientId = await this.getClientId();
            // Build correct API URL using baseUrl
			const apiUrl = `${wclCountdown.apiEndpoint}/verify-traffic`;
			console.log('Making API request to:', apiUrl);
            console.log('Verifying traffic with client ID:', clientId);

            const response = await fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': wclCountdown.nonce
            },
            body: JSON.stringify({
                protection_id: this.settings.protectionId,
                gtm_data: await this.getGTMData(),
                api_version: wclCountdown.apiVersion
            }),
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
            
            if (data.success) {
                this.verificationToken = data.token;
                this.isActive = true;
                this.startCountdown();
                
                this.trackEvent('countdown_started', {
                    protection_id: this.settings.protectionId,
                    verification_token: this.verificationToken
                });
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
     * Start the countdown
     */
    startCountdown() {
        if (!this.isActive || this.isPaused) return;

        this.timerInterval = setInterval(() => {
            if (this.currentTime > 0) {
                this.currentTime--;
                this.updateTimerDisplay();
            } else {
                this.completeCountdown();
            }
        }, 1000);

        this.trackEvent('countdown_stage_started', {
            stage: this.stage,
            duration: this.currentTime
        });
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
     * Handle countdown completion
     */
    completeCountdown() {
        clearInterval(this.timerInterval);

        if (this.settings.mode === 'double' && this.stage === 'first') {
            this.stage = 'second';
            this.currentTime = this.settings.secondTime;
            this.messageDisplay.textContent = this.settings.messages.second;
            this.startCountdown();
        } else {
            this.showComplete();
        }

        this.trackEvent('countdown_stage_completed', {
            stage: this.stage
        });
    }

    /**
     * Show completion message and handle redirect if needed
     */
    showComplete() {
        this.messageDisplay.textContent = this.settings.messages.redirect;
        this.container.classList.add('wcl-countdown-complete');

        if (this.settings.redirectUrl) {
            setTimeout(() => {
                window.location.href = this.settings.redirectUrl;
            }, 1000);
        }

        this.trackEvent('countdown_completed');
    }

    /**
     * Handle page visibility changes
     */
    handleVisibilityChange() {
        if (document.hidden) {
            this.isPaused = true;
            clearInterval(this.timerInterval);
            this.trackEvent('countdown_paused');
        } else {
            this.isPaused = false;
            this.startCountdown();
            this.trackEvent('countdown_resumed');
        }
    }

    /**
     * Get GA Client ID
     */
    async getClientId() {
        let clientId = localStorage.getItem('wcl_client_id');
        
        if (!clientId) {
            if (typeof gtag !== 'undefined') {
                try {
                    clientId = await new Promise((resolve) => {
                        gtag('get', this.settings.ga4MeasurementId, 'client_id', resolve);
                    });
                } catch (error) {
                    console.warn('Failed to get GA4 client ID:', error);
                }
            }
            
            if (!clientId) {
                clientId = 'wcl_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            }
            
            localStorage.setItem('wcl_client_id', clientId);
        }
        
        return clientId;
    }

    /**
     * Get GTM Data
     */
    async getGTMData() {
        const clientId = await this.getClientId();
        
        return {
            client_id: clientId,
            page_url: window.location.href,
            page_title: document.title,
            referrer: document.referrer,
            user_agent: navigator.userAgent,
            screen_resolution: `${window.screen.width}x${window.screen.height}`,
            viewport_size: `${window.innerWidth}x${window.innerHeight}`,
            timestamp: new Date().toISOString(),
            protection_id: this.settings.protectionId,
            traffic_source: this.getTrafficSource()
        };
    }

    /**
     * Get traffic source
     */
    getTrafficSource() {
        const referrer = document.referrer;
        const urlParams = new URLSearchParams(window.location.search);
        
        if (urlParams.get('utm_source')) {
            return urlParams.get('utm_source');
        }
        
        if (referrer) {
            const referrerDomain = new URL(referrer).hostname;
            if (referrerDomain.includes('google')) {
                return 'google';
            }
            return referrerDomain;
        }
        
        return 'direct';
    }

    /**
     * Track event to GA4/GTM
     */
    trackEvent(eventName, params = {}) {
        if (!this.settings.ga4Enabled) return;

        const baseParams = {
            protection_id: this.settings.protectionId,
            countdown_mode: this.settings.mode,
            stage: this.stage,
            client_id: localStorage.getItem('wcl_client_id'),
            event_time: new Date().toISOString()
        };

        const eventParams = {
            ...baseParams,
            ...params
        };

        // Track via GTM
        if (window.dataLayer) {
            window.dataLayer.push({
                event: eventName,
                ...eventParams
            });
        }

        // Track via GA4 directly if available
        if (typeof gtag !== 'undefined') {
            gtag('event', eventName, eventParams);
        }
    }

    /**
     * Show loading state
     */
    showLoading() {
        this.container.classList.add('wcl-loading');
    }

    /**
     * Hide loading state
     */
    hideLoading() {
        this.container.classList.remove('wcl-loading');
    }

    /**
     * Show error message
     */
    showError(message) {
        this.messageDisplay.textContent = message;
        this.container.classList.add('wcl-error');
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
            gtmContainerId: element.dataset.gtmContainerId,
            ajaxUrl: wclCountdown.ajaxUrl,
            nonce: wclCountdown.nonce,
            apiEndpoint: wclCountdown.apiEndpoint || '/wp-json/wp-content-locker/v2'
        };

        new WCLCountdown(settings);
    });
});
