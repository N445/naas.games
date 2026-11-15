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
        this.finalLoads = new Map();
        this.saveData = navigator.connection?.saveData === true;
        // Step 1 (preview) is in the HTML with fetchpriority=high; step 2 (lossless) starts once it is in.
        const first = this.slideTargets[0];
        if (first) {
            this.previewLoaded(first).then(() => this.loadFinal(first)).then(() => this.warmUp());
        }
    }

    disconnect() {
        this.finalLoads = new Map();
        document.removeEventListener('visibilitychange', this.onVisibilityChange);
        window.removeEventListener('load', this.onLoad);
        if (this.idleHandle && 'cancelIdleCallback' in window) {
            window.cancelIdleCallback(this.idleHandle);
        } else if (this.idleHandle) {
            clearTimeout(this.idleHandle);
        }
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
        const images = slide.querySelectorAll('img');
        // Settle exactly at scale 1 and drop the transform: the resting frame is rendered 1:1 from the
        // srcset candidate (no resampling, no compositor layer), so the full resolution stays crisp.
        gsap.fromTo(images, { scale: 1.05 }, {
            scale: 1,
            duration: Math.max(this.intervalValue / 1000 - 1, 2),
            ease: 'power1.out',
            overwrite: 'auto',
            clearProps: 'transform',
        });
    }

    previewLoaded(slide) {
        const preview = slide.querySelector('.home-hero__preview');
        if (!preview || preview.complete) {
            return Promise.resolve();
        }

        return new Promise((resolve) => {
            preview.addEventListener('load', resolve, { once: true });
            preview.addEventListener('error', resolve, { once: true });
        });
    }

    // Step 2: hand srcset/sizes to the final <img> only now, so the browser picks one lossless
    // candidate once, then reveal it after decode() so the swap over the preview never flashes.
    // With Save-Data the preview stays as the final frame.
    loadFinal(slide) {
        const final = slide.querySelector('.home-hero__final');
        if (!final || this.saveData) {
            return Promise.resolve();
        }
        if (this.finalLoads.has(final)) {
            return this.finalLoads.get(final);
        }

        final.loading = 'eager';
        final.sizes = final.dataset.sizes;
        final.srcset = final.dataset.srcset;
        const reveal = () => {
            final.classList.add('is-loaded');
            const preview = slide.querySelector('.home-hero__preview');
            setTimeout(() => preview?.classList.add('is-hidden'), 500);
        };
        // Some engines reject decode() for very large images although they display fine.
        const loaded = final.decode().then(reveal, () => {
            if (final.complete && final.naturalWidth) {
                reveal();
            }
        });
        this.finalLoads.set(final, loaded);

        return loaded;
    }

    // Once the page is idle: previews of the following slides first (light), then their lossless
    // versions one at a time in slide order, so bandwidth goes to the next slide to be shown.
    warmUp() {
        const slides = this.slideTargets.slice(1);
        if (slides.length === 0 || !this.element.isConnected) {
            return;
        }

        const run = async () => {
            slides.forEach((slide) => {
                const preview = slide.querySelector('.home-hero__preview');
                if (preview) {
                    preview.loading = 'eager';
                    preview.decode?.().catch(() => {});
                }
            });
            for (const slide of slides) {
                if (!this.element.isConnected) {
                    return;
                }
                await this.loadFinal(slide);
            }
        };
        this.onLoad = () => {
            this.idleHandle = 'requestIdleCallback' in window ? requestIdleCallback(run, { timeout: 4000 }) : setTimeout(run, 1500);
        };
        document.readyState === 'complete' ? this.onLoad() : window.addEventListener('load', this.onLoad, { once: true });
    }

    // Before a slide is shown: wait for whichever is ready first, its decoded preview or its lossless
    // version (at most 2.5 s); when only the preview was ready, the lossless one fades in on arrival.
    ready(slide) {
        const preview = slide.querySelector('.home-hero__preview');
        const final = this.loadFinal(slide);
        const timeout = new Promise((resolve) => { setTimeout(resolve, 2500); });
        if (!preview?.decode) {
            return Promise.race([final, timeout]);
        }

        preview.loading = 'eager';

        return Promise.race([preview.decode().catch(() => {}), final, timeout]);
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

        const next = this.slideTargets[index];
        this.pending = index;
        this.ready(next).then(() => {
            if (this.pending !== index || index === this.index || !this.element.isConnected) {
                return;
            }

            const previous = this.slideTargets[this.index];
            this.index = index;
            this.renderSlides(previous, next);
            this.renderCaption(next);
            this.renderDots();
            this.startTimer();
        });
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
