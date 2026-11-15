import { Controller } from '@hotwired/stimulus';
import { announce, fail, gallery, postJson, removeCards, replaceCards, selectedValues, syncChoices } from '../request';

const SAVE_DELAY = 300;

/* Inline editing of the screenshot shown in the lightbox: every change is saved right away. */
export default class extends Controller {
    static targets = ['published', 'game', 'albums', 'tags', 'status'];
    static values = { url: String, deleteUrl: String, token: String, slug: String, confirm: String };

    disconnect() {
        clearTimeout(this.timer);
    }

    save() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => {
            this.queue = (this.queue ?? Promise.resolve()).then(() => this.send());
        }, SAVE_DELAY);
    }

    async send() {
        announce(this.statusTarget, this.element.dataset.saving);
        try {
            const payload = await postJson(this.urlValue, this.tokenValue, {
                published: this.publishedTarget.checked,
                game: this.gameTarget.value,
                albums: selectedValues(this.albumsTarget),
                tags: selectedValues(this.tagsTarget),
            });
            syncChoices(this.albumsTarget, payload.albums);
            syncChoices(this.tagsTarget, payload.tags);
            replaceCards(payload.cards);
            announce(this.statusTarget, this.element.dataset.saved);
        } catch (error) {
            fail(this.statusTarget, `${this.element.dataset.error} (${error.message})`);
        }
    }

    async delete() {
        if (!window.confirm(this.confirmValue)) {
            return;
        }
        try {
            const payload = await postJson(this.deleteUrlValue, this.tokenValue, {});
            removeCards(payload.removed);
            announce(this.statusTarget, this.element.dataset.deleted);
            this.lightbox()?.close();
        } catch (error) {
            fail(this.statusTarget, `${this.element.dataset.error} (${error.message})`);
        }
    }

    lightbox() {
        const root = gallery();

        return root ? this.application.getControllerForElementAndIdentifier(root, 'gallery-lightbox') : null;
    }
}
