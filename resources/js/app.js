import '@fontsource/montserrat/latin-400.css';
import '@fontsource/montserrat/latin-500.css';
import '@fontsource/montserrat/latin-600.css';
import '@fontsource/montserrat/latin-700.css';
import { initPagination } from './gallery-pagination';
import { ChunkedUpload } from './uploads/chunked-upload';
import { demoTransport, httpTransport } from './uploads/transports';
import { prepareShareFile, shareMoment } from './share';

const $ = (selector, root = document) => root.querySelector(selector);
let qrCodeModule;

async function drawQr(canvas, value) {
    qrCodeModule ||= import('qrcode');
    const { default: QRCode } = await qrCodeModule;
    await QRCode.toCanvas(canvas, value, { width: 160, margin: 1, color: { dark: '#111111', light: '#ffffff' } });
}

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
            job ||= new ChunkedUpload(selected, form.dataset.uploadEndpoint ? httpTransport(form.dataset.uploadEndpoint) : demoTransport());
            const result = await job.run({ signal: controller.signal,
                onProgress: value => { progressBar.style.width = `${value}%`; progress.setAttribute('aria-valuenow', String(value)); label.textContent = `Uploading ${value}%`; },
                onStatus: message => { feedback.textContent = message; },
            });
            feedback.textContent = result.demo ? 'Preview complete — demo only.' : 'Upload complete.';
            label.textContent = 'Complete'; job = null;
            setBusy(false); button.disabled = true;
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
    initPagination(gallery);
    const viewer = $('#media-viewer'), mediaHost = $('#viewer-media'), feedback = $('#viewer-hint');
    const media = window.demoMedia || [];
    let previousFocus = null, current = null, currentUrl = '', shareFile = null, shareController = null;
    const close = () => { shareController?.abort(); shareFile = null; current = null; viewer.hidden = true; mediaHost.replaceChildren(); document.body.classList.remove('viewer-open'); previousFocus?.focus(); };
    const open = async (item, trigger) => {
        current = item;
        shareController?.abort(); shareFile = null; shareController = new AbortController();
        const request = shareController;
        prepareShareFile(item, request.signal).then(file => { if (!request.signal.aborted && current === item) shareFile = file; });
        previousFocus = trigger;
        mediaHost.replaceChildren();
        const element = document.createElement(item.type === 'video' ? 'video' : 'img');
        element.className = 'media-display';
        element.src = item.src;
        if (item.type === 'video') { element.controls = true; element.playsInline = true; element.poster = item.poster; }
        else element.alt = item.alt;
        mediaHost.append(element);
        $('#viewer-date').textContent = item.date_display;
        $('#viewer-time').textContent = item.time_display;
        $('#viewer-time').dateTime = item.captured_at;
        currentUrl = `${gallery.dataset.guestBase}/${encodeURIComponent(item.id)}`;
        const download = $('#viewer-download'); download.href = item.src; download.download = item.filename;
        feedback.textContent = '';
        try { await drawQr($('#viewer-qr'), currentUrl); }
        catch { feedback.textContent = 'QR code unavailable. Use Share instead.'; }
        viewer.hidden = false;
        document.body.classList.add('viewer-open');
        $('.viewer__close', viewer).focus();
    };
    gallery.addEventListener('click', (event) => {
        const card = event.target.closest('[data-open-media]');
        if (card) { const item = media.find((entry) => entry.id === card.dataset.openMedia); if (item) open(item, card); }
    });
    viewer.addEventListener('click', (event) => { if (event.target.closest('[data-close-viewer]')) close(); });
    viewer.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') close();
        if (event.key !== 'Tab') return;
        const focusable = [...viewer.querySelectorAll('button, a[href]')], first = focusable[0], last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    $('#viewer-share').addEventListener('click', () => current && shareMoment(current, currentUrl, feedback, shareFile));
    $('#gallery-sort')?.addEventListener('change', (event) => event.target.form.requestSubmit());
    const status = $('#live-status'), label = $('#live-label');
    const checkFeed = async () => {
        try {
            const response = await fetch(gallery.dataset.feedUrl, { cache: 'no-store', headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Feed unavailable');
            const data = await response.json();
            status.dataset.status = 'live'; label.textContent = 'LIVE';
            if ($('#media-grid') && data.items.map((item) => item.id).join() !== media.map((item) => item.id).join()) window.location.reload();
        } catch { status.dataset.status = 'reconnecting'; label.textContent = 'RECONNECTING'; }
    };
    $('#retry-gallery')?.addEventListener('click', () => { window.location.href = gallery.dataset.feedUrl.replace('/feed', ''); });
    if (status.dataset.status === 'live') window.setInterval(checkFeed, 20000);
}

initUpload(); initGallery();
