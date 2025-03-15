jQuery(document).ready(async function($) {
    // Initialize countdown settings from PHP
    window.wclCountdownSettings = {
        ajaxUrl: wclCountdown.ajaxUrl,
        nonce: wclCountdown.nonce,
        apiEndpoint: wclCountdown.apiEndpoint || '/wp-json/wp-content-locker/v1',
        ga4MeasurementId: wclCountdown.ga4MeasurementId
    };

    // Wait for GA to be ready
    await new Promise(resolve => {
        if (typeof gtag !== 'undefined') {
            resolve();
        } else {
            // Check every 100ms for gtag
            const checkGtag = setInterval(() => {
                if (typeof gtag !== 'undefined') {
                    clearInterval(checkGtag);
                    resolve();
                }
            }, 100);

            // Timeout after 5 seconds
            setTimeout(() => {
                clearInterval(checkGtag);
                resolve();
            }, 5000);
        }
    });

    // Initialize countdown instances
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
            ...window.wclCountdownSettings
        };

        new WCLCountdown(settings);
    });
});