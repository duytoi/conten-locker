(function($) {
    'use strict';

    // GA4 Event Tracking
    function trackGA4Event(eventName, params) {
        if (typeof gtag !== 'undefined' && wclCountdown.ga4Enabled) {
            gtag('event', eventName, params);
        }
    }

    // Check Google Traffic
    function isGoogleTraffic() {
        return document.referrer.indexOf('google.com') !== -1;
    }

    // Initialize Protection
    function initProtection() {
        // Track page view
        trackGA4Event('page_view', {
            'source': document.referrer
        });

        // If requires Google traffic, check referrer
        if (wclCountdown.requiresGA && !isGoogleTraffic()) {
            $('.wcl-get-password').hide();
            return;
        }

        // Show get password button
        $('.wcl-get-password').show();
    }

    // Handle Countdown
    function startCountdown(container) {
        let timeLeft = wclCountdown.countdownTime;
        const timer = container.find('.wcl-countdown-timer');
        const message = container.find('.wcl-countdown-message');

        trackGA4Event('countdown_start', {
            'time_length': timeLeft
        });

        const interval = setInterval(() => {
            timeLeft--;
            timer.text(timeLeft);

            if (timeLeft <= 0) {
                clearInterval(interval);
                completeCountdown(container);
            }
        }, 1000);
    }

    // Handle Completion
    function completeCountdown(container) {
        trackGA4Event('countdown_complete');
        
        $.ajax({
            url: wclCountdown.ajaxUrl,
            type: 'POST',
            data: {
                action: 'wcl_get_password',
                nonce: wclCountdown.nonce
            },
            success: function(response) {
                if (response.success) {
                    container.find('.wcl-countdown-state').hide();
                    container.find('.wcl-password-state')
                        .show()
                        .find('.wcl-password-display')
                        .html(response.data.password);

                    trackGA4Event('password_revealed');
                }
            }
        });
    }

    // Document Ready
    $(function() {
        initProtection();
    });

})(jQuery);