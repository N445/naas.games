/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import { SplitText } from 'gsap/SplitText';
import { gsap, prefersReducedMotion } from '../motion';

gsap.registerPlugin(SplitText);

export default class extends Controller {
    connect() {
        if (prefersReducedMotion) {
            return;
        }

        this.split = SplitText.create(this.element, {
            type: 'words',
            autoSplit: true,
            onSplit: (split) => gsap.fromTo(split.words, { opacity: 0.16 }, {
                opacity: 1,
                stagger: 0.1,
                ease: 'none',
                scrollTrigger: { trigger: this.element, start: 'top 80%', end: 'bottom 45%', scrub: true },
            }),
        });
    }

    disconnect() {
        this.split?.revert();
    }
}
