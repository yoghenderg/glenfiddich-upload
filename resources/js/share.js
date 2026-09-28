const MAX_SHARE_BYTES = 32 * 1024 * 1024;

// Prepare before a tap. Safari requires share() to run during user activation.
export async function prepareShareFile(item, signal) {
    if (!navigator.share || !navigator.canShare) return null;
    try {
        const response = await fetch(item.src, { signal });
        if (!response.ok) return null;
        const expected = Number(response.headers.get('content-length'));
        if (expected > MAX_SHARE_BYTES) { await response.body?.cancel(); return null; }
        // Read with a size limit even when Content-Length is unavailable.
        const reader = response.body?.getReader();
        if (!reader) return null;
        let total = 0; const parts = [];
        while (true) {
            const { done, value } = await reader.read();
            if (done) break;
            total += value.byteLength;
            if (total > MAX_SHARE_BYTES) { await reader.cancel(); return null; }
            parts.push(value);
        }
        const file = new File(parts, item.filename, { type: response.headers.get('content-type')?.split(';')[0] || (item.type === 'video' ? 'video/mp4' : 'image/jpeg') });
        return navigator.canShare({ files: [file] }) ? file : null;
    } catch { return null; }
}

export async function shareMoment(item, url, feedback, file) {
    try {
        if (navigator.share) {
            // No fetch/await before this call. iOS opens its native share sheet;
            // AirDrop availability is controlled by the device and its settings.
            const data = file && navigator.canShare?.({ files: [file] }) ? { files: [file], title: item.title } : { title: item.title, url };
            await navigator.share(data);
        } else if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(url);
            feedback.textContent = 'Link copied.';
        } else window.prompt('Copy this moment link:', url);
    } catch (error) {
        if (error.name !== 'AbortError') feedback.textContent = 'Sharing is unavailable. Use Download or try again.';
    }
}
