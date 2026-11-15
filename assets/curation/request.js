/* Helpers shared by the gallery editing controllers. */

export async function postJson(url, token, body) {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-Token': token,
            'X-Requested-With': 'XMLHttpRequest',
            'X-Gallery-Query': window.location.search.slice(1),
            'X-Gallery-Locale': document.documentElement.lang,
        },
        body: JSON.stringify(body),
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        throw new Error(payload.error ?? `HTTP ${response.status}`);
    }

    return payload;
}

export function gallery() {
    return document.getElementById('gallery');
}

export function replaceCards(cards) {
    Object.entries(cards ?? {}).forEach(([slug, html]) => {
        const card = gallery()?.querySelector(`.gallery-card[data-slug="${CSS.escape(slug)}"]`);
        if (!card) {
            return;
        }
        const template = document.createElement('template');
        template.innerHTML = html.trim();
        const fresh = template.content.firstElementChild;
        fresh.classList.add('is-loaded');
        card.replaceWith(fresh);
    });
}

export function removeCards(slugs) {
    (slugs ?? []).forEach((slug) => gallery()?.querySelector(`.gallery-card[data-slug="${CSS.escape(slug)}"]`)?.remove());
}

export function selectedValues(select) {
    return [...select.selectedOptions].map((option) => option.value).filter((value) => value !== '');
}

/* Swaps "new:…" options for the entities the server just created, so they are not created twice. */
export function syncChoices(select, items, { multiple = true } = {}) {
    const tomSelect = select.tomselect;
    if (!tomSelect || !items) {
        return;
    }
    Object.keys(tomSelect.options).filter((value) => value.startsWith('new:')).forEach((value) => tomSelect.removeOption(value, true));
    items.forEach(({ id, label }) => tomSelect.addOption({ value: String(id), text: label }));
    tomSelect.setValue(multiple ? items.map(({ id }) => String(id)) : String(items[0]?.id ?? ''), true);
}

export function announce(target, message) {
    target.textContent = message;
    target.classList.remove('is-error');
}

export function fail(target, message) {
    target.textContent = message;
    target.classList.add('is-error');
}
