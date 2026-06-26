import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['countdown', 'expireForm'];
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

        const remaining = Math.max(0, this.failsAtValue - now);
        const mins      = Math.floor(remaining / 60);
        const secs      = Math.floor(remaining % 60);

        if (this.hasCountdownTarget) {
            this.countdownTarget.textContent = String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
            this.countdownTarget.className = 'badge ' + (
                remaining <= 30  ? 'bg-danger text-white' :
                remaining <= 60  ? 'bg-warning text-dark' :
                                   'bg-secondary text-white'
            );
        }

        if (remaining <= 0 && !this.expired) {
            this.expired = true;
            if (this.hasExpireFormTarget) {
                this.expireFormTarget.requestSubmit();
            }
        }
    }
}
