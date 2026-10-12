/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import { Flip } from 'gsap/Flip';
import { gsap, prefersReducedMotion, smoothScroll } from '../motion';

gsap.registerPlugin(Flip);

const FILTER_KEYS = ['q', 'album', 'tag', 'game', 'year', 'month', 'from', 'to', 'color', 'sort'];
const DEFAULT_SORT = 'taken';
const SEARCH_DELAY = 250;
const DRAWER_MEDIA = '(max-width: 1023.98px)';
const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), summary, [tabindex]:not([tabindex="-1"])';
const XHR_HEADERS = { 'X-Requested-With': 'XMLHttpRequest' };

export default class extends Controller {
    static targets = ['form', 'search', 'status', 'results', 'heading', 'grid', 'card', 'more', 'drawer', 'drawerToggle', 'drawerSubmit', 'backdrop', 'intro'];
    static values = { resultsUrl: String, pageUrl: String };

    connect() {
        this.element.classList.add('is-enhanced');
        this.currentQuery = filterQuery(new URLSearchParams(window.location.search));
        this.drawerMedia = window.matchMedia(DRAWER_MEDIA);
        this.onDrawerMediaChange = () => this.closeDrawer({ restoreFocus: false });
        this.drawerMedia.addEventListener('change', this.onDrawerMediaChange);
        this.observeMore();
        this.playIntro();
    }

    disconnect() {
        this.observer?.disconnect();
        this.request?.abort();
        clearTimeout(this.searchTimer);
        this.drawerMedia.removeEventListener('change', this.onDrawerMediaChange);
        this.unlockScroll();
        gsap.killTweensOf([...this.introTargets, ...this.cardTargets]);
    }

    cardTargetConnected(card) {
        const image = card.querySelector('img');
        const reveal = () => card.classList.add('is-loaded');
        if (image.complete && image.naturalWidth > 0) {
            reveal();
            return;
        }
        image.addEventListener('load', reveal, { once: true });
        image.addEventListener('error', reveal, { once: true });
    }

    navigate(event) {
        if (isModifiedClick(event)) {
            return;
        }
        event.preventDefault();
        this.visit(event.currentTarget.href);
    }

    search() {
        clearTimeout(this.searchTimer);
        this.searchTimer = setTimeout(() => this.visit(this.formUrl(), { typing: true }), SEARCH_DELAY);
    }

    clearSearch(event) {
        if (this.searchTarget.value === '') {
            this.searchTarget.blur();
            return;
        }
        event.preventDefault();
        this.searchTarget.value = '';
        this.submit();
    }

    submit(event) {
        event?.preventDefault();
        clearTimeout(this.searchTimer);
        this.visit(this.formUrl());
    }

    shortcut(event) {
        if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey || isTyping(event.target) || this.element.querySelector('dialog[open]')) {
            return;
        }
        event.preventDefault();
        this.closeDrawer({ restoreFocus: false });
        this.searchTarget.focus();
        this.searchTarget.select();
    }

    restore() {
        const parameters = new URLSearchParams(window.location.search);
        this.load(filterQuery(parameters));
        this.lightbox()?.syncWithLocation(parameters.get('screenshot'));
    }

    visit(url, { typing = false } = {}) {
        const query = filterQuery(new URL(url, window.location.href).searchParams);
        if (query === this.currentQuery) {
            return Promise.resolve();
        }
        const replace = typing && window.history.state?.gallerySearch;
        window.history[replace ? 'replaceState' : 'pushState']({ gallerySearch: typing }, '', this.pageUrlValue + (query ? `?${query}` : ''));

        return this.load(query, { scroll: true });
    }

    async load(query, { scroll = false } = {}) {
        if (query === this.currentQuery) {
            return;
        }
        this.currentQuery = query;
        this.request?.abort();
        this.request = new AbortController();
        this.setBusy(true);
        try {
            const response = await fetch(`${this.resultsUrlValue}?${query}`, { headers: XHR_HEADERS, signal: this.request.signal });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            this.swap(await response.text(), scroll);
            this.syncForm(new URLSearchParams(query));
            this.setBusy(false);
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }
            this.setBusy(false);
            this.statusTarget.textContent = this.statusTarget.dataset.error;
        }
    }

    swap(html, scroll) {
        const fragment = parseFragment(html);
        const focusedId = document.activeElement?.id;
        const focusWasInRegion = Boolean(document.activeElement?.closest('[data-gallery-region]'));
        const flipState = prefersReducedMotion ? null : Flip.getState(this.cardTargets);

        fragment.querySelectorAll('[data-gallery-region]').forEach((region) => this.replaceRegion(region));
        this.restoreFocus(focusedId, focusWasInRegion);
        this.announce();
        this.observeMore();
        this.animateResults(flipState);
        if (scroll) {
            this.scrollToResults();
        }
    }

    replaceRegion(region) {
        this.element.querySelector(`[data-gallery-region="${region.dataset.galleryRegion}"]`)?.replaceWith(region);
    }

    restoreFocus(focusedId, focusWasInRegion) {
        if (!focusWasInRegion) {
            return;
        }
        const target = focusedId ? document.getElementById(focusedId) : null;
        (target ?? this.headingTarget).focus({ preventScroll: true });
    }

    announce() {
        const summary = this.element.querySelector('[data-gallery-region="summary"]');
        this.statusTarget.textContent = summary.dataset.status;
        this.drawerSubmitTarget.textContent = summary.dataset.showResults;
    }

    animateResults(flipState) {
        if (!flipState) {
            return;
        }
        Flip.from(flipState, {
            targets: this.cardTargets,
            duration: 0.55,
            ease: 'power3.out',
            scale: true,
            prune: true,
            onEnter: (cards) => gsap.fromTo(cards, { opacity: 0, scale: 0.94, y: 18 }, { opacity: 1, scale: 1, y: 0, duration: 0.5, stagger: 0.03, ease: 'power3.out', clearProps: 'transform,opacity' }),
        });
    }

    scrollToResults() {
        const top = this.resultsTarget.getBoundingClientRect().top;
        const headerHeight = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--naas-header-height')) || 72;
        if (top >= headerHeight || this.isDrawerOpen()) {
            return;
        }
        const offset = -headerHeight - 16;
        const lenis = smoothScroll();
        if (lenis) {
            lenis.scrollTo(this.resultsTarget, { offset });
            return;
        }
        window.scrollTo({ top: window.scrollY + top + offset, behavior: prefersReducedMotion ? 'auto' : 'smooth' });
    }

    setBusy(busy) {
        this.resultsTarget.classList.toggle('is-loading', busy);
        this.resultsTarget.setAttribute('aria-busy', String(busy));
    }

    syncForm(parameters) {
        [...this.formTarget.elements]
            .filter((control) => control.name && !control.closest('[data-gallery-region]'))
            .filter((control) => !(control === this.searchTarget && document.activeElement === control))
            .forEach((control) => { control.value = parameters.get(control.name) ?? (control.name === 'sort' ? DEFAULT_SORT : ''); });
    }

    formUrl() {
        return `${this.pageUrlValue}?${new URLSearchParams(new FormData(this.formTarget))}`;
    }

    async loadMore(event) {
        event?.preventDefault();
        this.pendingMore ??= this.fetchMore().finally(() => { this.pendingMore = null; });

        return this.pendingMore;
    }

    async fetchMore() {
        const more = this.hasMoreTarget ? this.moreTarget : null;
        const url = more?.dataset.url;
        if (!url) {
            return;
        }
        const response = await fetch(url, { headers: XHR_HEADERS });
        if (!response.ok || !more.isConnected) {
            return;
        }
        const fragment = parseFragment(await response.text());
        const cards = [...fragment.querySelectorAll('.gallery-card')];
        const focusMoved = more.contains(document.activeElement);
        this.gridTarget.append(...cards);
        more.replaceWith(fragment.querySelector('.gallery-more'));
        this.observeMore();
        this.revealCards(cards);
        if (focusMoved && cards.length > 0) {
            cards[0].querySelector('a').focus({ preventScroll: true });
        }
    }

    revealCards(cards) {
        if (prefersReducedMotion || cards.length === 0) {
            return;
        }
        gsap.fromTo(cards, { opacity: 0, y: 24 }, { opacity: 1, y: 0, duration: 0.6, stagger: 0.04, ease: 'power3.out', clearProps: 'transform,opacity' });
    }

    observeMore() {
        this.observer?.disconnect();
        if (!this.hasMoreTarget || !this.moreTarget.dataset.url) {
            return;
        }
        this.observer = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                this.loadMore();
            }
        }, { rootMargin: '800px 0px' });
        this.observer.observe(this.moreTarget);
    }

    hasMorePages() {
        return this.hasMoreTarget && Boolean(this.moreTarget.dataset.url);
    }

    async neighbour(slug, direction) {
        const slugs = this.cardTargets.map((card) => card.dataset.slug);
        const index = slugs.indexOf(slug);
        if (index === -1) {
            return null;
        }
        const isLast = direction > 0 && index === slugs.length - 1;
        if (!isLast || !this.hasMorePages()) {
            return slugs[index + direction] ?? null;
        }
        await this.loadMore();

        return this.cardTargets.length > slugs.length ? this.neighbour(slug, direction) : null;
    }

    hasNeighbour(slug, direction) {
        const slugs = this.cardTargets.map((card) => card.dataset.slug);
        const index = slugs.indexOf(slug);
        if (index === -1) {
            return false;
        }

        return Boolean(slugs[index + direction]) || (direction > 0 && this.hasMorePages());
    }

    card(slug) {
        return this.cardTargets.find((card) => card.dataset.slug === slug) ?? null;
    }

    openDrawer() {
        this.drawerTarget.classList.add('is-open');
        this.drawerTarget.setAttribute('role', 'dialog');
        this.drawerTarget.setAttribute('aria-modal', 'true');
        this.drawerToggleTarget.setAttribute('aria-expanded', 'true');
        this.backdropTarget.hidden = false;
        this.lockScroll();
        this.drawerTarget.querySelector('.gallery-filters__close').focus();
    }

    closeDrawer({ restoreFocus = true } = {}) {
        if (!this.isDrawerOpen()) {
            return;
        }
        this.drawerTarget.classList.remove('is-open');
        this.drawerTarget.removeAttribute('role');
        this.drawerTarget.removeAttribute('aria-modal');
        this.drawerToggleTarget.setAttribute('aria-expanded', 'false');
        this.backdropTarget.hidden = true;
        this.unlockScroll();
        if (restoreFocus) {
            this.drawerToggleTarget.focus();
        }
    }

    trapDrawer(event) {
        if (!this.isDrawerOpen()) {
            return;
        }
        if (event.key === 'Escape') {
            this.closeDrawer();
            return;
        }
        if (event.key === 'Tab') {
            trapFocus(event, this.drawerTarget);
        }
    }

    isDrawerOpen() {
        return this.drawerTarget.classList.contains('is-open');
    }

    lockScroll() {
        smoothScroll()?.stop();
        document.documentElement.classList.add('gallery-lock');
    }

    unlockScroll() {
        smoothScroll()?.start();
        document.documentElement.classList.remove('gallery-lock');
    }

    lightbox() {
        return this.application.getControllerForElementAndIdentifier(this.element, 'gallery-lightbox');
    }

    playIntro() {
        if (prefersReducedMotion) {
            return;
        }
        gsap.from(this.introTargets, { opacity: 0, y: 24, duration: 0.8, stagger: 0.08, ease: 'power3.out', clearProps: 'transform,opacity' });
        gsap.from(this.cardTargets.slice(0, 18), { opacity: 0, y: 32, scale: 0.97, duration: 0.8, stagger: 0.05, delay: 0.2, ease: 'power3.out', clearProps: 'transform,opacity' });
    }
}

function filterQuery(parameters) {
    const query = new URLSearchParams();
    FILTER_KEYS
        .map((key) => [key, (parameters.get(key) ?? '').trim()])
        .filter(([key, value]) => value !== '' && !(key === 'sort' && value === DEFAULT_SORT))
        .forEach(([key, value]) => query.set(key, value));

    return query.toString();
}

function parseFragment(html) {
    const template = document.createElement('template');
    template.innerHTML = html;

    return template.content;
}

function isModifiedClick(event) {
    return event.button > 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey;
}

function isTyping(element) {
    return element.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(element.tagName);
}

function trapFocus(event, container) {
    const focusable = [...container.querySelectorAll(FOCUSABLE)].filter((element) => element.offsetParent !== null);
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
        return;
    }
    if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}
