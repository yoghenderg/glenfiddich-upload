import '@fontsource/montserrat/latin-400.css';
import '@fontsource/montserrat/latin-500.css';
import '@fontsource/montserrat/latin-600.css';
import '@fontsource/montserrat/latin-700.css';
import QRCode from 'qrcode';

const $ = (selector, root = document) => root.querySelector(selector);

async function shareMoment(item, url, feedback) {
    try {
        if (navigator.share) {
            const data = { title: item.title, text: 'Your event moment', url };
            if (item.src && navigator.canShare) {
                try {
                    const response = await fetch(item.src);
                    const blob = await response.blob();
                    const file = new File([blob], item.filename, { type: blob.type || (item.type === 'video' ? 'video/mp4' : 'image/jpeg') });
                    if (navigator.canShare({ files: [file] })) data.files = [file];
                } catch { /* URL sharing still works. */ }
            }
            await navigator.share(data);
        } else if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(url);
            feedback.textContent = 'Link copied. You can paste it into a message.';
        } else window.prompt('Copy this moment link:', url);
    } catch (error) {
        if (error.name !== 'AbortError') feedback.textContent = 'Sharing is unavailable here. Please copy the link instead.';
    }
}

function initUpload() {
    const form = $('#upload-form');
    if (!form) return;
    const input = $('#media-file'), zone = $('#drop-zone'), feedback = $('#upload-feedback');
    const preview = $('#drop-preview'), previewMedia = $('#preview-media');
    const button = $('#upload-button'), replace = $('#replace-file');
    const progress = $('#upload-progress'), progressBar = $('#upload-progress-bar');
    const allowed = ['image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/quicktime'];
    let selected = null, objectUrl = null;
    const choose = (file) => {
        feedback.textContent = '';
        feedback.classList.remove('is-error');
        if (!file) return;
        const error = !allowed.includes(file.type) ? 'Choose a JPG, PNG, WEBP, MP4 or MOV file.' : file.size > 200 * 1024 * 1024 ? 'This file is larger than 200 MB.' : '';
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
    zone.addEventListener('click', (event) => { if (!event.target.closest('video')) input.click(); });
    zone.addEventListener('keydown', (event) => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); input.click(); } });
    input.addEventListener('change', () => choose(input.files?.[0]));
    replace.addEventListener('click', () => input.click());
    ['dragenter', 'dragover'].forEach((type) => zone.addEventListener(type, (event) => { event.preventDefault(); zone.classList.add('is-dragging'); }));
    ['dragleave', 'drop'].forEach((type) => zone.addEventListener(type, (event) => { event.preventDefault(); zone.classList.remove('is-dragging'); }));
    zone.addEventListener('drop', (event) => choose(event.dataTransfer?.files?.[0]));
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!selected) { feedback.textContent = 'Choose a file first.'; feedback.classList.add('is-error'); zone.focus(); return; }
        button.disabled = true;
        replace.disabled = true;
        zone.setAttribute('aria-busy', 'true');
        progress.hidden = false;
        feedback.textContent = 'Preparing your media…';
        feedback.classList.remove('is-error');
        let value = 0;
        const timer = window.setInterval(() => {
            value = Math.min(value + 10, 100);
            progressBar.style.width = `${value}%`;
            progress.setAttribute('aria-valuenow', String(value));
            if (value === 100) {
                window.clearInterval(timer);
                feedback.textContent = 'Preview complete — demo only.';
                button.disabled = false;
                replace.disabled = false;
                zone.removeAttribute('aria-busy');
            }
        }, 90);
    });
    window.addEventListener('pagehide', () => { if (objectUrl) URL.revokeObjectURL(objectUrl); });
}

function initGallery() {
    const gallery = $('[data-gallery]');
    if (!gallery) return;
    const viewer = $('#media-viewer'), mediaHost = $('#viewer-media'), feedback = $('#viewer-hint');
    const media = window.demoMedia || [];
    let previousFocus = null, current = null, currentUrl = '';
    const close = () => { viewer.hidden = true; mediaHost.replaceChildren(); document.body.classList.remove('viewer-open'); previousFocus?.focus(); };
    const open = async (item, trigger) => {
        current = item;
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
        try { await QRCode.toCanvas($('#viewer-qr'), currentUrl, { width: 160, margin: 1, color: { dark: '#111111', light: '#ffffff' } }); }
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
    $('#viewer-share').addEventListener('click', () => current && shareMoment(current, currentUrl, feedback));
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
