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

// Initialize countdowns
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.wcl-countdown').forEach(element => {
        new WCLCountdown(element);
    });
});