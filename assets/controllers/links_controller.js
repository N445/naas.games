import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    track({ params: { title } }) {
        window._paq?.push(['trackEvent', 'Liens', 'Clic', title]);
    }
}
