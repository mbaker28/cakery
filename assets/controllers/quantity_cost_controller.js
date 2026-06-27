import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['quantity', 'total'];
    static values  = { unitCost: Number };

    connect() {
        this.update();
    }

    update() {
        const qty = Math.max(0, parseInt(this.quantityTarget.value) || 0);
        this.totalTarget.textContent = '$' + (this.unitCostValue * qty).toFixed(2);
    }
}
