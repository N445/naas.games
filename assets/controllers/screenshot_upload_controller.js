import { Controller } from '@hotwired/stimulus';

const PARALLEL_UPLOADS = 3;

export default class extends Controller {
    static targets = ['dropzone', 'input', 'game', 'album', 'newAlbum', 'summary', 'progress', 'status', 'errors', 'results'];
    static values = { uploadUrl: String, albumUrl: String, csrfToken: String };

    connect() {
        this.toggleNewAlbum();
        this.selectValue(this.gameTarget, localStorage.getItem('screenshot-upload-game') ?? '');
        this.gameTarget.addEventListener('change', () => localStorage.setItem('screenshot-upload-game', this.gameTarget.value));
    }

    toggleNewAlbum() {
        this.newAlbumTarget.hidden = this.albumTarget.value !== 'new';
        if (!this.newAlbumTarget.hidden) {
            this.newAlbumTarget.focus();
        }
    }

    highlight(event) {
        event.preventDefault();
        this.dropzoneTarget.classList.add('dropzone--active');
    }

    unhighlight() {
        this.dropzoneTarget.classList.remove('dropzone--active');
    }

    drop(event) {
        event.preventDefault();
        this.unhighlight();
        this.upload([...event.dataTransfer.files]);
    }

    select() {
        this.upload([...this.inputTarget.files]);
        this.inputTarget.value = '';
    }

    async upload(files) {
        const images = files.filter((file) => file.type.startsWith('image/'));
        if (images.length === 0 || this.busy) {
            return;
        }

        this.busy = true;
        try {
            const albumId = await this.resolveAlbum();
            this.startBatch(images);
            const queue = [...images];
            const workers = Array.from({ length: PARALLEL_UPLOADS }, () => this.consume(queue, albumId));
            await Promise.all(workers);
            this.finishBatch();
        } catch (error) {
            this.reportError(error.message);
        } finally {
            this.busy = false;
        }
    }

    async consume(queue, albumId) {
        while (queue.length > 0) {
            const file = queue.shift();
            await this.send(file, albumId).catch((error) => this.reportError(`${file.name} : ${error.message}`));
        }
    }

    async resolveAlbum() {
        if (this.albumTarget.value !== 'new') {
            return this.albumTarget.value;
        }

        const response = await this.post(this.albumUrlValue, JSON.stringify({ title: this.newAlbumTarget.value }), {
            'Content-Type': 'application/json',
        });
        this.addAlbumOption(String(response.id), response.title);
        this.toggleNewAlbum();
        this.newAlbumTarget.value = '';

        return String(response.id);
    }

    selectValue(select, value) {
        if (select.tomselect) {
            select.tomselect.setValue(value, true);
            return;
        }
        select.value = value;
    }

    addAlbumOption(value, title) {
        const tomSelect = this.albumTarget.tomselect;
        if (!tomSelect) {
            this.albumTarget.add(new Option(title, value, true, true), this.albumTarget.options.length - 1);
            return;
        }
        tomSelect.addOption({ value, text: title });
        tomSelect.setValue(value, true);
    }

    send(file, albumId) {
        const body = new FormData();
        body.append('file', file);
        body.append('album', albumId);
        body.append('game', this.gameTarget.value);

        return new Promise((resolve, reject) => {
            const request = new XMLHttpRequest();
            request.open('POST', this.uploadUrlValue);
            request.setRequestHeader('X-CSRF-Token', this.csrfTokenValue);
            request.responseType = 'json';
            request.upload.addEventListener('progress', (event) => this.trackProgress(file, event.loaded / event.total));
            request.addEventListener('load', () => {
                if (request.status !== 200) {
                    reject(new Error(request.response?.error ?? `erreur ${request.status}`));
                    return;
                }
                this.completeFile(file, request.response.html);
                resolve();
            });
            request.addEventListener('error', () => reject(new Error('connexion interrompue')));
            request.send(body);
        });
    }

    async post(url, body, headers) {
        const response = await fetch(url, { method: 'POST', body, headers: { ...headers, 'X-CSRF-Token': this.csrfTokenValue } });
        const payload = await response.json();
        if (!response.ok) {
            throw new Error(payload.error ?? `erreur ${response.status}`);
        }

        return payload;
    }

    startBatch(files) {
        this.progress = new Map(files.map((file) => [file, 0]));
        this.completed = 0;
        this.errorsTarget.replaceChildren();
        this.summaryTarget.hidden = false;
        this.render();
    }

    trackProgress(file, ratio) {
        this.progress.set(file, ratio);
        this.render();
    }

    completeFile(file, html) {
        this.progress.set(file, 1);
        this.completed += 1;
        this.resultsTarget.insertAdjacentHTML('afterbegin', html);
        this.render();
    }

    finishBatch() {
        this.statusTarget.textContent = `${this.completed} / ${this.progress.size} screenshot(s) importé(s). Vous pouvez les renommer, les classer et les taguer ci-dessous.`;
    }

    render() {
        const ratios = [...this.progress.values()];
        const percent = Math.round((ratios.reduce((sum, ratio) => sum + ratio, 0) / ratios.length) * 100);
        this.progressTarget.style.width = `${percent}%`;
        this.statusTarget.textContent = `Import en cours : ${this.completed} / ${ratios.length} (${percent} %)`;
    }

    reportError(message) {
        const item = document.createElement('li');
        item.textContent = message;
        this.errorsTarget.append(item);
    }
}
