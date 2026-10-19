import { preloadGalleryImages, trackVideoLoading } from './gallery-images.js';

export function pageWindow(total, size, page = 1) {
    const pages = Math.max(1, Math.ceil(total / size));
    const current = Math.max(1, Math.min(pages, Math.trunc(Number(page)) || 1));
    return { current, pages, start: (current - 1) * size, end: Math.min(current * size, total) };
}

export function initPagination(gallery, { reset = false } = {}) {
    const grid = gallery.querySelector('#media-grid');
    const controls = gallery.querySelector('#gallery-pagination');
    if (!grid || !controls) return;
    const cards = [...grid.querySelectorAll('[data-media-card]')];
    const previous = gallery.querySelector('#gallery-previous');
    const next = gallery.querySelector('#gallery-next');
    const summary = gallery.querySelector('#gallery-page-summary');
    const number = gallery.querySelector('#gallery-page-number');
    const sizeFromLayout = () => parseInt(getComputedStyle(grid).getPropertyValue('--gallery-page-size'), 10) || 16;
    const listeners = new AbortController();
    let size = sizeFromLayout(), page = reset ? 1 : (gallery.dataset.page || new URL(location.href).searchParams.get('page') || 1);
    const render = () => {
        const window = pageWindow(cards.length, size, page); page = window.current;
        cards.forEach((card, index) => {
            card.hidden = index < window.start || index >= window.end;
            card.style.setProperty('--card-enter-delay', `${90 + Math.min(Math.max(index - window.start, 0), 7) * 75}ms`);
        });
        void preloadGalleryImages(gallery);
        trackVideoLoading(gallery);
        summary.textContent = `${cards.length} ${cards.length === 1 ? 'item' : 'items'}`;
        number.textContent = `${page} / ${window.pages}`;
        previous.disabled = page === 1; next.disabled = page === window.pages;
        controls.hidden = false;
        gallery.dataset.page = page;
        if (!gallery.closest('[data-workspace-panel]')?.hidden) {
            const url = new URL(location.href);
            if (page === 1) url.searchParams.delete('page'); else url.searchParams.set('page', page);
            history.replaceState(null, '', url);
            document.dispatchEvent(new CustomEvent('gallery:page', { detail: { url } }));
        }
    };
    const changePage = direction => {
        page = Number(page) + direction; render();
        // A disabled control cannot retain keyboard focus on every browser.
        (direction > 0 ? (next.disabled ? previous : next) : (previous.disabled ? next : previous)).focus({ preventScroll: true });
        gallery.querySelector('[data-gallery-scroll]')?.scrollTo({ top: 0, behavior: 'smooth' });
    };
    previous.addEventListener('click', () => changePage(-1), { signal: listeners.signal });
    next.addEventListener('click', () => changePage(1), { signal: listeners.signal });
    window.addEventListener('resize', () => {
        const newSize = sizeFromLayout();
        if (newSize === size) return;
        const anchor = (Number(page) - 1) * size;
        size = newSize; page = Math.floor(anchor / size) + 1; render();
        if (document.activeElement?.hidden) cards[pageWindow(cards.length, size, page).start]?.focus({ preventScroll: true });
    }, { signal: listeners.signal });
    gallery.addEventListener('gallery:paginate', () => {
        size = sizeFromLayout();
        page = new URL(location.href).searchParams.get('page') || 1;
        render();
    }, { signal: listeners.signal });
    render();
    return () => listeners.abort();
}
