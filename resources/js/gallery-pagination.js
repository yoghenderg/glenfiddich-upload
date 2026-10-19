import { preloadGalleryImages, trackVideoLoading } from './gallery-images.js';

export function pageWindow(total, size, page = 1) {
    const pages = Math.max(1, Math.ceil(total / size));
    const current = Math.max(1, Math.min(pages, Math.trunc(Number(page)) || 1));
    return { current, pages, start: (current - 1) * size, end: Math.min(current * size, total) };
}

export function galleryPageSize(gallery, fallback = 16) {
    const grid = gallery.querySelector('.media-grid');
    return grid ? parseInt(getComputedStyle(grid).getPropertyValue('--gallery-page-size'), 10) || fallback : fallback;
}

export function initPagination(gallery, { pagination = window.galleryPagination } = {}) {
    if (!pagination || !gallery.querySelector('#gallery-pagination')) return;
    const listeners = new AbortController();
    const previous = gallery.querySelector('#gallery-previous');
    const next = gallery.querySelector('#gallery-next');
    const { page, pages, per_page: size, total } = pagination;
    gallery.querySelectorAll('[data-media-card]').forEach((card, index) => {
        card.hidden = false;
        card.style.setProperty('--card-enter-delay', `${90 + Math.min(index, 7) * 75}ms`);
    });
    void preloadGalleryImages(gallery);
    trackVideoLoading(gallery);
    gallery.querySelector('#gallery-page-summary').textContent = `${total} ${total === 1 ? 'item' : 'items'}`;
    gallery.querySelector('#gallery-page-number').textContent = `${page} / ${pages}`;
    previous.disabled = page <= 1;
    next.disabled = page >= pages;
    gallery.dataset.page = page;
    gallery.dataset.perPage = size;
    if (!gallery.closest('[data-workspace-panel]')?.hidden) {
        const url = new URL(location.href);
        if (page === 1) url.searchParams.delete('page'); else url.searchParams.set('page', page);
        if (size === 16) url.searchParams.delete('per_page'); else url.searchParams.set('per_page', size);
        history.replaceState(null, '', url);
        document.dispatchEvent(new CustomEvent('gallery:page', { detail: { url } }));
    }
    const request = (targetPage, perPage = size) => gallery.dispatchEvent(new CustomEvent('gallery:request', {
        detail: { page: targetPage, perPage, scroll: true },
    }));
    previous.addEventListener('click', () => request(page - 1), { signal: listeners.signal });
    next.addEventListener('click', () => request(page + 1), { signal: listeners.signal });
    const resize = () => {
        const desired = galleryPageSize(gallery, size);
        if (desired !== size) request(Math.floor((page - 1) * size / desired) + 1, desired);
    };
    window.addEventListener('resize', resize, { signal: listeners.signal });
    queueMicrotask(() => { if (!listeners.signal.aborted) resize(); });
    if (document.activeElement === next && next.disabled && pages > 1) previous.focus({ preventScroll: true });
    else if (document.activeElement === previous && previous.disabled && pages > 1) next.focus({ preventScroll: true });
    return () => listeners.abort();
}
