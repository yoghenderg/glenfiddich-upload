export function initWorkspace() {
    const panels = [...document.querySelectorAll('[data-workspace-panel]')];
    if (!panels.length) return;
    const uploadLink = document.querySelector('[data-workspace-link="upload"]');
    const galleryLink = document.querySelector('[data-workspace-link="gallery"]');
    const uploadPath = new URL(uploadLink.href).pathname;
    const galleryPath = new URL(galleryLink.href).pathname;
    let galleryUrl = new URL(galleryLink.href);
    let viewChange = 0;
    const show = (url, push = false) => {
        const view = url.pathname === galleryPath ? 'gallery' : 'upload';
        const previousView = panels.find(panel => !panel.hidden)?.dataset.workspacePanel;
        if (view === 'gallery') galleryUrl = new URL(url);
        // Retain the gallery filter and pagination while the upload form is visible.
        galleryLink.href = galleryUrl.href;
        if (push && url.href !== location.href) history.pushState(null, '', url);
        panels.forEach(panel => { panel.hidden = panel.dataset.workspacePanel !== view; });
        document.body.classList.toggle('gallery-page', view === 'gallery');
        document.body.classList.toggle('upload-page', view === 'upload');
        if (view !== previousView) {
            // The shared header stays mounted, so explicitly replay its entrance.
            document.body.dataset.workspaceEntry = document.body.dataset.workspaceEntry === 'b' ? 'a' : 'b';
        }
        document.querySelectorAll('[data-workspace-link]').forEach(link => {
            const active = link.dataset.workspaceLink === view;
            link.classList.toggle('is-active', active);
            if (active) link.setAttribute('aria-current', 'page');
            else link.removeAttribute('aria-current');
        });
        document.querySelectorAll('.gallery-sort[open]').forEach(menu => { menu.open = false; });
        document.title = `${view === 'gallery' ? 'Gallery' : 'Upload'} · Ray-Ban`;
        // Paint the selected view before pagination, decoding, or feed work.
        const change = ++viewChange;
        requestAnimationFrame(() => setTimeout(() => {
            if (change !== viewChange) return;
            document.dispatchEvent(new CustomEvent('workspace:view', { detail: { view, url } }));
        }, 0));
    };
    document.addEventListener('click', event => {
        const link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target || link.hasAttribute('download')) return;
        let url = new URL(link.href);
        if (url.hash || url.origin !== location.origin || ![uploadPath, galleryPath].includes(url.pathname)) return;
        event.preventDefault();
        if (link === galleryLink) url = galleryUrl;
        // Pagination may have updated the current URL since the last tab switch.
        if (location.pathname === galleryPath && url.pathname === uploadPath) galleryUrl = new URL(location.href);
        show(url, true);
    });
    window.addEventListener('popstate', () => show(new URL(location.href)));
    document.addEventListener('gallery:page', event => {
        galleryUrl = new URL(event.detail.url);
        galleryLink.href = galleryUrl.href;
    });
}
