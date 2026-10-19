import test from 'node:test';
import assert from 'node:assert/strict';
import { preloadGalleryImages } from '../../resources/js/gallery-images.js';

test('warms gallery images even while their view is hidden and waits for decoding', async () => {
    let finishDecode;
    const image = { loading: 'lazy', decode: () => new Promise(resolve => { finishDecode = resolve; }) };
    const root = { querySelectorAll: () => [image] };
    let ready = false;
    const preload = preloadGalleryImages(root).then(() => { ready = true; });
    assert.equal(image.loading, 'eager');
    await Promise.resolve();
    assert.equal(ready, false);
    finishDecode();
    await preload;
    assert.equal(ready, true);
});

test('a broken photo does not prevent other gallery images becoming ready', async () => {
    const root = { querySelectorAll: () => [
        { decode: () => Promise.reject(new Error('Missing image')) },
        { decode: () => Promise.resolve() },
    ] };
    const results = await preloadGalleryImages(root);
    assert.deepEqual(results.map(result => result.status), ['rejected', 'fulfilled']);
});

test('switching views repeatedly reuses the prepared image', async () => {
    let calls = 0;
    const image = { decode: () => { calls++; return Promise.resolve(); } };
    const root = { querySelectorAll: () => [image] };
    await preloadGalleryImages(root);
    await preloadGalleryImages(root);
    assert.equal(calls, 1);
});

test('a card shows its mini loader until the preview finishes decoding', async () => {
    let finishDecode;
    const indicator = { hidden: true };
    const preview = { setAttribute(name, value) { this[name] = value; } };
    const card = { querySelector: selector => ({ '[data-card-loading]': indicator, '[data-open-media]': preview }[selector] || null) };
    const image = { closest: () => card, decode: () => new Promise(resolve => { finishDecode = resolve; }) };
    const pending = preloadGalleryImages({ querySelectorAll: () => [image] });
    assert.equal(indicator.hidden, false);
    assert.equal(preview['aria-busy'], 'true');
    finishDecode();
    await pending;
    await Promise.resolve();
    assert.equal(indicator.hidden, true);
    assert.equal(preview['aria-busy'], 'false');
});
