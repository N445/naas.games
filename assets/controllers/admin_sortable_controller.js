import { Controller } from '@hotwired/stimulus';
import Sortable from 'sortablejs';

export default class extends Controller {
    static values = { url: String, token: String };

    connect() {
        this.rows = this.element.querySelector('tbody');
        this.sortable = Sortable.create(this.rows, {
            handle: '.sortable-handle',
            draggable: 'tr[data-id]',
            animation: 150,
            onEnd: () => this.save(),
        });
    }

    disconnect() {
        this.sortable.destroy();
    }

    async save() {
        const ids = Array.from(this.rows.querySelectorAll('tr[data-id]'), (row) => row.dataset.id);
        const response = await fetch(this.urlValue, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.tokenValue },
            body: JSON.stringify({ ids }),
        });

        if (!response.ok) {
            window.location.reload();
        }
    }
}
