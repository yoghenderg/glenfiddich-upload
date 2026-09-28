import test from 'node:test';
import assert from 'node:assert/strict';
import { ChunkedUpload } from '../../resources/js/uploads/chunked-upload.js';

function fixture({ next = 0, fail = false } = {}) {
    const seen = []; let complete = false, attempts = 0;
    const adapter = {
        create: async () => ({ id: 'test', chunk_size: 4 }),
        status: async () => ({ next_index: next, complete: false }),
        chunk: async (id, index, chunk, signal, progress) => {
            attempts++;
            if (fail && attempts === 1) throw Object.assign(new Error('timeout'), { retryable: true });
            signal?.throwIfAborted(); seen.push([index, await chunk.text()]); progress(chunk.size); return { next_index: index + 1 };
        },
        complete: async () => { complete = true; return { complete: true }; },
        cancel: async () => {},
    };
    return { adapter, seen, get complete() { return complete; }, get attempts() { return attempts; } };
}
const file = () => new File(['abcdefghij'], 'sample.jpg', { type: 'image/jpeg' });

test('sends exact bounded slices sequentially and completes only after all slices', async () => {
    const f = fixture(); const progress = [];
    await new ChunkedUpload(file(), f.adapter).run({ onProgress: value => progress.push(value) });
    assert.deepEqual(f.seen, [[0, 'abcd'], [1, 'efgh'], [2, 'ij']]);
    assert.equal(f.complete, true); assert.equal(progress.at(-1), 100);
    assert.ok(progress.slice(0, -1).every(value => value < 100));
});

test('resumes at the server-confirmed chunk without repeating accepted bytes', async () => {
    const f = fixture({ next: 2 });
    await new ChunkedUpload(file(), f.adapter).run();
    assert.deepEqual(f.seen, [[2, 'ij']]);
});

test('retries a transient chunk failure, then finishes', async () => {
    const f = fixture({ fail: true });
    await new ChunkedUpload(file(), f.adapter).run();
    assert.equal(f.attempts, 4); assert.equal(f.seen.length, 3); assert.equal(f.complete, true);
});

test('cancellation prevents upload and completion', async () => {
    const f = fixture(); const controller = new AbortController(); controller.abort();
    await assert.rejects(new ChunkedUpload(file(), f.adapter).run({ signal: controller.signal }), { name: 'AbortError' });
    assert.equal(f.complete, false); assert.equal(f.seen.length, 0);
});

test('validation errors are not retried', async () => {
    const f = fixture(); let attempts = 0;
    f.adapter.chunk = async () => { attempts++; throw new Error('Invalid media'); };
    await assert.rejects(new ChunkedUpload(file(), f.adapter).run(), /Invalid media/);
    assert.equal(attempts, 1); assert.equal(f.complete, false);
});

test('rejects a corrupt resume position', async () => {
    const f = fixture({ next: 100 });
    await assert.rejects(new ChunkedUpload(file(), f.adapter).run(), /invalid upload position/);
    assert.equal(f.complete, false);
});


test('does not report success without final server confirmation', async () => {
    const f = fixture(); const progress = [];
    f.adapter.complete = async () => ({});
    await assert.rejects(new ChunkedUpload(file(), f.adapter).run({ onProgress: value => progress.push(value) }), /not confirmed/);
    assert.ok(!progress.includes(100));
});

test('stops on an unacknowledged chunk', async () => {
    const f = fixture(); f.adapter.chunk = async () => ({});
    await assert.rejects(new ChunkedUpload(file(), f.adapter).run(), /did not confirm/);
    assert.equal(f.complete, false);
});
