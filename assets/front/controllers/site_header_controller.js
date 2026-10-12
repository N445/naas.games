import { Controller } from '@hotwired/stimulus';
import { startSmoothScroll } from '../motion';

const SCROLLED_OFFSET = 24;

export default class extends Controller {
    connect() {
        startSmoothScroll();
        this.onScroll = () => this.element.classList.toggle('is-scrolled', window.scrollY > SCROLLED_OFFSET);
        window.addEventListener('scroll', this.onScroll, { passive: true });
        this.onScroll();
    }

    disconnect() {
        window.removeEventListener('scroll', this.onScroll);
    }
}
