import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['patienceBar', 'patienceLabel', 'expireForm'];
    static values  = { spawnAt: Number, failsAt: Number, serverNow: Number };

    connect() {
        this.offset  = this.serverNowValue - Date.now() / 1000;
        this.expired = false;
        this.tick();
        this.interval = setInterval(() => this.tick(), 1000);
    }

    disconnect() {
        clearInterval(this.interval);
    }

    tick() {
        const now = Date.now() / 1000 + this.offset;

        if (this.spawnAtValue <= now) {
            this.element.classList.remove('d-none');
        }

        if (this.element.classList.contains('d-none')) return;

        const total     = this.failsAtValue - this.spawnAtValue;
        const remaining = Math.max(0, this.failsAtValue - now);
        const fraction  = total > 0 ? remaining / total : 0;

        if (this.hasPatienceBarTarget) {
            this.patienceBarTarget.style.width = `${fraction * 100}%`;
            this.patienceBarTarget.className = 'progress-bar ' + (
                fraction > 0.6 ? 'bg-success' :
                fraction > 0.3 ? 'bg-warning' :
                                 'bg-danger'
            );
        }

        if (this.hasPatienceLabelTarget) {
            this.patienceLabelTarget.textContent =
                fraction > 0.75 ? '😊 Excited' :
                fraction > 0.50 ? '🙂 Happy'   :
                fraction > 0.30 ? '😐 Waiting…' :
                fraction > 0.15 ? '😤 Impatient' :
                                  '😡 Leaving soon!';
        }

        if (remaining <= 0 && !this.expired) {
            this.expired = true;
            if (this.hasExpireFormTarget) {
                this.expireFormTarget.requestSubmit();
            }
        }
    }
}
