import { Controller } from '@hotwired/stimulus';
import { announce, fail, postJson, removeCards, replaceCards, selectedValues, syncChoices } from '../request';

/*
 * Selection mode of the gallery: clicking a card checks it instead of opening the lightbox,
 * then a bulk action is applied to every checked screenshot (kept across filter changes).
 */
export default class extends Controller {
    static targets = ['toggle', 'bar', 'count', 'operation', 'field', 'tags', 'album', 'game', 'apply', 'status'];
    static values = { batchUrl: String, token: String };

    connect() {
        this.selected = new Map();
        this.selecting = false;
        this.onClick = (event) => this.click(event);
        this.onKeydown = (event) => this.keydown(event);
        this.element.addEventListener('click', this.onClick, true);
        document.addEventListener('keydown', this.onKeydown);
        this.observer = new MutationObserver(() => this.paint());
        this.observer.observe(this.element, { childList: true, subtree: true });
        this.showFields();
    }

    disconnect() {
        this.element.removeEventListener('click', this.onClick, true);
        document.removeEventListener('keydown', this.onKeydown);
        this.observer.disconnect();
    }

    toggle() {
        this.selecting = !this.selecting;
        this.element.classList.toggle('is-curating', this.selecting);
        this.barTarget.hidden = !this.selecting;
        this.toggleTarget.setAttribute('aria-pressed', String(this.selecting));
        if (!this.selecting) {
            this.selected.clear();
            this.lastIndex = null;
            announce(this.statusTarget, '');
        }
        this.paint();
    }

    click(event) {
        if (!this.selecting) {
            return;
        }
        const card = event.target.closest('.gallery-card[data-curation-id]');
        if (!card || card.closest('dialog')) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        const cards = this.cards();
        const index = cards.indexOf(card);
        if (event.shiftKey && this.lastIndex != null) {
            const [from, to] = [Math.min(this.lastIndex, index), Math.max(this.lastIndex, index)];
            cards.slice(from, to + 1).forEach((item) => this.selected.set(item.dataset.curationId, item.dataset.slug));
        } else if (this.selected.has(card.dataset.curationId)) {
            this.selected.delete(card.dataset.curationId);
        } else {
            this.selected.set(card.dataset.curationId, card.dataset.slug);
        }
        this.lastIndex = index;
        this.paint();
    }

    keydown(event) {
        const typing = event.target.matches?.('input, textarea, select') || event.target.closest?.('.ts-wrapper');
        if (event.key === 'Escape' && this.selecting && !typing && !this.element.querySelector('dialog[open]')) {
            this.toggle();
        }
    }

    selectAll() {
        this.cards().forEach((card) => this.selected.set(card.dataset.curationId, card.dataset.slug));
        this.paint();
    }

    clear() {
        this.selected.clear();
        this.lastIndex = null;
        this.paint();
    }

    showFields() {
        const operation = this.operationTarget.value;
        this.fieldTargets.forEach((field) => { field.hidden = !field.dataset.operations.split(' ').includes(operation); });
    }

    async apply() {
        const bar = this.barTarget.dataset;
        const count = this.selected.size;
        if (count === 0) {
            fail(this.statusTarget, bar.empty);
            return;
        }
        const operation = this.operationTarget.value;
        if (operation === 'delete' && !window.confirm(bar.confirmDelete.replace('%count%', count))) {
            return;
        }
        this.applyTarget.disabled = true;
        announce(this.statusTarget, bar.working);
        try {
            const payload = await postJson(this.batchUrlValue, this.tokenValue, {
                operation,
                ids: [...this.selected.keys()],
                tags: selectedValues(this.tagsTarget),
                album: this.albumTarget.value,
                game: this.gameTarget.value,
            });
            syncChoices(this.tagsTarget, payload.tags);
            syncChoices(this.albumTarget, payload.album ? [payload.album] : null, { multiple: false });
            replaceCards(payload.cards);
            removeCards(payload.removed);
            (payload.removed ?? []).forEach((slug) => this.forget(slug));
            announce(this.statusTarget, (payload.removed ? bar.deleted : bar.done).replace('%count%', payload.count));
        } catch (error) {
            fail(this.statusTarget, `${bar.error} (${error.message})`);
        } finally {
            this.applyTarget.disabled = false;
            this.paint();
        }
    }

    forget(slug) {
        [...this.selected.entries()].filter(([, value]) => value === slug).forEach(([id]) => this.selected.delete(id));
    }

    cards() {
        return [...this.element.querySelectorAll('.gallery-grid .gallery-card[data-curation-id]')];
    }

    paint() {
        this.cards().forEach((card) => {
            const checked = this.selecting && this.selected.has(card.dataset.curationId);
            card.classList.toggle('is-selected', checked);
            const link = card.querySelector('.gallery-card__link');
            if (this.selecting) {
                link?.setAttribute('aria-pressed', String(checked));
            } else {
                link?.removeAttribute('aria-pressed');
            }
        });
        const count = this.selected.size;
        const bar = this.barTarget.dataset;
        this.countTarget.textContent = count === 0 ? bar.countZero : (count === 1 ? bar.countOne : bar.countMany.replace('%count%', count));
    }
}
