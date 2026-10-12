/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import { gsap, prefersReducedMotion, smoothScroll } from '../motion';

const SLUG_PLACEHOLDER = 'screenshot-slug';
const SWIPE_DISTANCE = 50;
const XHR_HEADERS = { 'X-Requested-With': 'XMLHttpRequest' };

export default class extends Controller {
    static targets = ['dialog', 'stage', 'media', 'image', 'placeholder', 'panel', 'previous', 'next', 'status'];
    static values = { detailUrl: String, viewUrl: String, initial: String };

    connect() {
        this.viewed = new Set();
        if (this.imageTarget.complete && this.imageTarget.naturalWidth > 0) {
            this.imageLoaded();
        }
        if (this.initialValue) {
            this.show(this.initialValue);
        }
    }

    disconnect() {
        this.request?.abort();
        gsap.killTweensOf([this.dialogTarget, this.mediaTarget, this.panelTarget]);
        if (this.dialogTarget.open) {
            this.dialogTarget.close();
        }
    }

    open(event) {
        if (event.button > 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }
        event.preventDefault();
        const slug = event.params.slug;
        if (!this.dialogTarget.open || !this.isModal) {
            this.openedFromPage = true;
        }
        this.updateUrl(slug, this.dialogTarget.open && this.isModal ? 'replace' : 'push');
        this.show(slug, event.currentTarget);
    }

    syncWithLocation(slug) {
        if (slug && (slug !== this.slug || !this.dialogTarget.open)) {
            this.openedFromPage = false;
            this.show(slug);
            return;
        }
        if (!slug && this.dialogTarget.open) {
            this.hide();
        }
    }

    async show(slug, trigger = null) {
        const isFirstOpen = !this.isModal;
        this.slug = slug;
        if (isFirstOpen) {
            this.present(trigger);
        }
        const card = this.explorer()?.card(slug);
        if (card) {
            this.previewFromCard(card);
        }
        if (isFirstOpen && card) {
            this.zoomFrom(card);
        }
        this.updateNavigation();
        this.recordView(slug);
        await this.loadDetail(slug, !card);
    }

    present(trigger) {
        this.returnFocus = trigger ?? document.activeElement;
        if (this.dialogTarget.open) {
            this.dialogTarget.close();
        }
        this.dialogTarget.showModal();
        this.isModal = true;
        smoothScroll()?.stop();
        document.documentElement.classList.add('gallery-lock');
        if (!prefersReducedMotion) {
            gsap.fromTo(this.dialogTarget, { opacity: 0 }, { opacity: 1, duration: 0.3, ease: 'power2.out' });
            gsap.fromTo(this.panelTarget, { x: 40, opacity: 0 }, { x: 0, opacity: 1, duration: 0.5, delay: 0.1, ease: 'power3.out', clearProps: 'transform,opacity' });
        }
    }

    zoomFrom(card) {
        if (prefersReducedMotion) {
            return;
        }
        const from = card.querySelector('img').getBoundingClientRect();
        const to = this.mediaTarget.getBoundingClientRect();
        if (to.width === 0 || from.width === 0) {
            return;
        }
        gsap.fromTo(this.mediaTarget, {
            x: from.left - to.left,
            y: from.top - to.top,
            scale: from.width / to.width,
            transformOrigin: '0 0',
        }, { x: 0, y: 0, scale: 1, duration: 0.65, ease: 'expo.out', clearProps: 'transform' });
    }

    close() {
        if (this.openedFromPage && window.history.state?.galleryLightbox) {
            window.history.back();
            return;
        }
        this.hide();
    }

    cancel(event) {
        event.preventDefault();
        this.close();
    }

    hide() {
        if (!this.dialogTarget.open || this.hiding) {
            return;
        }
        if (prefersReducedMotion) {
            this.dialogTarget.close();
            return;
        }
        this.hiding = true;
        gsap.to(this.dialogTarget, {
            opacity: 0,
            duration: 0.25,
            ease: 'power2.in',
            onComplete: () => {
                this.hiding = false;
                gsap.set(this.dialogTarget, { clearProps: 'opacity' });
                this.dialogTarget.close();
            },
        });
    }

    closed() {
        this.isModal = false;
        this.request?.abort();
        smoothScroll()?.start();
        document.documentElement.classList.remove('gallery-lock');
        if (new URLSearchParams(window.location.search).has('screenshot')) {
            this.updateUrl(null, 'replace');
        }
        const card = this.explorer()?.card(this.slug);
        const focusTarget = card?.querySelector('a') ?? this.returnFocus;
        this.slug = null;
        if (focusTarget?.isConnected) {
            focusTarget.focus({ preventScroll: false });
        }
    }

    filter(event) {
        if (event.button > 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }
        event.preventDefault();
        this.openedFromPage = false;
        this.explorer()?.visit(event.currentTarget.href);
        this.hide();
    }

    previous() {
        this.go(-1);
    }

    next() {
        this.go(1);
    }

    async go(direction) {
        const slug = await this.explorer()?.neighbour(this.slug, direction);
        if (!slug) {
            return;
        }
        this.updateUrl(slug, 'replace');
        if (!prefersReducedMotion) {
            await gsap.to(this.mediaTarget, { x: -direction * 60, opacity: 0, duration: 0.18, ease: 'power2.in' });
        }
        await this.showAndSlide(slug, direction);
    }

    async showAndSlide(slug, direction) {
        const detail = this.show(slug);
        if (!prefersReducedMotion) {
            gsap.fromTo(this.mediaTarget, { x: direction * 60, opacity: 0 }, { x: 0, opacity: 1, duration: 0.4, ease: 'power3.out', clearProps: 'transform,opacity' });
        }
        await detail;
    }

    keydown(event) {
        if (event.target.matches('input, textarea, select')) {
            return;
        }
        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            this.previous();
        }
        if (event.key === 'ArrowRight') {
            event.preventDefault();
            this.next();
        }
    }

    backdrop(event) {
        if (this.swiped) {
            this.swiped = false;
            return;
        }
        if (event.target === this.dialogTarget || event.target === this.stageTarget) {
            this.close();
        }
    }

    swipeStart(event) {
        this.swipeOrigin = event.pointerType === 'mouse' ? null : { x: event.clientX, y: event.clientY };
    }

    swipeEnd(event) {
        if (!this.swipeOrigin) {
            return;
        }
        const deltaX = event.clientX - this.swipeOrigin.x;
        const deltaY = event.clientY - this.swipeOrigin.y;
        this.swipeOrigin = null;
        if (Math.abs(deltaX) < SWIPE_DISTANCE || Math.abs(deltaX) < Math.abs(deltaY)) {
            return;
        }
        this.swiped = true;
        this.go(deltaX < 0 ? 1 : -1);
    }

    swipeCancel() {
        this.swipeOrigin = null;
    }

    imageLoaded() {
        this.imageTarget.classList.add('is-loaded');
        this.preloadNeighbour();
    }

    previewFromCard(card) {
        const image = card.querySelector('img');
        this.setMedia({
            hero: card.querySelector('a').dataset.hero,
            placeholder: image.currentSrc || image.src,
            ratio: card.style.getPropertyValue('--ratio'),
            tint: card.style.getPropertyValue('--tint'),
            title: card.querySelector('.gallery-card__title').textContent,
        });
    }

    previewFromDetail(detail) {
        this.setMedia({
            hero: detail.dataset.hero,
            placeholder: detail.dataset.placeholder,
            ratio: String(Number(detail.dataset.width) / Math.max(Number(detail.dataset.height), 1)),
            tint: detail.dataset.tint,
            title: detail.dataset.title,
        });
    }

    setMedia({ hero, placeholder, ratio, tint, title }) {
        this.mediaTarget.style.setProperty('--ratio', ratio);
        this.mediaTarget.style.setProperty('--tint', tint);
        this.imageTarget.alt = title;
        if (this.imageTarget.getAttribute('src') === hero) {
            return;
        }
        this.placeholderTarget.src = placeholder;
        this.imageTarget.classList.remove('is-loaded');
        this.imageTarget.src = hero;
    }

    async loadDetail(slug, previewWhenLoaded) {
        if (this.panelTarget.querySelector('.lightbox-detail')?.dataset.slug === slug) {
            return;
        }
        this.request?.abort();
        this.request = new AbortController();
        this.panelTarget.setAttribute('aria-busy', 'true');
        this.statusTarget.textContent = this.statusTarget.dataset.loading;
        try {
            const response = await fetch(this.detailUrlValue.replace(SLUG_PLACEHOLDER, encodeURIComponent(slug)), { headers: XHR_HEADERS, signal: this.request.signal });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            this.renderDetail(await response.text(), previewWhenLoaded);
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.statusTarget.textContent = this.statusTarget.dataset.error;
            }
        }
    }

    renderDetail(html, previewWhenLoaded) {
        this.panelTarget.innerHTML = html;
        this.panelTarget.scrollTop = 0;
        this.panelTarget.removeAttribute('aria-busy');
        this.statusTarget.textContent = '';
        const detail = this.panelTarget.querySelector('.lightbox-detail');
        if (previewWhenLoaded && detail) {
            this.previewFromDetail(detail);
        }
        if (!prefersReducedMotion && detail) {
            gsap.from(detail.children, { opacity: 0, y: 12, duration: 0.4, stagger: 0.04, ease: 'power2.out', clearProps: 'transform,opacity' });
        }
    }

    updateNavigation() {
        const explorer = this.explorer();
        this.previousTarget.disabled = !explorer?.hasNeighbour(this.slug, -1);
        this.nextTarget.disabled = !explorer?.hasNeighbour(this.slug, 1);
    }

    preloadNeighbour() {
        const explorer = this.explorer();
        const cards = explorer?.cardTargets ?? [];
        const index = cards.findIndex((card) => card.dataset.slug === this.slug);
        const hero = cards[index + 1]?.querySelector('a').dataset.hero;
        if (index !== -1 && hero) {
            new Image().src = hero;
        }
    }

    recordView(slug) {
        if (this.viewed.has(slug)) {
            return;
        }
        this.viewed.add(slug);
        fetch(this.viewUrlValue.replace(SLUG_PLACEHOLDER, encodeURIComponent(slug)), {
            method: 'POST',
            headers: XHR_HEADERS,
            credentials: 'same-origin',
            keepalive: true,
        }).catch(() => this.viewed.delete(slug));
    }

    updateUrl(slug, mode) {
        const url = new URL(window.location.href);
        url.searchParams.delete('screenshot');
        if (slug) {
            url.searchParams.set('screenshot', slug);
        }
        const state = { ...(window.history.state ?? {}), galleryLightbox: Boolean(slug) };
        window.history[mode === 'replace' ? 'replaceState' : 'pushState'](state, '', url);
    }

    explorer() {
        return this.application.getControllerForElementAndIdentifier(this.element, 'gallery-explorer');
    }
}
