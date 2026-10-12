/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import { gsap, ScrollTrigger, prefersReducedMotion, smoothScroll } from '../motion';

const MINIMUM_TRAVEL = 48;

export default class extends Controller {
    static targets = ['pin', 'viewport', 'track', 'image', 'progress'];

    connect() {
        this.media = gsap.matchMedia();
        this.media.add('(min-width: 900px)', () => this.pin());
        this.media.add('(max-width: 899px)', () => this.trackNativeScroll());
    }

    disconnect() {
        this.media.revert();
    }

    pin() {
        if (prefersReducedMotion || this.travel() < MINIMUM_TRAVEL) {
            return this.trackNativeScroll();
        }

        this.element.classList.add('is-pinned');
        this.tween = gsap.to(this.trackTarget, {
            x: () => -this.travel(),
            ease: 'none',
            scrollTrigger: {
                trigger: this.pinTarget,
                start: 'top top',
                end: () => `+=${this.travel()}`,
                pin: true,
                scrub: 0.8,
                invalidateOnRefresh: true,
                onUpdate: (self) => this.renderProgress(self.progress),
            },
        });
        this.imageTargets.forEach((image) => this.drift(image));

        return () => {
            this.element.classList.remove('is-pinned');
            this.tween = null;
        };
    }

    drift(image) {
        gsap.fromTo(image, { xPercent: -6, scale: 1.14 }, {
            xPercent: 6,
            ease: 'none',
            scrollTrigger: { trigger: image.closest('li'), containerAnimation: this.tween, start: 'left right', end: 'right left', scrub: true },
        });
    }

    trackNativeScroll() {
        const update = () => this.renderProgress(this.viewportTarget.scrollLeft / Math.max(1, this.viewportTarget.scrollWidth - this.viewportTarget.clientWidth));
        this.viewportTarget.addEventListener('scroll', update, { passive: true });
        update();

        return () => this.viewportTarget.removeEventListener('scroll', update);
    }

    follow(event) {
        if (!this.tween) {
            return;
        }

        const item = event.target.closest('li');
        const trigger = this.tween.scrollTrigger;
        const offset = Math.min(this.travel(), Math.max(0, item.offsetLeft - this.viewportTarget.clientWidth / 4));
        this.viewportTarget.scrollLeft = 0;
        this.scrollTo(trigger.start + offset);
    }

    scrollTo(position) {
        const lenis = smoothScroll();
        if (lenis) {
            lenis.scrollTo(position, { immediate: true });

            return;
        }

        window.scrollTo(0, position);
        ScrollTrigger.update();
    }

    travel() {
        return Math.max(0, this.trackTarget.scrollWidth - this.viewportTarget.clientWidth);
    }

    renderProgress(progress) {
        this.progressTargets.forEach((bar) => { bar.style.transform = `scaleX(${progress})`; });
    }
}
