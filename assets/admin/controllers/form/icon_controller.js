import { Controller } from '@hotwired/stimulus';

const DEBOUNCE_MS = 300;
const LABELS = {
    loading: 'Chargement…',
    hint: 'Suggestions · tapez au moins 2 lettres pour chercher.',
    empty: 'Aucune icône trouvée.',
    error: 'Recherche indisponible pour le moment.',
};

export default class extends Controller {
    static targets = ['value', 'preview', 'toggle', 'panel', 'set', 'query', 'grid', 'status'];

    static values = { sets: { type: Array, default: [] }, searchUrl: String, svgUrl: String };

    connect() {
        this.renderPreview();
    }

    disconnect() {
        clearTimeout(this.timer);
        this.pending?.abort();
    }

    toggle() {
        return this.panelTarget.hidden ? this.open() : this.close();
    }

    open() {
        this.panelTarget.hidden = false;
        this.toggleTarget.setAttribute('aria-expanded', 'true');
        this.queryTarget.focus();
        this.fetchIcons();
    }

    close() {
        this.panelTarget.hidden = true;
        this.toggleTarget.setAttribute('aria-expanded', 'false');
    }

    closeOnOutsideClick(event) {
        if (!this.panelTarget.hidden && !this.element.contains(event.target)) {
            this.close();
        }
    }

    preventSubmit(event) {
        event.preventDefault();
    }

    search() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.fetchIcons(), DEBOUNCE_MS);
    }

    pick(event) {
        this.valueTarget.value = event.currentTarget.dataset.icon;
        this.valueTarget.dispatchEvent(new Event('input', { bubbles: true }));
        this.renderPreview();
        this.close();
    }

    renderPreview() {
        const name = this.valueTarget.value.trim();
        this.previewTarget.classList.toggle('is-empty', '' === name);
        this.previewTarget.style.setProperty('--icon-url', name ? this.iconUrl(name) : 'none');
    }

    async fetchIcons() {
        const query = this.queryTarget.value.trim();
        this.pending?.abort();
        this.pending = new AbortController();
        this.statusTarget.textContent = LABELS.loading;

        const url = new URL(this.searchUrlValue, window.location.origin);
        url.searchParams.set('q', query);
        url.searchParams.set('prefixes', this.prefixes);

        try {
            const response = await fetch(url, { signal: this.pending.signal });
            if (!response.ok) {
                throw new Error(String(response.status));
            }
            const { icons } = await response.json();
            this.render(icons, query.length < 2 ? LABELS.hint : LABELS.empty, query.length < 2);
        } catch (error) {
            if ('AbortError' !== error.name) {
                this.render([], LABELS.error, false);
            }
        }
    }

    render(names, label, alwaysShowLabel) {
        this.gridTarget.replaceChildren(...names.map((name) => this.buildIcon(name)));
        this.gridTarget.scrollTop = 0;
        this.statusTarget.textContent = names.length && !alwaysShowLabel ? '' : label;
    }

    buildIcon(name) {
        const icon = document.createElement('button');
        icon.type = 'button';
        icon.className = 'icon-picker__icon';
        icon.title = name;
        icon.dataset.icon = name;
        icon.dataset.action = 'form--icon#pick';
        const glyph = document.createElement('i');
        glyph.style.setProperty('--icon-url', this.iconUrl(name));
        icon.append(glyph);

        return icon;
    }

    iconUrl(name) {
        const url = new URL(this.svgUrlValue, window.location.origin);
        url.searchParams.set('name', name);

        return `url("${url}")`;
    }

    get prefixes() {
        return this.hasSetTarget ? this.setTarget.value : this.setsValue.join(',');
    }
}
