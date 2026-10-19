// Hidden views do not trigger native lazy loading. Warm the current page so
// its photos are downloaded and decoded before the user switches to Gallery.
const decoding = new WeakMap();
const decoded = new WeakSet();

function showCardLoading(card, loading) {
    const indicator = card?.querySelector('[data-card-loading]');
    if (indicator) indicator.hidden = !loading;
    card?.querySelector('[data-open-media]')?.setAttribute('aria-busy', String(loading));
}

export function preloadGalleryImages(root) {
    const images = [...root.querySelectorAll('[data-media-card]:not([hidden]) img')];
    const cards = new Map();
    const pending = images.map(image => {
        if (!decoding.has(image)) {
            image.loading = 'eager';
            const ready = image.decode().then(() => { decoded.add(image); }).catch(error => {
                decoding.delete(image);
                throw error;
            });
            decoding.set(image, ready);
        }
        const ready = decoding.get(image);
        const card = image.closest?.('[data-media-card]');
        if (card) {
            if (!cards.has(card)) cards.set(card, []);
            cards.get(card).push({ image, ready });
        }
        return ready;
    });
    cards.forEach((entries, card) => {
        showCardLoading(card, entries.some(({ image }) => !decoded.has(image)));
        Promise.allSettled(entries.map(({ ready }) => ready)).then(results => {
            showCardLoading(card, false);
            const hint = card.querySelector('[data-card-hint]');
            if (hint && !hint.textContent && results.some(result => result.status === 'rejected')) hint.textContent = 'Preview unavailable. Tap the photo to try opening it.';
        });
    });
    return Promise.allSettled(pending);
}

const watchedVideos = new WeakSet();
export function trackVideoLoading(root) {
    root.querySelectorAll('[data-media-card] video').forEach(video => {
        if (watchedVideos.has(video)) return;
        watchedVideos.add(video);
        const card = video.closest('[data-media-card]');
        showCardLoading(card, video.readyState < 1 && !video.error);
        video.addEventListener('loadedmetadata', () => showCardLoading(card, false), { once: true });
        const showError = () => {
            showCardLoading(card, false);
            const hint = card.querySelector('[data-card-hint]');
            if (hint && !hint.textContent) hint.textContent = 'Video preview unavailable. Tap to try opening it.';
        };
        video.addEventListener('error', showError, { once: true });
        if (video.error) showError();
    });
}
