import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['progressBar', 'label', 'seconds', 'doneForm'];
    static values  = { startedAt: Number, duration: Number, serverNow: Number };

    connect() {
        // Calibrate client clock to server clock
        this.clientOffset = this.serverNowValue - Math.floor(Date.now() / 1000);
        this.tick();
        this.interval = setInterval(() => this.tick(), 100);
    }

    disconnect() {
        clearInterval(this.interval);
    }

    tick() {
        const now     = Math.floor(Date.now() / 1000) + this.clientOffset;
        const elapsed = now - this.startedAtValue;
        const remaining = Math.max(0, this.durationValue - elapsed);
        const fraction  = Math.min(1, elapsed / this.durationValue);

        if (this.hasProgressBarTarget) {
            this.progressBarTarget.style.width = `${fraction * 100}%`;
        }

        if (this.hasLabelTarget) {
            this.labelTarget.textContent = remaining > 0 ? 'Baking…' : 'Done!';
        }

        if (this.hasSecondsTarget) {
            this.secondsTarget.textContent = remaining > 0
                ? `${Math.ceil(remaining)}s remaining`
                : '';
        }

        if (remaining <= 0) {
            clearInterval(this.interval);
            if (this.hasDoneFormTarget) {
                this.doneFormTarget.requestSubmit();
            }
        }
    }
}
