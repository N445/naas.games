import { Controller } from '@hotwired/stimulus';
import { getComponent } from '@symfony/ux-live-component';

export default class extends Controller {
    async initialize() {
        this.component = await getComponent(this.element.closest('[data-controller~="live"]'));
    }

    dragStart(event) {
        this.draggedId = event.currentTarget.dataset.tagId;
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', this.draggedId);
        event.currentTarget.classList.add('is-dragging');
    }

    dragEnd(event) {
        event.currentTarget.classList.remove('is-dragging');
        this.draggedId = null;
    }

    dragOver(event) {
        if (!this.draggedId || event.currentTarget.dataset.tagId === this.draggedId) {
            return;
        }
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        event.currentTarget.classList.add('is-drop-target');
    }

    dragLeave(event) {
        event.currentTarget.classList.remove('is-drop-target');
    }

    drop(event) {
        event.preventDefault();
        event.currentTarget.classList.remove('is-drop-target');
        const tagId = this.draggedId;
        const parentId = event.currentTarget.dataset.tagId;
        this.draggedId = null;
        if (tagId && tagId !== parentId) {
            this.component.action('move', { tagId, parentId });
        }
    }

    cancel() {
        this.component.action('cancel');
    }

    confirmDelete(event) {
        const { tagId, tagName, screenshots, children } = event.currentTarget.dataset;
        const consequences = [
            Number(screenshots) > 0 ? `${screenshots} image(s) perdront ce tag.` : null,
            Number(children) > 0 ? `Ses ${children} sous-tag(s) remonteront d'un niveau.` : null,
        ].filter(Boolean).join('\n');

        if (window.confirm(`Supprimer le tag « ${tagName} » ?\n${consequences}`)) {
            this.component.action('delete', { tagId });
        }
    }
}
