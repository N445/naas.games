import { Controller } from '@hotwired/stimulus';
import TomSelect from 'tom-select';

export default class extends Controller {
    connect() {
        this.tomSelect = new TomSelect(this.element, {
            plugins: this.element.multiple ? ['remove_button'] : [],
            allowEmptyOption: true,
            maxOptions: null,
            hidePlaceholder: this.element.multiple,
            placeholder: this.element.dataset.placeholder,
            render: {
                no_results: () => '<div class="no-results">Aucun résultat</div>',
            },
        });
    }

    disconnect() {
        this.tomSelect?.destroy();
    }
}
