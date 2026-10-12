import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';
import Sortable from 'sortablejs';

export default class extends Controller {
    static values = { action: String };

    connect() {
        this.sortable = Sortable.create(this.element, {
            draggable: '[data-id]',
            filter: 'button',
            preventOnFilter: false,
            animation: 180,
            ghostClass: 'is-ghost',
            chosenClass: 'is-dragging',
            onEnd: (event) => this.save(event),
        });
    }

    disconnect() {
        this.sortable.destroy();
    }

    async save(event) {
        if (event.oldIndex === event.newIndex) {
            return;
        }

        const ids = Array.from(this.element.querySelectorAll('[data-id]'), (item) => item.dataset.id).join(',');
        const component = await getComponent(this.element.closest('[data-controller~="live"]'));
        component.action(this.actionValue, { ids });
    }
}
