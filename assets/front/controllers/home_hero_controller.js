/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import { SplitText } from 'gsap/SplitText';
import { gsap, ScrollTrigger, prefersReducedMotion } from '../motion';

gsap.registerPlugin(SplitText);

export default class extends Controller {
    static targets = ['media', 'slide', 'content', 'title', 'intro', 'caption', 'captionTitle', 'captionMeta', 'dot', 'toggle', 'pauseIcon', 'playIcon'];
    static values = { interval: Number, pauseLabel: String, playLabel: String };

    connect() {
        this.index = 0;
        this.playing = !prefersReducedMotion && this.slideTargets.length > 1;
        this.visible = true;

        this.element.classList.add('is-ready');
        if (!prefersReducedMotion) {
            this.animations = gsap.context(() => this.animate(), this.element);
        }

        this.onVisibilityChange = () => this.refreshTimer();
        document.addEventListener('visibilitychange', this.onVisibilityChange);
        this.toggleTargets.forEach((toggle) => { toggle.hidden = !this.playing; });
        this.renderToggle();
        this.startTimer();
    }

    disconnect() {
        document.removeEventListener('visibilitychange', this.onVisibilityChange);
        this.timer?.kill();
        gsap.killTweensOf([...this.slideTargets, ...this.element.querySelectorAll('img'), ...this.captionTargets]);
        this.split?.revert();
        this.animations?.revert();
    }

    animate() {
        this.introduce();
        if (this.hasSlideTarget) {
            gsap.set(this.slideTargets.slice(1), { autoAlpha: 0 });
            this.kenBurns(this.slideTarget);
        }

        gsap.to(this.mediaTarget, {
            yPercent: 20,
            ease: 'none',
            scrollTrigger: { trigger: this.element, start: 'top top', end: 'bottom top', scrub: true },
        });
        gsap.to(this.contentTarget, {
            yPercent: -25,
            opacity: 0,
            ease: 'none',
            scrollTrigger: { trigger: this.element, start: 'top top', end: 'bottom 20%', scrub: true },
        });
        ScrollTrigger.create({
            trigger: this.element,
            start: 'top bottom',
            end: 'bottom top',
            onToggle: (self) => {
                this.visible = self.isActive;
                this.refreshTimer();
            },
        });
    }

    introduce() {
        const timeline = gsap.timeline({ defaults: { ease: 'expo.out' } });
        timeline.from(this.mediaTarget, { scale: 1.12, duration: 2.4, ease: 'power3.out' }, 0);
        this.split = SplitText.create(this.titleTarget, {
            type: 'lines,words',
            mask: 'lines',
            autoSplit: true,
            onSplit: (split) => gsap.from(split.words, { yPercent: 110, duration: 1.3, stagger: 0.06, ease: 'expo.out', delay: 0.15 }),
        });
        timeline.from(this.introTargets, { y: 28, autoAlpha: 0, duration: 1.2, stagger: 0.12 }, 0.45);
    }

    kenBurns(slide) {
        const image = slide.querySelector('img');
        const drift = this.index % 2 === 0 ? 2 : -2;
        // The image must stay scaled by at least twice the drift, otherwise its edge slides into view.
        gsap.fromTo(image, { scale: 1.16, xPercent: -drift }, { scale: 1.06, xPercent: drift, duration: this.intervalValue / 1000 + 2, ease: 'none', overwrite: 'auto' });
    }

    select({ params: { index } }) {
        this.show(index);
    }

    toggle() {
        this.playing = !this.playing;
        this.renderToggle();
        this.refreshTimer();
    }

    next() {
        this.show((this.index + 1) % this.slideTargets.length);
    }

    show(index) {
        if (index === this.index) {
            return;
        }

        const previous = this.slideTargets[this.index];
        const next = this.slideTargets[index];
        this.index = index;
        this.renderSlides(previous, next);
        this.renderCaption(next);
        this.renderDots();
        this.startTimer();
    }

    renderSlides(previous, next) {
        previous.classList.remove('is-active');
        previous.setAttribute('aria-hidden', 'true');
        next.classList.add('is-active');
        next.removeAttribute('aria-hidden');
        if (prefersReducedMotion) {
            return;
        }

        gsap.killTweensOf([previous, next]);
        gsap.set(previous, { zIndex: 1 });
        gsap.set(next, { zIndex: 2, autoAlpha: 1 });
        gsap.fromTo(next, { clipPath: 'inset(0% 0% 0% 100%)' }, {
            clipPath: 'inset(0% 0% 0% 0%)',
            duration: 1.4,
            ease: 'expo.inOut',
            onComplete: () => gsap.set(previous, { autoAlpha: 0 }),
        });
        this.kenBurns(next);
    }

    renderCaption(slide) {
        const update = () => {
            this.captionTitleTarget.textContent = slide.dataset.title;
            this.captionMetaTarget.textContent = slide.dataset.meta;
            this.captionTarget.href = slide.dataset.href;
        };
        if (prefersReducedMotion) {
            update();

            return;
        }

        gsap.timeline()
            .to(this.captionTarget, { y: -12, autoAlpha: 0, duration: 0.35, ease: 'power2.in', onComplete: update })
            .fromTo(this.captionTarget, { y: 12 }, { y: 0, autoAlpha: 1, duration: 0.6, ease: 'expo.out' });
    }

    renderDots() {
        this.dotTargets.forEach((dot, position) => {
            dot.removeAttribute('aria-current');
            if (position === this.index) {
                dot.setAttribute('aria-current', 'true');
            }
        });
    }

    renderToggle() {
        this.toggleTargets.forEach((toggle) => {
            toggle.setAttribute('aria-label', this.playing ? this.pauseLabelValue : this.playLabelValue);
        });
        this.pauseIconTargets.forEach((icon) => { icon.hidden = !this.playing; });
        this.playIconTargets.forEach((icon) => { icon.hidden = this.playing; });
    }

    startTimer() {
        this.timer?.kill();
        if (this.slideTargets.length < 2 || prefersReducedMotion) {
            return;
        }

        this.dotTargets.forEach((dot) => gsap.set(dot.querySelector('.home-hero__dot-progress'), { scaleX: 0 }));
        const progress = this.dotTargets[this.index].querySelector('.home-hero__dot-progress');
        this.timer = gsap.to(progress, { scaleX: 1, duration: this.intervalValue / 1000, ease: 'none', onComplete: () => this.next() });
        this.refreshTimer();
    }

    refreshTimer() {
        if (!this.timer) {
            return;
        }

        const running = this.playing && this.visible && !document.hidden;
        running ? this.timer.play() : this.timer.pause();
    }
}
