/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['query', 'item'];

    connect() {
        this.expanded = new Set();
        this.collapsed = new Set();
    }

    disconnect() {
        cancelAnimationFrame(this.frame);
    }

    toggle(event) {
        const button = event.currentTarget;
        const expanded = button.getAttribute('aria-expanded') !== 'true';
        const tagId = button.closest('[data-tag-id]').dataset.tagId;
        this.expanded[expanded ? 'add' : 'delete'](tagId);
        this.collapsed[expanded ? 'delete' : 'add'](tagId);
        setExpanded(button, expanded);
    }

    itemTargetConnected() {
        cancelAnimationFrame(this.frame);
        this.frame = requestAnimationFrame(() => this.restore());
    }

    restore() {
        this.expanded.forEach((tagId) => this.applyState(tagId, true));
        this.collapsed.forEach((tagId) => this.applyState(tagId, false));
        this.filter();
    }

    applyState(tagId, expanded) {
        const button = this.element.querySelector(`#tag-toggle-${tagId}`);
        if (button) {
            setExpanded(button, expanded);
        }
    }

    filter() {
        const query = normalize(this.queryTarget.value.trim());
        this.element.classList.toggle('is-searching', query !== '');
        this.itemTargets.forEach((item) => item.toggleAttribute('data-match', query !== '' && normalize(item.dataset.name).includes(query)));
        this.itemTargets.forEach((item) => { item.hidden = query !== '' && !isVisibleMatch(item); });
    }
}

function setExpanded(button, expanded) {
    button.setAttribute('aria-expanded', String(expanded));
    document.getElementById(button.getAttribute('aria-controls'))?.classList.toggle('is-collapsed', !expanded);
}

function isVisibleMatch(item) {
    return item.hasAttribute('data-match') || Boolean(item.querySelector('[data-match]')) || Boolean(item.parentElement.closest('[data-match]'));
}

function normalize(text) {
    return text.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();
}
