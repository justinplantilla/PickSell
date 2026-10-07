/**
 * Product image gallery control for the seller Add/Edit product form.
 *
 * Each image is a tile in one of three micro-states:
 *   uploading - inline progress + Cancel
 *   uploaded  - thumbnail, cover toggle, Move Left / Move Right / Delete, optional alt text
 *   failed    - red alert + helper text, Retry (when retrying can help) / Delete
 *
 * Files upload immediately (XHR, for progress events) to a staging endpoint that returns a token.
 * On submit the form posts gallery[] = ordered "existing:{id}" / "upload:{token}" keys, the
 * gallery_cover key, and gallery_alt[key] for custom alt text. The image at position 1 is the
 * cover unless another was chosen; empty alt text falls back to "[Product Name] - Image [N]".
 *
 * Accessibility (WCAG 2.1 AA): every action is a native button (no drag-and-drop), changes are
 * announced through a polite live region, and focus survives re-renders, including re-renders
 * triggered by an upload finishing in the background.
 */
const MAX_BYTES = 2 * 1024 * 1024;
const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

const ICONS = {
    star: '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-2.9-5.6 2.9 1.1-6.2L3 9.6l6.2-.9Z" fill="currentColor"/>',
    left: '<path d="m15 18-6-6 6-6" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>',
    right: '<path d="m9 18 6-6-6-6" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>',
    remove: '<path d="M18 6 6 18M6 6l12 12" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>',
    warning: '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0zM12 9v4M12 17h.01" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
    plus: '<path d="M12 5v14M5 12h14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>',
};

let localSequence = 0;

function icon(name) {
    return `<svg class="gallery-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">${ICONS[name]}</svg>`;
}

function el(tag, className, html) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (html !== undefined) node.innerHTML = html;
    return node;
}

function button(className, html, label, action, onClick, disabled = false) {
    const node = el('button', className, html);
    node.type = 'button';
    node.setAttribute('aria-label', label);
    node.title = label;
    node.dataset.action = action;
    node.disabled = disabled;
    node.addEventListener('click', onClick);
    return node;
}

export class ProductGallery {
    constructor(root) {
        this.root = root;
        this.form = root.closest('form');
        this.max = Number(root.dataset.max) || 6;
        this.uploadUrl = root.dataset.uploadUrl;
        this.discardUrl = root.dataset.discardUrl;
        this.grid = root.querySelector('[data-gallery-grid]');
        this.input = root.querySelector('[data-gallery-input]');
        this.fields = root.querySelector('[data-gallery-fields]');
        this.count = root.querySelector('[data-gallery-count]');
        this.message = root.querySelector('[data-gallery-message]');
        this.live = root.querySelector('[data-gallery-live]');
        this.nameInput = this.form?.querySelector('[name="name"]');
        this.items = [];
        this.coverKey = null;
        this.pendingFocus = null;

        this.input.addEventListener('change', () => {
            this.addFiles([...this.input.files]);
            this.input.value = '';
        });
        this.nameInput?.addEventListener('input', () => this.refreshAltPlaceholders());
        this.form?.addEventListener('submit', event => this.onSubmit(event));
        this.render();
    }

    /** Replace the gallery with a product's saved images (already in display_order). */
    load(images = [], productName = '') {
        this.items.forEach(item => item.xhr?.abort());
        this.items = images.map(image => ({
            key: `existing:${image.id}`,
            state: 'uploaded',
            url: image.url,
            name: '',
            customAlt: this.isGeneratedAlt(image.alt_text, productName) ? '' : image.alt_text,
        }));
        const primary = images.find(image => image.is_primary);
        this.coverKey = primary ? `existing:${primary.id}` : null;
        this.say('');
        this.render();
    }

    /** Alt text the system generated (legacy product name or "[Name] - Image N") is not shown as custom text. */
    isGeneratedAlt(alt, productName) {
        if (!alt || alt === productName) return true;
        const prefix = `${productName} - Image `;
        return alt.startsWith(prefix) && /^\d+$/.test(alt.slice(prefix.length));
    }

    get productName() {
        return this.nameInput?.value.trim() || 'Product';
    }

    fallbackAlt(index) {
        return `${this.productName} - Image ${index + 1}`;
    }

    get primaryKey() {
        const uploaded = this.items.filter(item => item.state === 'uploaded');
        return (uploaded.find(item => item.key === this.coverKey) ?? uploaded[0])?.key ?? null;
    }

    /** Visible status/warning text below the gallery. */
    say(text, tone = 'info') {
        this.message.textContent = text;
        this.message.dataset.tone = tone;
    }

    /** Screen-reader-only announcement. Cleared first so repeating the same text is re-announced. */
    announce(text) {
        if (!this.live) return;
        this.live.textContent = '';
        window.setTimeout(() => { this.live.textContent = text; }, 50);
    }

    position(item) {
        return this.items.indexOf(item) + 1;
    }

    addFiles(files) {
        const slots = Math.max(this.max - this.items.length, 0);
        const accepted = files.slice(0, slots);
        if (files.length > slots) {
            const text = `Only ${this.max} images are allowed. ${files.length - slots} file(s) were not added.`;
            this.say(text, 'warning');
            this.announce(text);
        } else {
            this.say('');
        }
        accepted.forEach(file => {
            const item = { key: `local:${++localSequence}`, file, preview: URL.createObjectURL(file), name: file.name, customAlt: '' };
            this.items.push(item);
            this.upload(item);
        });
        if (accepted.length && files.length <= slots) {
            this.announce(`Uploading ${accepted.length} ${accepted.length === 1 ? 'image' : 'images'}.`);
        }
        this.render();
    }

    fail(item, error, retryable) {
        Object.assign(item, { state: 'failed', error, retryable });
        this.announce(`Image ${this.position(item)} failed to upload. ${error}`);
    }

    upload(item) {
        const problem = !ACCEPTED_TYPES.includes(item.file.type)
            ? 'Use a JPG, PNG, or WEBP image.'
            : item.file.size > MAX_BYTES ? 'Image must be 2MB or smaller.' : null;
        if (problem) {
            this.fail(item, problem, false);
            return;
        }

        Object.assign(item, { state: 'uploading', progress: 0, error: null });
        const data = new FormData();
        data.append('image', item.file);

        const xhr = new XMLHttpRequest();
        item.xhr = xhr;
        xhr.open('POST', this.uploadUrl);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]')?.content ?? '');
        xhr.upload.addEventListener('progress', event => {
            if (!event.lengthComputable) return;
            item.progress = Math.min(99, Math.round((event.loaded / event.total) * 100));
            this.updateProgress(item);
        });
        xhr.addEventListener('load', () => {
            item.xhr = null;
            let body = {};
            try { body = JSON.parse(xhr.responseText); } catch { /* non-JSON error page */ }
            if (xhr.status >= 200 && xhr.status < 300 && body.token) {
                const oldKey = item.key;
                Object.assign(item, { state: 'uploaded', key: `upload:${body.token}`, url: body.url, progress: 100 });
                if (this.coverKey === oldKey) this.coverKey = item.key;
                this.announce(`Image ${this.position(item)} of ${this.items.length} uploaded.`);
            } else if (xhr.status === 422) {
                this.fail(item, body.errors?.image?.[0] ?? body.message ?? 'This image could not be accepted.', false);
            } else if (xhr.status === 419) {
                this.fail(item, 'Your session expired. Refresh the page and try again.', false);
            } else {
                this.fail(item, 'Upload failed. Check your connection and retry.', true);
            }
            this.render();
        });
        xhr.addEventListener('error', () => {
            item.xhr = null;
            this.fail(item, 'Upload failed. Check your connection and retry.', true);
            this.render();
        });
        xhr.send(data);
    }

    updateProgress(item) {
        const tile = this.tileFor(item.key);
        if (!tile) return;
        tile.querySelector('.gallery-progress-bar')?.style.setProperty('width', `${item.progress}%`);
        const text = tile.querySelector('.gallery-progress-text');
        if (text) text.textContent = `${item.progress}%`;
        tile.querySelector('.gallery-progress')?.setAttribute('aria-valuenow', String(item.progress));
    }

    tileFor(key) {
        return [...this.grid.children].find(tile => tile.dataset.key === key);
    }

    remove(item, confirmed = false) {
        if (item.key.startsWith('upload:') && !confirmed) {
            window.showConfirm('Delete this uploaded product image?').then(accepted => {
                if (accepted) this.remove(item, true);
            });
            return;
        }
        const wasUploading = item.state === 'uploading';
        item.xhr?.abort();
        if (item.preview) URL.revokeObjectURL(item.preview);
        if (item.key.startsWith('upload:')) {
            fetch(`${this.discardUrl}/${encodeURIComponent(item.key.slice(7))}`, {
                method: 'DELETE',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
            }).catch(() => {});
        }
        const index = this.items.indexOf(item);
        this.items.splice(index, 1);
        if (this.coverKey === item.key) this.coverKey = null;
        const next = this.items[Math.min(index, this.items.length - 1)];
        this.pendingFocus = next ? { key: next.key, action: 'remove' } : { add: true };
        this.say('');
        const remaining = `${this.items.length} ${this.items.length === 1 ? 'image' : 'images'} remaining.`;
        this.announce(wasUploading ? `Upload cancelled. ${remaining}` : `Image ${index + 1} deleted. ${remaining}`);
        this.render();
    }

    move(item, delta) {
        const from = this.items.indexOf(item);
        const to = from + delta;
        if (to < 0 || to >= this.items.length) return;
        [this.items[from], this.items[to]] = [this.items[to], this.items[from]];
        // Keep focus on the same arrow; restoreFocus falls back to another control if it is now disabled.
        this.pendingFocus = { key: item.key, action: delta < 0 ? 'left' : 'right' };
        this.announce(`Image moved to position ${to + 1} of ${this.items.length}.`);
        this.render();
    }

    makeCover(item) {
        if (item.key === this.primaryKey) return;
        this.coverKey = item.key;
        this.pendingFocus = { key: item.key, action: 'cover' };
        this.announce(`Image ${this.position(item)} set as cover image.`);
        this.render();
    }

    /** Describe an image for control labels: its position plus a name a seller will recognise. */
    describe(item, index) {
        const name = item.customAlt || item.name;
        return name ? `image ${index + 1} (${name})` : `image ${index + 1}`;
    }

    render() {
        const focus = this.pendingFocus ?? this.captureFocus();
        this.pendingFocus = null;
        const primaryKey = this.primaryKey;
        const total = this.items.length;
        this.grid.replaceChildren();

        this.items.forEach((item, index) => {
            const label = this.describe(item, index);
            const tile = el('li', `gallery-tile is-${item.state}`);
            tile.dataset.key = item.key;
            tile.setAttribute('aria-label', `Image ${index + 1} of ${total}${item.key === primaryKey ? ', cover image' : ''}`);

            const media = el('div', 'gallery-tile-media');
            const img = el('img');
            img.src = item.url || item.preview || '';
            img.alt = item.state === 'uploaded' ? (item.customAlt || this.fallbackAlt(index)) : '';
            img.addEventListener('error', () => { img.hidden = true; });
            media.appendChild(img);
            const actions = el('div', 'gallery-tile-actions');

            if (item.state === 'uploading') {
                const overlay = el('div', 'gallery-tile-overlay');
                overlay.innerHTML = `<span class="gallery-tile-status">Uploading</span>
                    <div class="gallery-progress" role="progressbar" aria-label="Upload progress for ${label.replace(/"/g, '&quot;').replace(/</g, '&lt;')}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${item.progress}"><div class="gallery-progress-bar" style="width:${item.progress}%"></div></div>
                    <span class="gallery-progress-text" aria-hidden="true">${item.progress}%</span>`;
                media.appendChild(overlay);
                actions.appendChild(button('gallery-btn gallery-btn-wide', 'Cancel', `Cancel upload of ${label}`, 'cancel', () => this.remove(item)));
            } else if (item.state === 'failed') {
                const overlay = el('div', 'gallery-tile-overlay', `${icon('warning')}<span class="gallery-tile-error"></span>`);
                const errorId = `gallery-error-${item.key.replace(/\W/g, '')}`;
                const error = overlay.querySelector('.gallery-tile-error');
                error.id = errorId;
                error.textContent = item.error;
                media.appendChild(overlay);
                if (item.retryable) {
                    const retry = button('gallery-btn gallery-btn-wide', 'Retry', `Retry upload of ${label}`, 'retry', () => {
                        this.pendingFocus = { key: item.key, action: 'cancel' };
                        this.upload(item);
                        this.render();
                    });
                    retry.setAttribute('aria-describedby', errorId);
                    actions.appendChild(retry);
                }
                const del = button('gallery-btn gallery-btn-wide', 'Delete', `Delete ${label}`, 'remove', () => this.remove(item));
                del.setAttribute('aria-describedby', errorId);
                actions.appendChild(del);
            } else {
                const isPrimary = item.key === primaryKey;
                const cover = button(
                    `gallery-tag ${isPrimary ? 'is-primary' : 'gallery-make-cover'}`,
                    isPrimary ? `${icon('star')} Primary` : 'Make Cover',
                    `Set ${label} as cover image`,
                    'cover',
                    () => this.makeCover(item),
                );
                cover.setAttribute('aria-pressed', String(isPrimary));
                media.appendChild(cover);

                const row = el('div', 'gallery-tile-row');
                row.append(
                    button('gallery-btn', icon('left'), `Move ${label} left`, 'left', () => this.move(item, -1), index === 0),
                    button('gallery-btn', icon('right'), `Move ${label} right`, 'right', () => this.move(item, 1), index === total - 1),
                    button('gallery-btn gallery-btn-danger', icon('remove'), `Delete ${label}`, 'remove', () => this.remove(item)),
                );

                const alt = el('input', 'gallery-alt');
                alt.type = 'text';
                alt.maxLength = 255;
                alt.value = item.customAlt;
                alt.placeholder = this.fallbackAlt(index);
                alt.dataset.action = 'alt';
                alt.dataset.index = String(index);
                alt.setAttribute('aria-label', `Alt text for image ${index + 1} (optional)`);
                alt.addEventListener('input', () => {
                    item.customAlt = alt.value.trim();
                    img.alt = item.customAlt || this.fallbackAlt(index);
                });
                actions.append(row, alt);
            }

            tile.append(media, actions);
            this.grid.appendChild(tile);
        });

        const full = total >= this.max;
        const addTile = el('li', 'gallery-tile gallery-add-tile');
        const add = button('gallery-add', `${icon('plus')}<span>Add Image</span>`, full ? `Add image, limit of ${this.max} reached` : `Add image, ${total} of ${this.max} used`, 'add', () => this.input.click(), full);
        addTile.appendChild(add);
        this.grid.appendChild(addTile);
        this.count.textContent = `${total} / ${this.max}`;

        this.restoreFocus(focus, add);
    }

    /** Remember which control inside the grid has focus so a background re-render does not drop it. */
    captureFocus() {
        const active = document.activeElement;
        if (!active || !this.grid.contains(active)) return null;
        const tile = active.closest('[data-key]');
        return {
            key: tile?.dataset.key,
            action: active.dataset.action,
            add: active.dataset.action === 'add',
            selection: active.tagName === 'INPUT' ? [active.selectionStart, active.selectionEnd] : null,
        };
    }

    restoreFocus(target, add) {
        if (!target) return;
        if (target.add) {
            (add.disabled ? this.grid.querySelector('button:not(:disabled)') : add)?.focus();
            return;
        }
        const tile = this.tileFor(target.key);
        const control = tile?.querySelector(`[data-action="${target.action}"]:not(:disabled)`)
            ?? tile?.querySelector('button:not(:disabled)');
        control?.focus();
        if (control && target.selection && control.tagName === 'INPUT') control.setSelectionRange(...target.selection);
    }

    refreshAltPlaceholders() {
        this.grid.querySelectorAll('.gallery-alt').forEach(input => {
            const index = Number(input.dataset.index);
            input.placeholder = this.fallbackAlt(index);
            const item = this.items[index];
            const img = input.closest('.gallery-tile')?.querySelector('img');
            if (img && item && !item.customAlt) img.alt = this.fallbackAlt(index);
        });
    }

    onSubmit(event) {
        const blocker = this.items.some(item => item.state === 'uploading')
            ? 'Please wait for uploads to finish before saving.'
            : this.items.some(item => item.state === 'failed') ? 'Retry or delete the failed images before saving.' : null;
        if (blocker) {
            event.preventDefault();
            this.say(blocker, 'warning');
            this.announce(blocker);
            (this.grid.querySelector('.is-uploading button, .is-failed button') ?? this.grid.querySelector('button'))?.focus();
            return;
        }

        this.fields.replaceChildren();
        const hidden = (name, value) => {
            const input = el('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            this.fields.appendChild(input);
        };
        hidden('gallery_managed', '1');
        this.items.forEach(item => {
            hidden('gallery[]', item.key);
            if (item.customAlt) hidden(`gallery_alt[${item.key}]`, item.customAlt);
        });
        if (this.primaryKey) hidden('gallery_cover', this.primaryKey);
    }
}

export function initProductGalleries() {
    return [...document.querySelectorAll('[data-product-gallery]')].map(root => {
        const gallery = new ProductGallery(root);
        root.productGallery = gallery;
        return gallery;
    });
}
