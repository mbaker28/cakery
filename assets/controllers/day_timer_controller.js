import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['display', 'expiredForm'];
    static values  = { endsAt: Number, serverNow: Number };

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
        const now       = Date.now() / 1000 + this.offset;
        const remaining = Math.max(0, this.endsAtValue - now);
        const mins      = Math.floor(remaining / 60);
        const secs      = Math.floor(remaining % 60);

        if (this.hasDisplayTarget) {
            this.displayTarget.textContent =
                '⏱ ' + String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');

            if (remaining <= 30) {
                this.displayTarget.classList.replace('bg-warning', 'bg-danger');
                this.displayTarget.classList.remove('text-dark');
                this.displayTarget.classList.add('text-white');
            }
        }

        document.querySelectorAll('.order-card[data-spawn-at]').forEach(card => {
            if (parseFloat(card.dataset.spawnAt) <= now) {
                card.classList.remove('d-none');
            }
        });

        if (remaining <= 0 && !this.expired) {
            this.expired = true;
            if (this.hasExpiredFormTarget) {
                this.expiredFormTarget.requestSubmit();
            }
        }
    }
}
