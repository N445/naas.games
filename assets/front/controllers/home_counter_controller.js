/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import { gsap, prefersReducedMotion } from '../motion';

export default class extends Controller {
    static targets = ['value'];

    connect() {
        if (prefersReducedMotion) {
            return;
        }

        const format = new Intl.NumberFormat(document.documentElement.lang || undefined);
        this.animations = gsap.context(() => this.valueTargets.forEach((element) => this.count(element, format)));
    }

    disconnect() {
        this.animations?.revert();
    }

    count(element, format) {
        const counter = { value: 0 };
        gsap.to(counter, {
            value: Number(element.dataset.value),
            duration: 2.2,
            ease: 'power3.out',
            onUpdate: () => { element.textContent = format.format(Math.round(counter.value)); },
            scrollTrigger: { trigger: element, start: 'top 90%', once: true },
        });
        gsap.from(element.parentElement, { y: 40, autoAlpha: 0, duration: 1.1, ease: 'expo.out', scrollTrigger: { trigger: element, start: 'top 90%', once: true } });
    }
}
