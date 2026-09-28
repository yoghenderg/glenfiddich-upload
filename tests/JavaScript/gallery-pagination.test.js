import test from 'node:test';
import assert from 'node:assert/strict';
import { pageWindow } from '../../resources/js/gallery-pagination.js';

test('tablet pages contain 16 entries without overlap or omissions', () => {
    const seen = [];
    for (let page = 1; page <= 3; page++) {
        const state = pageWindow(39, 16, page);
        assert.ok(state.end - state.start <= 16);
        seen.push(...Array.from({ length: state.end - state.start }, (_, i) => state.start + i));
    }
    assert.deepEqual(seen, Array.from({ length: 39 }, (_, i) => i));
});
test('desktop pages use larger capacities and keep the last page bounded', () => {
    assert.deepEqual(pageWindow(32, 24, 2), { current: 2, pages: 2, start: 24, end: 32 });
    assert.deepEqual(pageWindow(32, 32, 1), { current: 1, pages: 1, start: 0, end: 32 });
});
test('invalid or out-of-range page requests are clamped', () => {
    assert.equal(pageWindow(32, 16, 'bad').current, 1);
    assert.equal(pageWindow(32, 16, -5).current, 1);
    assert.equal(pageWindow(32, 16, 900).current, 2);
});
