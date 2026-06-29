import { Controller } from '@hotwired/stimulus';

// Module-level state persists across Turbo Drive navigations
const _notifiedOrders = new Set();
const _notifiedCakes  = new Set();
let   _lastChecked    = 0;

export default class extends Controller {
    static values = {
        doorbell:  { type: Boolean, default: false },
        ovenAlarm: { type: Boolean, default: false },
    };

    connect() {
        if (_lastChecked === 0) {
            _lastChecked = Math.floor(Date.now() / 1000);
        }
        this._connected = true;
        this._schedule();
    }

    disconnect() {
        this._connected = false;
        clearTimeout(this._timer);
    }

    _schedule() {
        this._timer = setTimeout(() => this._poll(), 2000);
    }

    async _poll() {
        try {
            const since = _lastChecked;
            _lastChecked = Math.floor(Date.now() / 1000);

            const res = await fetch(`/api/notifications?since=${since}`);
            if (!res.ok) { this._schedule(); return; }
            const data = await res.json();

            if (this.doorbellValue) {
                for (const order of (data.newOrders ?? [])) {
                    if (!_notifiedOrders.has(order.id)) {
                        _notifiedOrders.add(order.id);
                        this._toast(
                            `${order.avatar} New order from ${order.customerName}!`,
                            '/',
                            'dark'
                        );
                    }
                }
            }

            if (this.ovenAlarmValue) {
                for (const cake of (data.doneCakes ?? [])) {
                    const key = String(cake.cakeId);
                    if (!_notifiedCakes.has(key)) {
                        _notifiedCakes.add(key);
                        this._toast(
                            `🔔 ${cake.avatar} ${cake.customerName}'s cake is ready to decorate!`,
                            `/order/${cake.orderId}/cake/${cake.cakeId}/edit`,
                            'warning'
                        );
                    }
                }
            }
        } catch (_) {
            // silent on network errors
        }
        if (this._connected) {
            this._schedule();
        }
    }

    _toast(message, href, variant) {
        const container = document.getElementById('notification-toast-container');
        if (!container) return;

        const el = document.createElement('div');
        el.className = `toast align-items-center text-bg-${variant} border-0 fade`;
        el.setAttribute('role', 'alert');
        el.innerHTML = `
            <div class="d-flex">
                <div class="toast-body fw-semibold">
                    <a href="${href}" data-turbo-frame="_top" class="text-white text-decoration-none">${message}</a>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" aria-label="Close"></button>
            </div>`;

        el.querySelector('button').addEventListener('click', () => el.remove());
        container.appendChild(el);

        // Let the browser paint the element at opacity:0 before triggering the fade-in
        requestAnimationFrame(() => el.classList.add('show'));

        setTimeout(() => {
            el.classList.remove('show');
            el.addEventListener('transitionend', () => el.remove(), { once: true });
        }, 6000);
    }
}
