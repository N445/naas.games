import { Controller } from '@hotwired/stimulus';
import TomSelect from 'tom-select';

const NEW_PREFIX = 'new:';

/*
 * Options (data attributes on the <select>):
 * - data-placeholder
 * - data-create="true": typed values become options sent as "new:<text>"
 * - data-create-label="Create “__value__”", data-no-results: translated texts (French defaults)
 */
export default class extends Controller {
    connect() {
        const { placeholder, create, createLabel, noResults } = this.element.dataset;
        this.tomSelect = new TomSelect(this.element, {
            plugins: this.element.multiple ? ['remove_button'] : [],
            allowEmptyOption: true,
            maxOptions: null,
            hidePlaceholder: this.element.multiple,
            placeholder,
            create: create === 'true' ? (input) => ({ value: NEW_PREFIX + input, text: input }) : false,
            createFilter: (input) => input.trim().length > 0,
            render: {
                no_results: () => `<div class="no-results">${escape(noResults ?? 'Aucun résultat')}</div>`,
                option_create: (data, escapeHtml) => `<div class="create">${escapeHtml((createLabel ?? 'Créer « __value__ »').replace('__value__', data.input))}</div>`,
            },
        });
    }

    disconnect() {
        this.tomSelect?.destroy();
    }
}

function escape(text) {
    const element = document.createElement('span');
    element.textContent = text;

    return element.innerHTML;
}
