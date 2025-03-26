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
        // Debug log for initialization
        if (settings.debug) {
            console.log('WCL Settings:', {
                apiEndpoint: wclCountdown?.apiEndpoint,
                siteUrl: wclCountdown?.siteUrl,
                baseUrl: wclCountdown?.baseUrl
            });
        }

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
                apiEndpoint: wclCountdown?.apiEndpoint || '/wp-json/wp-content-locker/v2', // Changed to v2
                nonce: wclCountdown?.nonce || '',
                debug: false,
                redirectUrl: ''
            },
            ...wclCountdown, // Merge with localized WordPress data
            ...settings // Merge with instance settings
        };

        // Initialize state
        this.state = {
            isActive: false,
            isPaused: false,
            currentTime: this.settings.mode === 'double' ? 
                        this.settings.firstTime : 
                        (this.settings.countdown_time || 60),
            timerInterval: null,
            stage: 'first',
            verificationToken: null,
            clientId: null,
            isVerified: false,
            hasError: false
        };

        // Find and validate DOM elements
        this.initializeDOMElements();

        // Bind methods to maintain context
        this.bindMethods();

        // Add event listeners
        this.addEventListeners();

        // Initialize analytics and start countdown
        this.initializeAnalytics()
            .then(() => {
                if (this.settings.debug) {
                    console.log('Analytics initialized successfully');
                }
                this.init();
            })
            .catch(error => {
                console.error('Analytics initialization error:', error);
                this.init(); // Continue without analytics
            });
    }

    /**
     * Initialize DOM elements
     */
    initializeDOMElements() {
        try {
            this.elements = {
                container: document.querySelector('.wcl-countdown'),
                timerDisplay: document.querySelector('.wcl-countdown-timer'),
                messageDisplay: document.querySelector('.wcl-countdown-message'),
                passwordContainer: document.querySelector('.wcl-password-container'),
                loadingIndicator: document.querySelector('.wcl-loading-indicator'),
                errorDisplay: document.querySelector('.wcl-error-message')
            };

            // Validate required elements
            if (!this.elements.container || !this.elements.timerDisplay || !this.elements.messageDisplay) {
                throw new Error('Required DOM elements not found');
            }
        } catch (error) {
            console.error('DOM initialization error:', error);
            throw error;
        }
    }

    /**
     * Bind class methods
     */
    bindMethods() {
        this.boundMethods = {
            handleVisibilityChange: this.handleVisibilityChange.bind(this),
            startCountdown: this.startCountdown.bind(this),
            updateTimer: this.updateTimer.bind(this),
            completeCountdown: this.completeCountdown.bind(this),
            verifyTraffic: this.verifyTraffic.bind(this),
            handleError: this.handleError.bind(this)
        };
    }

    /**
     * Add event listeners
     */
    addEventListeners() {
        try {
            // Page visibility
            document.addEventListener('visibilitychange', this.boundMethods.handleVisibilityChange);

            // Cleanup on page unload
            window.addEventListener('beforeunload', () => this.cleanup());

            // Handle API errors
            window.addEventListener('unhandledrejection', (event) => {
                if (event.reason?.isApiError) {
                    this.handleError(event.reason);
                    event.preventDefault();
                }
            });
        } catch (error) {
            console.error('Event listener initialization error:', error);
        }
    }

    /**
     * Initialize analytics
     */
    async initializeAnalytics() {
        if (!this.settings.ga4Enabled) return;

        try {
            if (this.settings.debug) {
                console.log('Initializing analytics with settings:', {
                    ga4Enabled: this.settings.ga4Enabled,
                    ga4MeasurementId: this.settings.ga4MeasurementId,
                    gtmContainerId: this.settings.gtmContainerId
                });
            }

            // Initialize GTM
            if (this.settings.gtmContainerId) {
                await this.loadGTM();
                if (this.settings.debug) {
                    console.log('GTM initialized successfully');
                }
            }

            // Initialize GA4 if not using GTM
            if (this.settings.ga4MeasurementId && !this.settings.gtmContainerId) {
                await this.loadGA4();
                if (this.settings.debug) {
                    console.log('GA4 initialized successfully');
                }
            }

            // Get or generate client ID
            this.state.clientId = await this.getClientId();

            if (this.settings.debug) {
                console.log('Analytics initialization complete:', {
                    clientId: this.state.clientId
                });
            }
        } catch (error) {
            console.error('Analytics initialization error:', error);
            throw error;
        }
    }

    /**
     * Initialize countdown functionality
     */
    init() {
        if (this.settings.debug) {
            console.log('Initializing countdown with settings:', this.settings);
        }

        // Set initial message and time
        this.elements.messageDisplay.textContent = this.settings.messages.first;
        this.updateTimerDisplay();

        // Start verification process
        this.verifyTraffic();
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
     * Build API URL
     * @param {string} endpoint - The API endpoint
     * @returns {string} Complete API URL
     */
    buildApiUrl(endpoint) {
        const baseUrl = this.settings.siteUrl || window.location.origin;
        return `${baseUrl}/wp-json/wp-content-locker/v2/${endpoint}`;
    }

    /**
     * Verify traffic before starting countdown
     */
    async verifyTraffic() {
        try {
            this.showLoading();

            const apiUrl = this.buildApiUrl('verify-traffic');

            if (this.settings.debug) {
                console.log('API Request Details:', {
                    url: apiUrl,
                    protectionId: this.settings.protectionId,
                    nonce: this.settings.nonce
                });
            }

            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.settings.nonce
                },
                body: JSON.stringify({
                    protection_id: this.settings.protectionId,
                    gtm_data: await this.getGTMData()
                }),
                credentials: 'same-origin'
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`HTTP error! status: ${response.status}, message: ${errorText}`);
            }

            const data = await response.json();

            if (this.settings.debug) {
                console.log('API Response:', data);
            }

            if (data.success) {
                this.state.verificationToken = data.token;
                this.state.isActive = true;
                this.state.isVerified = true;
                this.startCountdown();
                
                this.trackEvent('countdown_verified', {
                    protection_id: this.settings.protectionId,
                    verification_token: this.state.verificationToken
                });
            } else {
                throw new Error(data.message || 'Traffic verification failed');
            }
        } catch (error) {
            console.error('Traffic verification error:', error);
            this.handleError(error);
        } finally {
            this.hideLoading();
        }
    }

    /**
     * Start the countdown
     */
    startCountdown() {
        if (!this.state.isActive || this.state.isPaused) return;

        clearInterval(this.state.timerInterval);

        this.state.timerInterval = setInterval(() => {
            if (this.state.currentTime > 0) {
                this.state.currentTime--;
                this.updateTimerDisplay();
            } else {
                this.completeCountdown();
            }
        }, 1000);

        this.trackEvent('countdown_stage_started', {
            stage: this.state.stage,
            duration: this.state.currentTime
        });
    }

    /**
     * Update timer display
     */
    updateTimerDisplay() {
        const minutes = Math.floor(this.state.currentTime / 60);
        const seconds = this.state.currentTime % 60;
        this.elements.timerDisplay.textContent = 
            `${minutes}:${seconds.toString().padStart(2, '0')}`;
    }

    /**
     * Handle countdown completion
     */
    completeCountdown() {
        clearInterval(this.state.timerInterval);

        if (this.settings.mode === 'double' && this.state.stage === 'first') {
            this.state.stage = 'second';
            this.state.currentTime = this.settings.secondTime;
            this.elements.messageDisplay.textContent = this.settings.messages.second;
            this.startCountdown();
        } else {
            this.showComplete();
        }

        this.trackEvent('countdown_stage_completed', {
            stage: this.state.stage
        });
    }

    /**
     * Show completion message and handle redirect
     */
    showComplete() {
        this.elements.messageDisplay.textContent = this.settings.messages.success;
        this.elements.container.classList.add('wcl-countdown-complete');

        if (this.settings.redirectUrl) {
            this.elements.messageDisplay.textContent = this.settings.messages.redirect;
            setTimeout(() => {
                window.location.href = this.settings.redirectUrl;
            }, 1000);
        }

        this.trackEvent('countdown_completed', {
            total_time: this.settings.mode === 'double' ? 
                       this.settings.firstTime + this.settings.secondTime :
                       this.settings.countdown_time
        });
    }

    /**
     * Handle page visibility changes
     */
    handleVisibilityChange() {
        if (document.hidden) {
            this.state.isPaused = true;
            clearInterval(this.state.timerInterval);
            this.trackEvent('countdown_paused');
        } else {
            this.state.isPaused = false;
            if (this.state.isVerified) {
                this.startCountdown();
                this.trackEvent('countdown_resumed');
            }
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
        return {
            client_id: await this.getClientId(),
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
            stage: this.state.stage,
            client_id: this.state.clientId,
            event_time: new Date().toISOString()
        };

        const eventParams = { ...baseParams, ...params };

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
        this.elements.container.classList.add('wcl-loading');
        if (this.elements.loadingIndicator) {
            this.elements.loadingIndicator.style.display = 'block';
        }
    }

    /**
     * Hide loading state
     */
    hideLoading() {
        this.elements.container.classList.remove('wcl-loading');
        if (this.elements.loadingIndicator) {
            this.elements.loadingIndicator.style.display = 'none';
        }
    }

    /**
     * Show error message
     */
    showError(message) {
        this.state.hasError = true;
        if (this.elements.errorDisplay) {
            this.elements.errorDisplay.textContent = message;
            this.elements.errorDisplay.style.display = 'block';
        }
        this.elements.container.classList.add('wcl-error');
        
        this.trackEvent('countdown_error', {
            error_message: message
        });
    }

    /**
     * Handle errors
     */
    handleError(error) {
        console.error('WCLCountdown error:', error);
        this.showError(error.message || this.settings.messages.error);
    }

    /**
     * Cleanup resources
     */
    cleanup() {
        clearInterval(this.state.timerInterval);
        document.removeEventListener('visibilitychange', this.boundMethods.handleVisibilityChange);
        
        if (this.settings.debug) {
            console.log('Cleanup completed');
        }
    }
}

// Initialize countdown when DOM is ready
jQuery(document).ready(function($) {
    const countdownElements = document.querySelectorAll('.wcl-countdown');
    
    countdownElements.forEach(element => {
        try {
            const settings = {
                mode: element.dataset.mode,
                firstTime: parseInt(element.dataset.firstTime) || 60,
                secondTime: parseInt(element.dataset.secondTime) || 30,
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
                redirectUrl: element.dataset.redirectUrl,
                debug: element.dataset.debug === 'true'
            };

            new WCLCountdown(settings);
        } catch (error) {
            console.error('Failed to initialize countdown:', error);
        }
    });
});
