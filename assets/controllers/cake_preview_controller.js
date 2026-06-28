import { Controller } from '@hotwired/stimulus';

const C = {
    sponge:    '#F2C570',
    spongeBot: '#C8852A',
    frostings: {
        frosting_chocolate:    { main: '#6B3A2A', edge: '#3D200E' },
        frosting_vanilla:      { main: '#FFF8E8', edge: '#D4C090' },
        frosting_cream_cheese: { main: '#FAEBD7', edge: '#C8A882' },
    },
    sprinkles: ['#E53E3E', '#3182CE', '#38A169', '#D69E2E', '#8B5CF6'],
    chip:      '#1A0800',
    berry:     '#C53030',
    paper:     '#EDE8D8',
    paperLine: '#CFC8B0',
};

export default class extends Controller {
    static values = {
        size:     { type: String, default: '' },
        layers:   { type: Number, default: 0 },
        frosting: { type: String, default: '' },
        toppings: { type: Array,  default: [] },
        phase:    { type: String, default: 'mixing' },
    };

    connect() {
        requestAnimationFrame(() => {
            this._initCanvas();
            this.draw();
        });
    }

    sizeValueChanged()     { if (this._ready) this.draw(); }
    layersValueChanged()   { if (this._ready) this.draw(); }
    frostingValueChanged() { if (this._ready) this.draw(); }
    toppingsValueChanged() { if (this._ready) this.draw(); }
    phaseValueChanged()    { if (this._ready) this.draw(); }

    _initCanvas() {
        const canvas = this.element;
        const dpr    = window.devicePixelRatio || 1;
        const rect   = canvas.getBoundingClientRect();
        canvas.width  = Math.round(rect.width  * dpr);
        canvas.height = Math.round(rect.height * dpr);
        this._dpr   = dpr;
        this._ready = true;
    }

    draw() {
        const canvas = this.element;
        if (!canvas.width) return;

        const ctx  = canvas.getContext('2d');
        const dpr  = this._dpr || 1;
        const W    = canvas.width  / dpr;
        const H    = canvas.height / dpr;

        ctx.save();
        ctx.scale(dpr, dpr);
        ctx.clearRect(0, 0, W, H);

        ctx.fillStyle = '#F7F4EF';
        ctx.fillRect(0, 0, W, H);

        const size     = this.sizeValue;
        const layers   = Math.max(1, this.layersValue || 1);
        const frosting = this.frostingValue;
        const toppings = this.toppingsValue || [];
        const phase    = this.phaseValue;
        const decor    = phase === 'decorating';

        if (!size) {
            this._placeholder(ctx, W, H);
        } else {
            if (phase === 'baking') this._ovenGlow(ctx, W, H);

            if (size === 'cupcake') {
                this._cupcake(ctx, W, H, layers, frosting, decor, toppings);
            } else if (size === 'tiered') {
                this._tiered(ctx, W, H, layers, frosting, decor, toppings);
            } else {
                this._layered(ctx, W, H, size === '9"' ? 0.72 : 0.54, layers, frosting, decor, toppings);
            }
        }

        ctx.restore();
    }

    _placeholder(ctx, W, H) {
        ctx.fillStyle   = '#DEDAD4';
        ctx.strokeStyle = '#C2BCB4';
        ctx.lineWidth   = 1;
        this._rr(ctx, W / 2 - 52, H / 2 - 16, 104, 32, 6);
        ctx.fill();
        ctx.stroke();
        ctx.fillStyle     = '#9A948C';
        ctx.font          = '11px system-ui, sans-serif';
        ctx.textAlign     = 'center';
        ctx.textBaseline  = 'middle';
        ctx.fillText('Choose a size', W / 2, H / 2);
    }

    _ovenGlow(ctx, W, H) {
        const g = ctx.createRadialGradient(W / 2, H * 0.75, 6, W / 2, H * 0.75, W * 0.7);
        g.addColorStop(0,   'rgba(255,120,10,0.30)');
        g.addColorStop(0.5, 'rgba(255,60,0,0.10)');
        g.addColorStop(1,   'rgba(255,0,0,0)');
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, W, H);
    }

    _fr(key) { return C.frostings[key] || null; }

    _layered(ctx, W, H, wf, layers, frosting, decor, toppings) {
        const cw  = Math.round(W * wf);
        const cx  = Math.round((W - cw) / 2);
        const lh  = Math.min(22, Math.floor((H * 0.58) / layers));
        const sh  = lh * layers;
        const fh  = 12;
        const bot = H - 14;
        const st  = bot - sh;

        for (let i = 0; i < layers; i++) {
            const ly = st + i * lh;
            ctx.fillStyle = C.sponge;
            ctx.fillRect(cx, ly, cw, lh - 1);
            ctx.fillStyle = C.spongeBot;
            ctx.fillRect(cx, ly + lh - 4, cw, 4);
        }
        ctx.strokeStyle = C.spongeBot;
        ctx.lineWidth   = 1.5;
        ctx.strokeRect(cx + 0.5, st + 0.5, cw - 1, sh - 1);

        if (decor) {
            const fr = this._fr(frosting);
            if (fr) {
                ctx.fillStyle = fr.main;
                ctx.fillRect(cx, st - fh, cw, fh);
                ctx.strokeStyle = fr.edge;
                ctx.lineWidth   = 1;
                ctx.strokeRect(cx + 0.5, st - fh + 0.5, cw - 1, fh - 1);
                this._drips(ctx, cx, st, cw, fr.main);
                this._toppings(ctx, toppings, cx, st - fh, cw, fh);
            }
        }
    }

    _cupcake(ctx, W, H, layers, frosting, decor, toppings) {
        const cw = Math.round(W * 0.44);
        const cx = Math.round((W - cw) / 2);
        const ch = 48;
        const cy = H - ch - 12;
        const bh = 22 + Math.min(10, (layers - 1) * 3);
        const bx = cx + Math.round(cw * 0.07);
        const bw = Math.round(cw * 0.86);
        const by = cy - bh;

        // Paper cup
        ctx.fillStyle = C.paper;
        ctx.beginPath();
        ctx.moveTo(cx + Math.round(cw * 0.08), cy);
        ctx.lineTo(cx + Math.round(cw * 0.92), cy);
        ctx.lineTo(cx + cw, cy + ch);
        ctx.lineTo(cx, cy + ch);
        ctx.closePath();
        ctx.fill();

        ctx.strokeStyle = C.paperLine;
        ctx.lineWidth   = 0.8;
        for (let l = 1; l <= 3; l++) {
            const fy   = cy + ch * l / 4;
            const shrk = Math.round(cw * 0.08 * (l / 4));
            ctx.beginPath();
            ctx.moveTo(cx + shrk, fy);
            ctx.lineTo(cx + cw - shrk, fy);
            ctx.stroke();
        }
        ctx.strokeStyle = '#B0A080';
        ctx.lineWidth   = 1.5;
        ctx.beginPath();
        ctx.moveTo(cx + Math.round(cw * 0.08), cy);
        ctx.lineTo(cx + Math.round(cw * 0.92), cy);
        ctx.lineTo(cx + cw, cy + ch);
        ctx.lineTo(cx, cy + ch);
        ctx.closePath();
        ctx.stroke();

        // Body
        ctx.fillStyle = C.sponge;
        ctx.fillRect(bx, by, bw, bh);
        ctx.fillStyle = C.spongeBot;
        ctx.fillRect(bx, by + bh - 3, bw, 3);
        ctx.strokeStyle = C.spongeBot;
        ctx.lineWidth   = 1;
        ctx.strokeRect(bx + 0.5, by + 0.5, bw - 1, bh - 1);

        // Frosting dome
        if (decor) {
            const fr = this._fr(frosting);
            if (fr) {
                const r   = bw / 2;
                const dcx = W / 2;
                const dcy = by;
                const dh  = 26 + Math.min(10, layers * 2);
                ctx.fillStyle = fr.main;
                ctx.beginPath();
                ctx.moveTo(dcx - r, dcy);
                ctx.bezierCurveTo(dcx - r, dcy - dh * 1.25, dcx + r, dcy - dh * 1.25, dcx + r, dcy);
                ctx.closePath();
                ctx.fill();
                ctx.strokeStyle = fr.edge;
                ctx.lineWidth   = 1;
                ctx.stroke();
                this._toppings(ctx, toppings, dcx - r * 0.75, dcy - dh * 0.88, r * 1.5, dh * 0.5);
            }
        }
    }

    _tiered(ctx, W, H, layers, frosting, decor, toppings) {
        const fr = decor ? this._fr(frosting) : null;
        const fh = fr ? 9 : 0;

        const bW = Math.round(W * 0.62);
        const bH = Math.min(50, 16 + layers * 7);
        const bX = Math.round((W - bW) / 2);
        const bY = H - bH - 14;

        const tW = Math.round(W * 0.37);
        const tH = Math.min(34, 12 + layers * 4);
        const tX = Math.round((W - tW) / 2);
        const tY = bY - tH - fh * 2 - 2;

        // Bottom tier
        ctx.fillStyle = C.sponge;
        ctx.fillRect(bX, bY, bW, bH);
        ctx.fillStyle = C.spongeBot;
        ctx.fillRect(bX, bY + bH - 4, bW, 4);
        ctx.strokeStyle = C.spongeBot;
        ctx.lineWidth   = 1.5;
        ctx.strokeRect(bX + 0.5, bY + 0.5, bW - 1, bH - 1);
        if (fr) {
            ctx.fillStyle   = fr.main;
            ctx.fillRect(bX, bY - fh, bW, fh);
            ctx.strokeStyle = fr.edge;
            ctx.lineWidth   = 1;
            ctx.strokeRect(bX + 0.5, bY - fh + 0.5, bW - 1, fh - 1);
        }

        // Top tier
        ctx.fillStyle = C.sponge;
        ctx.fillRect(tX, tY, tW, tH);
        ctx.fillStyle = C.spongeBot;
        ctx.fillRect(tX, tY + tH - 3, tW, 3);
        ctx.strokeStyle = C.spongeBot;
        ctx.lineWidth   = 1.5;
        ctx.strokeRect(tX + 0.5, tY + 0.5, tW - 1, tH - 1);
        if (fr) {
            ctx.fillStyle   = fr.main;
            ctx.fillRect(tX, tY - fh, tW, fh);
            ctx.strokeStyle = fr.edge;
            ctx.lineWidth   = 1;
            ctx.strokeRect(tX + 0.5, tY - fh + 0.5, tW - 1, fh - 1);
            this._toppings(ctx, toppings, tX, tY - fh, tW, fh);
        }
    }

    _drips(ctx, x, y, w, color) {
        const n = Math.max(3, Math.floor(w / 20));
        ctx.fillStyle = color;
        for (let i = 0; i < n; i++) {
            const dx = x + 8 + (i / (n - 1)) * (w - 16);
            const dh = 5 + (i % 3) * 4;
            ctx.beginPath();
            ctx.ellipse(Math.round(dx), y + dh / 2, 3.5, dh / 2 + 1, 0, 0, Math.PI * 2);
            ctx.fill();
        }
    }

    _toppings(ctx, toppings, x, y, w, fh) {
        if (!toppings || !toppings.length) return;
        const ty = y + fh / 2;

        if (toppings.includes('topping_sprinkles')) {
            for (let i = 0; i < 13; i++) {
                const sx    = x + 5 + (i * 43) % Math.max(1, w - 10);
                const sy    = ty - 2 + (i % 3) * 2;
                const angle = (i * 59 % 160) * Math.PI / 180;
                ctx.save();
                ctx.translate(sx, sy);
                ctx.rotate(angle);
                ctx.fillStyle = C.sprinkles[i % C.sprinkles.length];
                ctx.fillRect(-4, -1, 8, 2);
                ctx.restore();
            }
        }

        if (toppings.includes('topping_chocolate_chips')) {
            for (let i = 0; i < 6; i++) {
                const ex = x + 9 + (i * 47) % Math.max(1, w - 18);
                const ey = ty + (i % 2) * 3 - 1;
                ctx.fillStyle = C.chip;
                ctx.beginPath();
                ctx.ellipse(ex, ey, 5, 4, 0, 0, Math.PI * 2);
                ctx.fill();
            }
        }

        if (toppings.includes('topping_strawberries')) {
            for (let i = 0; i < 3; i++) {
                const sx = x + 12 + (i * 61) % Math.max(1, w - 24);
                const sy = ty - fh * 0.4;
                ctx.fillStyle = C.berry;
                ctx.beginPath();
                ctx.arc(sx - 3, sy + 3, 5, -Math.PI, 0);
                ctx.arc(sx + 3, sy + 3, 5, -Math.PI, 0);
                ctx.lineTo(sx, sy + 11);
                ctx.closePath();
                ctx.fill();
            }
        }
    }

    _rr(ctx, x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
    }
}
