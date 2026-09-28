import test from 'node:test';
import assert from 'node:assert/strict';
import { shareMoment } from '../../resources/js/share.js';

test('native share is called synchronously on tap with the already prepared file', async () => {
    let shared;
    Object.defineProperty(globalThis, 'navigator', { configurable: true, value: { canShare: () => true, share: data => { shared = data; return Promise.resolve(); } } });
    const file = new File(['image'], 'photo.jpg', { type: 'image/jpeg' });
    const pending = shareMoment({ title: 'Moment' }, 'https://example.test/media/1', {}, file);
    assert.deepEqual(shared.files, [file]); // Before awaiting: preserves tap activation.
    await pending;
});

test('shares the guest link when a file is not ready', async () => {
    let shared;
    Object.defineProperty(globalThis, 'navigator', { configurable: true, value: { share: data => { shared = data; return Promise.resolve(); } } });
    await shareMoment({ title: 'Moment' }, 'https://example.test/media/1', {}, null);
    assert.equal(shared.url, 'https://example.test/media/1');
});

test('user dismissal is silent and unsupported browsers copy the link', async () => {
    const feedback = { textContent: '' };
    Object.defineProperty(globalThis, 'navigator', { configurable: true, value: { share: () => Promise.reject(new DOMException('Dismissed', 'AbortError')) } });
    await shareMoment({ title: 'Moment' }, 'https://example.test/media/1', feedback);
    assert.equal(feedback.textContent, '');
    let copied;
    Object.defineProperty(globalThis, 'navigator', { configurable: true, value: { clipboard: { writeText: async value => { copied = value; } } } });
    await shareMoment({ title: 'Moment' }, 'https://example.test/media/1', feedback);
    assert.equal(copied, 'https://example.test/media/1'); assert.equal(feedback.textContent, 'Link copied.');
});
