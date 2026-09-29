export function pageWindow(total, size, page = 1) {
    const pages = Math.max(1, Math.ceil(total / size));
    const current = Math.max(1, Math.min(pages, Math.trunc(Number(page)) || 1));
    return { current, pages, start: (current - 1) * size, end: Math.min(current * size, total) };
}

export function initPagination(gallery) {
    const grid = gallery.querySelector('#media-grid');
    const controls = gallery.querySelector('#gallery-pagination');
    if (!grid || !controls) return;
    const cards = [...grid.querySelectorAll('[data-open-media]')];
    const previous = gallery.querySelector('#gallery-previous');
    const next = gallery.querySelector('#gallery-next');
    const summary = gallery.querySelector('#gallery-page-summary');
    const number = gallery.querySelector('#gallery-page-number');
    const sizeFromLayout = () => parseInt(getComputedStyle(grid).getPropertyValue('--gallery-page-size'), 10) || 16;
    let size = sizeFromLayout(), page = new URL(location.href).searchParams.get('page') || 1;
    const render = () => {
        const window = pageWindow(cards.length, size, page); page = window.current;
        cards.forEach((card, index) => { card.hidden = index < window.start || index >= window.end; });
        summary.textContent = `${window.start + 1}–${window.end} of ${cards.length} · Page ${page} of ${window.pages}`;
        number.textContent = `${page} / ${window.pages}`;
        previous.disabled = page === 1; next.disabled = page === window.pages;
        controls.hidden = window.pages === 1;
        const url = new URL(location.href);
        if (page === 1) url.searchParams.delete('page'); else url.searchParams.set('page', page);
        history.replaceState(null, '', url);
    };
    const changePage = direction => {
        page = Number(page) + direction; render();
        // A disabled control cannot retain keyboard focus on every browser.
        (direction > 0 ? (next.disabled ? previous : next) : (previous.disabled ? next : previous)).focus({ preventScroll: true });
        gallery.querySelector('[data-gallery-scroll]')?.scrollTo({ top: 0, behavior: 'smooth' });
    };
    previous.addEventListener('click', () => changePage(-1));
    next.addEventListener('click', () => changePage(1));
    window.addEventListener('resize', () => {
        const newSize = sizeFromLayout();
        if (newSize === size) return;
        const anchor = (Number(page) - 1) * size;
        size = newSize; page = Math.floor(anchor / size) + 1; render();
        if (document.activeElement?.hidden) cards[pageWindow(cards.length, size, page).start]?.focus({ preventScroll: true });
    });
    render();
}
