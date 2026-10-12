/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import { gsap, ScrollTrigger, prefersReducedMotion } from '../motion';

export default class extends Controller {
    static targets = ['item', 'parallax'];

    connect() {
        if (prefersReducedMotion) {
            return;
        }

        this.animations = gsap.context(() => {
            this.reveal();
            this.parallaxTargets.forEach((image) => this.parallax(image));
        });
    }

    disconnect() {
        this.animations?.revert();
    }

    reveal() {
        gsap.set(this.itemTargets, { y: 56, autoAlpha: 0 });
        ScrollTrigger.batch(this.itemTargets, {
            start: 'top 88%',
            once: true,
            onEnter: (batch) => gsap.to(batch, { y: 0, autoAlpha: 1, duration: 1.2, stagger: 0.12, ease: 'expo.out', overwrite: true }),
        });
    }

    parallax(image) {
        gsap.fromTo(image, { yPercent: -7, scale: 1.16 }, {
            yPercent: 7,
            ease: 'none',
            scrollTrigger: { trigger: image.parentElement, start: 'top bottom', end: 'bottom top', scrub: true },
        });
    }
}
