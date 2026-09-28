// Carlo: endpoint contract is documented in docs/CHUNKED-UPLOAD.md.
function request(method, url, body, signal, onProgress) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        const abort = () => xhr.abort();
        const finish = (callback, value) => { signal?.removeEventListener('abort', abort); callback(value); };
        xhr.open(method, url);
        xhr.timeout = 45000;
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]')?.content || '');
        if (body && !(body instanceof FormData)) { xhr.setRequestHeader('Content-Type', 'application/json'); body = JSON.stringify(body); }
        xhr.upload.onprogress = event => onProgress?.(event.loaded);
        xhr.onload = () => {
            let data;
            try { data = JSON.parse(xhr.responseText || '{}'); } catch { data = {}; }
            if (xhr.status >= 200 && xhr.status < 300) return finish(resolve, data);
            const error = new Error(data.message || (xhr.status === 419 ? 'Session expired. Refresh this page and choose your file again.' : 'Upload could not be completed. Please retry.'));
            error.retryable = xhr.status === 408 || xhr.status === 429 || xhr.status >= 500;
            finish(reject, error);
        };
        xhr.onerror = xhr.ontimeout = () => finish(reject, Object.assign(new Error('Connection interrupted. Tap Retry upload to continue.'), { retryable: true }));
        xhr.onabort = () => finish(reject, new DOMException('Upload cancelled', 'AbortError'));
        if (signal?.aborted) return finish(reject, new DOMException('Upload cancelled', 'AbortError'));
        signal?.addEventListener('abort', abort, { once: true });
        xhr.send(body || null);
    });
}

export function httpTransport(endpoint) {
    const base = new URL(endpoint, window.location.origin);
    if (base.origin !== window.location.origin) throw new Error('Use a same-origin Laravel upload endpoint.');
    const path = base.href.replace(/\/$/, '');
    const url = id => `${path}/${encodeURIComponent(id)}`;
    return {
        create: (metadata, signal) => request('POST', path, metadata, signal),
        status: (id, signal) => request('GET', url(id), null, signal),
        chunk: (id, index, blob, signal, onProgress) => {
            const body = new FormData(); body.append('chunk', blob, 'chunk.bin');
            return request('POST', `${url(id)}/chunks/${index}`, body, signal, onProgress);
        },
        complete: (id, signal) => request('POST', `${url(id)}/complete`, {}, signal),
        cancel: id => request('DELETE', url(id)),
    };
}

// Mock-only adapter. It exercises the same slicing, progress and cancellation
// flow without transmitting, storing or publishing the user's media.
export function demoTransport() {
    let next = 0, count = 0, done = false;
    return {
        create: async metadata => { count = Math.ceil(metadata.size / metadata.chunk_size); return { id: metadata.idempotency_key, chunk_size: metadata.chunk_size }; },
        status: async () => ({ next_index: next, complete: done }),
        chunk: async (id, index, blob, signal, onProgress) => {
            signal?.throwIfAborted();
            await new Promise((resolve, reject) => {
                const abort = () => { clearTimeout(timer); reject(new DOMException('Upload cancelled', 'AbortError')); };
                const timer = setTimeout(() => { signal?.removeEventListener('abort', abort); resolve(); }, 70);
                signal?.addEventListener('abort', abort, { once: true });
            });
            signal?.throwIfAborted();
            onProgress(blob.size); next = index + 1; return { next_index: next };
        },
        complete: async () => { if (next !== count) throw new Error('Upload is incomplete.'); done = true; return { complete: true, demo: true }; },
        cancel: async () => { next = 0; done = false; },
    };
}
