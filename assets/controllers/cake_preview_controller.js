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
        ctx.fillStyle    = '#9A948C';
        ctx.font         = '11px system-ui, sans-serif';
        ctx.textAlign    = 'center';
        ctx.textBaseline = 'middle';
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
        // Wrapper geometry — wide at rim (top), narrow at base (bottom)
        const tW    = Math.round(Math.min(W * 0.50, H * 0.52));  // rim width (widest)
        const bW    = Math.round(tW * 0.68);                      // base width (narrowest)
        const ch    = Math.round(H * 0.30);                       // wrapper height
        const baseY = H - 12;
        const rimY  = baseY - ch;
        const bX    = Math.round((W - bW) / 2);
        const tX    = Math.round((W - tW) / 2);

        // Muffin top: dome that rises above the wrapper rim
        const muffR  = Math.round(tW / 2) + 3;                     // slightly wider than rim opening
        const muffCX = W / 2;
        const rise   = Math.round(H * 0.13) + (layers - 1) * 4;   // how tall the dome is
        const muffTopY = rimY - rise;

        // 1. Paper wrapper (drawn first — cake body is hidden inside it)
        ctx.fillStyle = C.paper;
        ctx.beginPath();
        ctx.moveTo(tX, rimY);
        ctx.lineTo(tX + tW, rimY);
        ctx.lineTo(bX + bW, baseY);
        ctx.lineTo(bX, baseY);
        ctx.closePath();
        ctx.fill();

        // Pleat lines (lerp from rim edges to base edges)
        ctx.strokeStyle = C.paperLine;
        ctx.lineWidth   = 0.8;
        for (let l = 1; l <= 2; l++) {
            const t  = l / 3;
            const fy = rimY + ch * t;
            const lx = tX + (bX - tX) * t;
            const rx = (tX + tW) + ((bX + bW) - (tX + tW)) * t;
            ctx.beginPath();
            ctx.moveTo(lx, fy);
            ctx.lineTo(rx, fy);
            ctx.stroke();
        }

        // Wrapper outline
        ctx.strokeStyle = '#B0A080';
        ctx.lineWidth   = 1.5;
        ctx.beginPath();
        ctx.moveTo(tX, rimY);
        ctx.lineTo(tX + tW, rimY);
        ctx.lineTo(bX + bW, baseY);
        ctx.lineTo(bX, baseY);
        ctx.closePath();
        ctx.stroke();

        // 2. Muffin top dome above the rim
        ctx.fillStyle = C.sponge;
        ctx.beginPath();
        ctx.moveTo(muffCX - muffR, rimY);
        ctx.lineTo(muffCX - muffR, muffTopY + rise * 0.35);
        ctx.bezierCurveTo(
            muffCX - muffR, muffTopY,
            muffCX + muffR, muffTopY,
            muffCX + muffR, muffTopY + rise * 0.35
        );
        ctx.lineTo(muffCX + muffR, rimY);
        ctx.closePath();
        ctx.fill();

        // Muffin top bottom edge
        ctx.fillStyle = C.spongeBot;
        ctx.fillRect(muffCX - muffR, rimY - 3, muffR * 2, 3);

        // Muffin top outline
        ctx.strokeStyle = C.spongeBot;
        ctx.lineWidth   = 1;
        ctx.beginPath();
        ctx.moveTo(muffCX - muffR, rimY);
        ctx.lineTo(muffCX - muffR, muffTopY + rise * 0.35);
        ctx.bezierCurveTo(
            muffCX - muffR, muffTopY,
            muffCX + muffR, muffTopY,
            muffCX + muffR, muffTopY + rise * 0.35
        );
        ctx.lineTo(muffCX + muffR, rimY);
        ctx.stroke();

        // 3. Frosting dome on top of muffin top
        if (decor) {
            const fr = this._fr(frosting);
            if (fr) {
                const dh    = Math.round(H * 0.22) + Math.min(10, layers * 2);
                const baseY = muffTopY + Math.round(rise * 0.18); // overlap muffin top slightly
                ctx.fillStyle = fr.main;
                ctx.beginPath();
                ctx.moveTo(muffCX - muffR, baseY);
                ctx.bezierCurveTo(
                    muffCX - muffR, baseY - dh * 1.2,
                    muffCX + muffR, baseY - dh * 1.2,
                    muffCX + muffR, baseY
                );
                ctx.closePath();
                ctx.fill();
                ctx.strokeStyle = fr.edge;
                ctx.lineWidth   = 1;
                ctx.stroke();
                this._toppings(ctx, toppings, muffCX - muffR * 0.8, baseY - dh * 0.85, muffR * 1.6, dh * 0.5);
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
