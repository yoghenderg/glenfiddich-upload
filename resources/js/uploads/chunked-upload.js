// The transport is deliberately separate from the UI and Laravel bindings.
export class ChunkedUpload {
    constructor(file, transport, chunkSize = 1024 * 1024) {
        this.file = file;
        this.transport = transport;
        this.chunkSize = chunkSize;
        this.session = null;
        this.key = Array.from(crypto.getRandomValues(new Uint8Array(16)), byte => byte.toString(16).padStart(2, '0')).join('');
    }

    async retry(action, signal, onStatus) {
        for (let attempt = 0; ; attempt++) {
            signal?.throwIfAborted();
            try { return await action(); }
            catch (error) {
                if (signal?.aborted || error.name === 'AbortError' || !error.retryable || attempt >= 3) throw error;
                onStatus?.(`Connection interrupted. Retrying (${attempt + 1}/3)…`);
                await new Promise((resolve, reject) => {
                    const abort = () => { clearTimeout(timer); reject(new DOMException('Upload cancelled', 'AbortError')); };
                    const timer = setTimeout(() => { signal?.removeEventListener('abort', abort); resolve(); }, 1000 * 2 ** attempt);
                    signal?.addEventListener('abort', abort, { once: true });
                });
            }
        }
    }

    async run({ signal, onProgress = () => {}, onStatus = () => {} } = {}) {
        const call = action => this.retry(action, signal, onStatus);
        if (!this.session) {
            this.session = await call(() => this.transport.create({ filename: this.file.name, size: this.file.size, mime: this.file.type, chunk_size: this.chunkSize, idempotency_key: this.key }, signal));
        }
        if (!this.session || typeof this.session.id !== 'string' || !this.session.id) throw new Error('The server returned an invalid upload session.');
        const size = this.session.chunk_size ?? this.chunkSize;
        if (!Number.isSafeInteger(size) || size < 1 || size > 4 * 1024 * 1024) throw new Error('The server returned an invalid chunk size.');
        const count = Math.ceil(this.file.size / size);
        const state = await call(() => this.transport.status(this.session.id, signal));
        if (state.complete === true) { onProgress(100); return state; }
        const start = state.next_index;
        if (!Number.isInteger(start) || start < 0 || start > count) throw new Error('The server returned an invalid upload position.');
        // One chunk in flight: bounded memory and predictable server load.
        for (let index = start; index < count; index++) {
            const chunk = this.file.slice(index * size, Math.min((index + 1) * size, this.file.size));
            onStatus(`Uploading ${Math.floor(index * size / this.file.size * 100)}%`);
            const accepted = await call(() => this.transport.chunk(this.session.id, index, chunk, signal, loaded => {
                onProgress(Math.min(99, Math.floor((index * size + Math.min(loaded, chunk.size)) / this.file.size * 100)));
            }));
            if (accepted?.next_index !== index + 1) throw new Error('The server did not confirm this chunk. Please retry.');
            onProgress(Math.min(99, Math.floor(Math.min((index + 1) * size, this.file.size) / this.file.size * 100)));
        }
        onStatus('Finishing upload…');
        const result = await call(() => this.transport.complete(this.session.id, signal));
        if (result?.complete !== true) throw new Error('The server has not confirmed the completed upload. Please retry.');
        onProgress(100);
        return result;
    }

    async cancel() {
        if (this.session) await this.transport.cancel(this.session.id);
        this.session = null;
    }
}
