<?php
/**
 * Frontend countdown template
 * 
 * @package WP_Content_Locker
 */

defined('ABSPATH') || exit;

// Get protection data from attributes
$protection_id = isset($args['protection_id']) ? intval($args['protection_id']) : 0;
$protection = isset($args['protection']) ? $args['protection'] : null;
$download = isset($args['download']) ? $args['download'] : null;

if (!$protection || !$download) {
    return;
}

// Get settings
$countdown_mode = $protection['countdown_mode'];
$first_time = intval($protection['countdown_first']);
$second_time = intval($protection['countdown_second']);
$first_message = !empty($protection['first_message']) ? $protection['first_message'] : __('Please wait for the countdown to complete', 'wcl');
$second_message = !empty($protection['second_message']) ? $protection['second_message'] : __('Please complete the second countdown', 'wcl');
$redirect_message = !empty($protection['redirect_message']) ? $protection['redirect_message'] : __('Click any link to continue', 'wcl');

// Generate unique IDs
$container_id = 'wcl-countdown-' . $protection_id;
$timer_id = 'wcl-timer-' . $protection_id;
$message_id = 'wcl-message-' . $protection_id;
$password_form_id = 'wcl-password-form-' . $protection_id;
?>

<div id="<?php echo esc_attr($container_id); ?>" class="wcl-countdown-container">
    <!-- Progress Bar -->
    <div class="wcl-progress-bar">
        <div class="wcl-progress" style="width: 0%"></div>
    </div>

    <!-- Timer Display -->
    <div id="<?php echo esc_attr($timer_id); ?>" class="wcl-timer">
        <span class="wcl-minutes">00</span>:<span class="wcl-seconds">00</span>
    </div>

    <!-- Message Display -->
    <div id="<?php echo esc_attr($message_id); ?>" class="wcl-message">
        <?php echo wp_kses_post($first_message); ?>
    </div>

    <!-- Password Form (Initially Hidden) -->
    <form id="<?php echo esc_attr($password_form_id); ?>" class="wcl-password-form" style="display: none;">
        <input type="hidden" name="protection_id" value="<?php echo esc_attr($protection_id); ?>">
        <input type="hidden" name="download_id" value="<?php echo esc_attr($download->ID); ?>">
        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('wcl_verify_password'); ?>">
        
        <div class="wcl-form-group">
            <input type="password" name="password" class="wcl-password-input" placeholder="<?php esc_attr_e('Enter password to unlock content', 'wcl'); ?>" required>
        </div>
        
        <div class="wcl-form-group">
            <button type="submit" class="wcl-submit-btn">
                <?php esc_html_e('Unlock Content', 'wcl'); ?>
            </button>
        </div>

        <div class="wcl-error-message" style="display: none;"></div>
    </form>
</div>

<!-- Analytics Integration -->
<?php if (!empty($protection['ga4_enabled']) && !empty($protection['ga4_measurement_id'])) : ?>
    <!-- Google Analytics 4 -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($protection['ga4_measurement_id']); ?>"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '<?php echo esc_js($protection['ga4_measurement_id']); ?>');
    </script>
<?php endif; ?>

<!-- Google Tag Manager -->
<?php if (!empty($protection['gtm_container_id'])) : ?>
    <script>
    (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','<?php echo esc_js($protection['gtm_container_id']); ?>');
    </script>
<?php endif; ?>

<script>
jQuery(document).ready(function($) {
    const container = $('#<?php echo esc_js($container_id); ?>');
    const timer = $('#<?php echo esc_js($timer_id); ?>');
    const message = $('#<?php echo esc_js($message_id); ?>');
    const passwordForm = $('#<?php echo esc_js($password_form_id); ?>');
    const progressBar = container.find('.wcl-progress');
    
    let countdownTime = <?php echo esc_js($first_time); ?>;
    let currentStep = 1;
    let timeLeft = countdownTime;
    
    // Initialize countdown
    updateTimer(timeLeft);
    startCountdown();

    function startCountdown() {
        const interval = setInterval(() => {
            timeLeft--;
            updateTimer(timeLeft);
            updateProgress();

            if (timeLeft <= 0) {
                clearInterval(interval);
                handleCountdownComplete();
            }
        }, 1000);
    }

    function updateTimer(seconds) {
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;
        
        timer.find('.wcl-minutes').text(String(minutes).padStart(2, '0'));
        timer.find('.wcl-seconds').text(String(remainingSeconds).padStart(2, '0'));
    }

    function updateProgress() {
        const progress = ((countdownTime - timeLeft) / countdownTime) * 100;
        progressBar.css('width', progress + '%');
    }

    function handleCountdownComplete() {
        if ('<?php echo esc_js($countdown_mode); ?>' === 'double' && currentStep === 1) {
            // Start second countdown
            currentStep = 2;
            timeLeft = <?php echo esc_js($second_time); ?>;
            countdownTime = timeLeft;
            message.html('<?php echo wp_kses_post($second_message); ?>');
            startCountdown();
        } else {
            // Show password form
            message.html('<?php echo wp_kses_post($redirect_message); ?>');
            passwordForm.slideDown();
            trackEvent('countdown_complete');
        }
    }

    // Password form submission
    passwordForm.on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'wcl_verify_password');

        $.ajax({
            url: wcl_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    trackEvent('content_unlocked');
                    window.location.href = response.data.redirect_url;
                } else {
                    passwordForm.find('.wcl-error-message')
                        .html(response.data.message)
                        .slideDown();
                }
            },
            error: function() {
                passwordForm.find('.wcl-error-message')
                    .html('<?php esc_html_e('An error occurred. Please try again.', 'wcl'); ?>')
                    .slideDown();
            }
        });
    });

    // Analytics tracking
    function trackEvent(eventName) {
        <?php if (!empty($protection['ga4_enabled'])) : ?>
        if (typeof gtag === 'function') {
            gtag('event', eventName, {
                'protection_id': '<?php echo esc_js($protection_id); ?>',
                'download_id': '<?php echo esc_js($download->ID); ?>'
            });
        }
        <?php endif; ?>

        <?php if (!empty($protection['gtm_container_id'])) : ?>
        if (typeof dataLayer !== 'undefined') {
            dataLayer.push({
                'event': eventName,
                'protection_id': '<?php echo esc_js($protection_id); ?>',
                'download_id': '<?php echo esc_js($download->ID); ?>'
            });
        }
        <?php endif; ?>
    }
});
</script>

<style>
.wcl-countdown-container {
    max-width: 600px;
    margin: 20px auto;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.wcl-progress-bar {
    height: 4px;
    background: #e9ecef;
    border-radius: 4px;
    margin-bottom: 20px;
    overflow: hidden;
}

.wcl-progress {
    height: 100%;
    background: #007bff;
    transition: width 0.3s ease;
}

.wcl-timer {
    font-size: 2.5em;
    text-align: center;
    font-weight: bold;
    color: #343a40;
    margin-bottom: 15px;
}

.wcl-message {
    text-align: center;
    margin-bottom: 20px;
    color: #495057;
}

.wcl-password-form {
    margin-top: 20px;
}

.wcl-form-group {
    margin-bottom: 15px;
}

.wcl-password-input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
}

.wcl-submit-btn {
    width: 100%;
    padding: 10px;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.3s;
}

.wcl-submit-btn:hover {
    background: #0056b3;
}

.wcl-error-message {
    color: #dc3545;
    margin-top: 10px;
    text-align: center;
}
</style>