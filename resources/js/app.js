import { composePrintPhoto, printPhoto } from './print';
import '@fortawesome/fontawesome-free/css/fontawesome.css';
import '@fortawesome/fontawesome-free/css/solid.css';
import '@fontsource/montserrat/latin-400.css';
import '@fontsource/montserrat/latin-500.css';
import '@fontsource/montserrat/latin-600.css';
import '@fontsource/montserrat/latin-700.css';
import { initPagination, galleryPageSize } from './gallery-pagination';
import { reconcileGalleryCards } from './gallery-updates';
import { initWorkspace } from './workspace';
import { ChunkedUpload } from './uploads/chunked-upload';
import { httpTransport } from './uploads/transports';

const $ = (selector, root = document) => root.querySelector(selector);
function initUpload() {
    const form = $('#upload-form');
    if (!form) return;
    const input = $('#media-file'), zone = $('#drop-zone'), feedback = $('#upload-feedback');
    const preview = $('#drop-preview'), previewMedia = $('#preview-media');
    const button = $('#upload-button'), replace = $('#replace-file');
    const progress = $('#upload-progress'), progressBar = $('#upload-progress-bar');
    const allowed = ['image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/quicktime'];
    let selected = null, objectUrl = null, job = null, controller = null, busy = false;
    const cancel = $('#cancel-upload'), label = $('#upload-button-label'), panel = form.closest('.upload-panel');
    const choose = (file) => {
        if (!file || busy) return;
        feedback.textContent = '';
        feedback.classList.remove('is-error');
        job?.cancel().catch(() => {}); job = null;
        label.textContent = 'Upload media';
        const error = !allowed.includes(file.type) ? 'Choose a JPG, PNG, WEBP, MP4 or MOV file.' : file.size === 0 ? 'This file is empty.' : file.size > 200 * 1024 * 1024 ? 'This file is larger than 200 MB.' : '';
        if (error) {
            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = null;
            selected = null;
            previewMedia.replaceChildren();
            preview.hidden = true;
            $('#drop-empty').hidden = false;
            replace.hidden = true;
            button.disabled = true;
            progress.hidden = true;
            feedback.textContent = error;
            feedback.classList.add('is-error');
            return;
        }
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        selected = file;
        progress.hidden = true;
        progress.setAttribute('aria-valuenow', '0');
        progressBar.style.width = '0%';
        objectUrl = URL.createObjectURL(file);
        previewMedia.replaceChildren();
        const media = document.createElement(file.type.startsWith('video/') ? 'video' : 'img');
        media.src = objectUrl;
        if (media.tagName === 'VIDEO') { media.muted = true; media.playsInline = true; media.controls = true; }
        else media.alt = 'Selected media preview';
        previewMedia.append(media);
        $('#preview-name').textContent = file.name;
        $('#preview-size').textContent = file.size < 1048576 ? `${Math.max(1, Math.round(file.size / 1024))} KB` : `${(file.size / 1048576).toFixed(1)} MB`;
        $('#drop-empty').hidden = true;
        preview.hidden = false;
        replace.hidden = false;
        button.disabled = false;
    };
    zone.addEventListener('click', (event) => { if (!busy && !event.target.closest('video')) input.click(); });
    zone.addEventListener('keydown', (event) => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); if (!busy) input.click(); } });
    input.addEventListener('change', () => choose(input.files?.[0]));
    replace.addEventListener('click', () => input.click());
    ['dragenter', 'dragover'].forEach((type) => zone.addEventListener(type, (event) => { event.preventDefault(); zone.classList.add('is-dragging'); }));
    ['dragleave', 'drop'].forEach((type) => zone.addEventListener(type, (event) => { event.preventDefault(); zone.classList.remove('is-dragging'); }));
    zone.addEventListener('drop', (event) => choose(event.dataTransfer?.files?.[0]));
    const setBusy = value => {
        busy = value; button.disabled = value; replace.disabled = value; input.disabled = value;
        cancel.hidden = !value; panel.dataset.uploading = String(value);
        zone.setAttribute('aria-disabled', String(value));
        zone.setAttribute('aria-busy', String(value));
    };
    cancel.addEventListener('click', () => controller?.abort());
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!selected || busy) return;
        controller = new AbortController();
        setBusy(true); progress.hidden = false;
        feedback.classList.remove('is-error');
        feedback.textContent = 'Preparing your media…';
        label.textContent = 'Uploading…';
        try {
            job ||= new ChunkedUpload(selected, httpTransport(form.dataset.uploadEndpoint));
            const result = await job.run({ signal: controller.signal,
                onProgress: value => { progressBar.style.width = `${value}%`; progress.setAttribute('aria-valuenow', String(value)); label.textContent = `Uploading ${value}%`; },
                onStatus: message => { feedback.textContent = message; },
            });
            feedback.textContent = 'Upload complete. View it in the gallery.';
            label.textContent = 'Complete'; job = null;
            setBusy(false); button.disabled = true;
            document.dispatchEvent(new Event('media:uploaded'));
        } catch (error) {
            setBusy(false);
            if (error.name === 'AbortError') {
                feedback.textContent = 'Upload cancelled.';
                label.textContent = 'Upload media';
                const cancelled = job; job = null;
                cancelled?.cancel().catch(() => {});
            } else {
                feedback.textContent = error.message;
                feedback.classList.add('is-error'); label.textContent = 'Retry upload';
            }
        }
    });
    window.addEventListener('beforeunload', event => { if (busy) { event.preventDefault(); event.returnValue = ''; } });
    window.addEventListener('pagehide', () => { if (objectUrl) URL.revokeObjectURL(objectUrl); });
}

function initGallery() {
    const gallery = $('[data-gallery]');
    if (!gallery) return;
    const sortDropdown = $('.gallery-sort', gallery);
    if (sortDropdown) {
        document.addEventListener('pointerdown', event => {
            if (sortDropdown.open && !sortDropdown.contains(event.target)) sortDropdown.open = false;
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && sortDropdown.open) {
                sortDropdown.open = false;
                $('summary', sortDropdown)?.focus();
            }
        });
    }
    let disposePagination = initPagination(gallery);
    const viewer = $('#media-viewer'), mediaHost = $('#viewer-media'), feedback = $('#viewer-hint');
    let media = window.demoMedia || [];
    const prepared = new WeakMap();
    const itemFor = card => media.find(item => item.id === card.dataset.mediaCard);
    const deleteModal = $('#delete-modal'), deleteConfirm = $('#delete-confirm'), deleteCancel = $('#delete-cancel'), deleteHint = $('#delete-hint');
    let deletion = null, deleting = false;
    const closeDelete = () => {
        if (deleting) return;
        deleteModal.hidden = true;
        document.body.classList.remove('viewer-open');
        deletion?.trigger.focus();
        deletion = null;
    };
    const openDelete = (item, card, trigger) => {
        deletion = { item, card, trigger };
        $('#delete-filename').textContent = item.filename;
        deleteHint.textContent = '';
        deleteConfirm.textContent = 'Delete';
        deleteModal.hidden = false;
        document.body.classList.add('viewer-open');
        deleteCancel.focus();
    };
    deleteModal.addEventListener('click', event => { if (event.target.closest('[data-close-delete]')) closeDelete(); });
    deleteModal.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeDelete();
        if (event.key !== 'Tab') return;
        if (deleting) { event.preventDefault(); return; }
        if (event.shiftKey && document.activeElement === deleteCancel) { event.preventDefault(); deleteConfirm.focus(); }
        else if (!event.shiftKey && document.activeElement === deleteConfirm) { event.preventDefault(); deleteCancel.focus(); }
    });
    deleteConfirm.addEventListener('click', async () => {
        if (!deletion || deleting) return;
        deleting = true;
        deleteConfirm.disabled = deleteCancel.disabled = true;
        deleteConfirm.textContent = 'Deleting...';
        deleteHint.textContent = '';
        try {
            const response = await fetch(deletion.card.dataset.deleteUrl, {
                method: 'DELETE',
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').content },
            });
            if (!response.ok && response.status !== 404) throw new Error('Delete failed');
            deleting = false;
            closeDelete();
            await checkFeed(true);
            const heading = $('#gallery-title');
            heading.setAttribute('tabindex', '-1');
            heading.focus({ preventScroll: true });
        } catch {
            deleteHint.textContent = 'Unable to delete this item. Please try again.';
            deleteConfirm.textContent = 'Delete';
        } finally {
            deleting = false;
            deleteConfirm.disabled = deleteCancel.disabled = false;
        }
    });
    const shareModal = $('#share-modal'), qrHost = $('#share-qr'), shareHint = $('#share-hint');
    let shareTrigger = null, shareRequest = 0, qrCodeModule;
    const closeShare = () => {
        shareRequest++;
        shareModal.hidden = true;
        qrHost.replaceChildren();
        document.body.classList.remove('viewer-open');
        shareTrigger?.focus();
    };
    const openShare = async (item, trigger) => {
        const request = ++shareRequest;
        shareTrigger = trigger;
        const url = gallery.dataset.guestBase + '/' + encodeURIComponent(item.id);
        $('#share-link').href = url;
        qrHost.replaceChildren();
        shareHint.textContent = 'Preparing QR code...';
        shareModal.hidden = false;
        document.body.classList.add('viewer-open');
        $('[data-share-close-button]', shareModal).focus();
        try {
            qrCodeModule ||= import('qrcode');
            const { default: QRCode } = await qrCodeModule;
            const canvas = document.createElement('canvas');
            canvas.setAttribute('role', 'img');
            canvas.setAttribute('aria-label', 'Scan to open and download this moment');
            await QRCode.toCanvas(canvas, url, { width: 320, margin: 4, errorCorrectionLevel: 'M', color: { dark: '#111111', light: '#ffffff' } });
            if (request !== shareRequest) return;
            qrHost.append(canvas);
            shareHint.textContent = '';
        } catch {
            if (request === shareRequest) shareHint.textContent = 'QR code unavailable. Use the link below or reopen Share to try again.';
        }
    };
    shareModal.addEventListener('click', event => { if (event.target.closest('[data-close-share]')) closeShare(); });
    shareModal.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeShare();
        if (event.key !== 'Tab') return;
        const first = $('[data-share-close-button]', shareModal), last = $('#share-link');
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    const prepare = (card, item) => {
        if (prepared.has(card)) return prepared.get(card);
        const state = { photo: item.type === 'image' ? composePrintPhoto(item.src) : Promise.resolve(null) };
        prepared.set(card, state);
        state.photo.catch(() => { prepared.delete(card); });
        return state;
    };
    let previousFocus = null, viewRequest = 0;
    const close = () => { viewRequest++; viewer.hidden = true; mediaHost.replaceChildren(); document.body.classList.remove('viewer-open'); previousFocus?.focus(); };
    const open = async (item, trigger, card) => {
        const request = ++viewRequest;
        previousFocus = trigger;
        mediaHost.replaceChildren();
        const element = document.createElement(item.type === 'video' ? 'video' : 'img');
        element.className = 'media-display';
        if (item.type === 'video') { element.src = item.src; element.controls = true; element.playsInline = true; element.poster = item.poster; }
        else { element.alt = (item.alt || 'Event photo') + ' in the pocket-size frame'; element.hidden = true; }
        mediaHost.append(element);
        feedback.textContent = item.type === 'image' ? 'Preparing preview...' : '';
        viewer.hidden = false;
        document.body.classList.add('viewer-open');
        $('.viewer__close', viewer).focus();
        if (item.type !== 'image') return;
        try {
            const src = await prepare(card, item).photo;
            if (request !== viewRequest) return;
            element.src = src;
            element.hidden = false;
            feedback.textContent = '';
        } catch {
            if (request === viewRequest) feedback.textContent = 'Unable to prepare the preview. Please reopen the photo to try again.';
        }
    };
    const downloadPhoto = async (item, card, button, hint) => {
        button.disabled = true;
        hint.textContent = 'Preparing download...';
        try {
            const link = document.createElement('a');
            link.href = item.type === 'image' ? await prepare(card, item).photo : (item.download || item.src);
            link.download = item.type === 'image' ? (item.filename || 'photo').replace(/\.[^.]+$/, '') + '-framed.png' : item.filename;
            document.body.append(link);
            link.click();
            link.remove();
            hint.textContent = '';
        } catch { hint.textContent = 'Unable to prepare the download. Please try again.'; }
        finally { button.disabled = false; }
    };
    gallery.addEventListener('click', event => {
        const card = event.target.closest('[data-media-card]');
        if (!card) return;
        const item = itemFor(card);
        if (!item) return;
        const action = event.target.closest('[data-card-action]');
        const hint = $('[data-card-hint]', card);
        if (action) {
            hint.textContent = '';
            if (action.dataset.cardAction === 'share') {
                openShare(item, action);
            } else if (action.dataset.cardAction === 'download') downloadPhoto(item, card, action, hint);
            else if (action.dataset.cardAction === 'print' && item.type === 'image') printPhoto(item, hint);
            else if (action.dataset.cardAction === 'delete') openDelete(item, card, action);
            return;
        }
        const trigger = event.target.closest('[data-open-media]');
        if (trigger) open(item, trigger, card);
    });
    viewer.addEventListener('click', event => { if (event.target.closest('[data-close-viewer]')) close(); });
    viewer.addEventListener('keydown', event => {
        if (event.key === 'Escape') close();
        if (event.key !== 'Tab') return;
        const focusable = [...viewer.querySelectorAll('button:not(:disabled), a[href], video[controls]')];
        const first = focusable[0], last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    let feedRequest = null, requestedKey = null;
    let targetPage = window.galleryPagination.page;
    let targetSize = window.galleryPagination.per_page;
    const sortForFeed = () => new URL(gallery.dataset.feedUrl).searchParams.get('sort') || 'none';
    const keyFor = (sort, page, size) => `${sort}|${page}|${size}`;
    let loadedKey = keyFor(sortForFeed(), targetPage, targetSize);
    let loadedVersion = window.galleryVersion;
    const pageCache = new Map();
    if (gallery.dataset.galleryState === 'ready') pageCache.set(loadedKey, {
        items: media, pagination: window.galleryPagination, version: loadedVersion,
        html: $('[data-gallery-results]', gallery).innerHTML, checkedAt: Date.now(),
    });
    const galleryLoading = $('[data-gallery-loading]', gallery);
    const applyPage = (data, sort, scroll = false) => {
        const results = $('[data-gallery-results]', gallery);
        const oldScroll = $('[data-gallery-scroll]', gallery)?.scrollTop || 0;
        const incoming = document.createElement('div');
        incoming.innerHTML = data.html;
        const currentGrid = $('#media-grid', results), incomingGrid = $('#media-grid', incoming);
        disposePagination?.();
        if (currentGrid && incomingGrid && $('#gallery-pagination', results)) {
            reconcileGalleryCards(currentGrid, incomingGrid, media, data.items);
        } else {
            results.replaceChildren(...incoming.childNodes);
        }
        media = data.items;
        targetPage = data.pagination.page;
        targetSize = data.pagination.per_page;
        loadedKey = keyFor(sort, targetPage, targetSize);
        loadedVersion = data.version;
        gallery.dataset.galleryState = 'ready';
        disposePagination = initPagination(gallery, { pagination: data.pagination });
        $('[data-gallery-scroll]', gallery)?.scrollTo({ top: scroll ? 0 : oldScroll });
    };
    const checkFeed = async (force = false, options = {}) => {
        if (document.body.classList.contains('viewer-open')) return;
        targetPage = Math.max(1, Number(options.page ?? targetPage) || 1);
        targetSize = options.perPage ?? targetSize;
        const feedUrl = new URL(gallery.dataset.feedUrl);
        const sort = sortForFeed();
        const key = keyFor(sort, targetPage, targetSize);
        if (!force && feedRequest && requestedKey === key) return;
        feedRequest?.abort();
        feedRequest = null;
        galleryLoading.hidden = true;
        if (force) pageCache.clear();
        const cached = pageCache.get(key);
        if (cached && key !== loadedKey) applyPage(cached, sort, options.scroll);
        if (!force && cached && gallery.dataset.galleryState === 'ready' && Date.now() - cached.checkedAt < 15000) return;
        const controller = new AbortController();
        feedRequest = controller;
        requestedKey = key;
        galleryLoading.hidden = false;
        feedUrl.searchParams.set('page', targetPage);
        feedUrl.searchParams.set('per_page', targetSize);
        const hint = $('[data-gallery-feedback]', gallery);
        const headers = { Accept: 'application/json' };
        if (cached) headers['If-None-Match'] = `"${cached.version}"`;
        try {
            const response = await fetch(feedUrl, { signal: controller.signal, cache: 'no-store', headers });
            if (controller.signal.aborted) return;
            if (response.status === 304 && cached) {
                cached.checkedAt = Date.now();
                hint.textContent = '';
                return;
            }
            if (!response.ok) throw new Error('Feed unavailable');
            const data = await response.json();
            if (controller.signal.aborted || document.body.classList.contains('viewer-open')) return;
            if (key !== loadedKey || data.version !== loadedVersion || gallery.dataset.galleryState !== 'ready') applyPage(data, sort, options.scroll);
            const canonicalKey = keyFor(sort, data.pagination.page, data.pagination.per_page);
            pageCache.delete(canonicalKey);
            pageCache.set(canonicalKey, { ...data, checkedAt: Date.now() });
            while (pageCache.size > 3) pageCache.delete(pageCache.keys().next().value);
            hint.textContent = '';
        } catch (error) {
            if (error.name !== 'AbortError') hint.textContent = 'Could not refresh the gallery. Try again shortly.';
        } finally {
            if (feedRequest === controller) {
                feedRequest = null;
                galleryLoading.hidden = true;
            }
        }
    };
    gallery.addEventListener('click', event => {
        if (event.target.closest('#retry-gallery')) checkFeed(true);
    });
    gallery.addEventListener('gallery:request', event => checkFeed(false, event.detail));
    document.addEventListener('workspace:view', event => {
        if (event.detail.view !== 'gallery') return;
        const sort = event.detail.url.searchParams.get('sort') || 'none';
        const feedUrl = new URL(gallery.dataset.feedUrl);
        const changedSort = sort !== sortForFeed();
        feedUrl.searchParams.set('sort', sort);
        gallery.dataset.feedUrl = feedUrl.href;
        gallery.querySelectorAll('.gallery-sort__menu a').forEach(link => {
            const selected = (new URL(link.href).searchParams.get('sort') || 'none') === sort;
            $('[data-sort-check]', link).hidden = !selected;
            if (selected) link.setAttribute('aria-current', 'page');
            else link.removeAttribute('aria-current');
        });
        checkFeed(false, {
            page: Number(event.detail.url.searchParams.get('page') || 1),
            perPage: galleryPageSize(gallery, targetSize), scroll: changedSort,
        });
    });
    document.addEventListener('media:uploaded', () => checkFeed(true));
    document.addEventListener('visibilitychange', () => { if (!document.hidden) checkFeed(); });
    window.setInterval(() => { if (!document.hidden) checkFeed(); }, 20000);
}

async function initGuest() {
    const guest = $('[data-framed-guest]');
    if (!guest) return;
    const photo = $('.media-display', guest), download = $('.guest-download', guest), hint = $('[data-guest-hint]', guest);
    photo.hidden = true;
    try {
        const src = await composePrintPhoto(guest.dataset.src);
        photo.src = src;
        photo.width = 1800;
        photo.height = 1200;
        photo.hidden = false;
        download.href = src;
        download.download = (guest.dataset.filename || 'photo').replace(/\.[^.]+$/, '') + '-framed.png';
        download.removeAttribute('aria-disabled');
        hint.textContent = '';
    } catch { hint.textContent = 'Unable to prepare your photo. Please reload this page to try again.'; }
}

initWorkspace(); initUpload(); initGallery(); initGuest();
