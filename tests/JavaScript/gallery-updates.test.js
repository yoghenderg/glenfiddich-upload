import test from 'node:test';
import assert from 'node:assert/strict';
import { reconcileGalleryCards } from '../../resources/js/gallery-updates.js';

function card(id) {
    return { dataset: { mediaCard: id }, remove() { this.parent.children.splice(this.parent.children.indexOf(this), 1); } };
}
function grid(cards) {
    const result = {
        children: cards, dataset: {}, insertions: 0,
        querySelectorAll() { return [...this.children]; },
        insertBefore(node, before) {
            this.insertions++;
            if (node.parent) node.remove();
            const index = before ? this.children.indexOf(before) : this.children.length;
            this.children.splice(index, 0, node);
            node.parent = this;
        },
    };
    cards.forEach(node => { node.parent = result; });
    return result;
}

test('an unchanged poll leaves existing cards attached and preserves their state', () => {
    const photo = card('a'); photo.downloadState = 'ready';
    const current = grid([photo]);
    reconcileGalleryCards(current, grid([card('a')]), [{ id: 'a' }], [{ id: 'a' }]);
    assert.equal(current.children[0], photo);
    assert.equal(current.children[0].downloadState, 'ready');
    assert.equal(current.insertions, 0);
});

test('inserting a new upload retains existing preview nodes and removes only departed cards', () => {
    const a = card('a'), b = card('b'), added = card('new');
    const current = grid([a, b]);
    reconcileGalleryCards(current, grid([added, card('a')]), [{ id: 'a' }, { id: 'b' }], [{ id: 'new' }, { id: 'a' }]);
    assert.deepEqual(current.children, [added, a]);
    assert.equal(current.insertions, 1);
});

test('a metadata change replaces that card while other previews remain attached', () => {
    const a = card('a'), b = card('b'), updated = card('a');
    const current = grid([a, b]);
    reconcileGalleryCards(current, grid([updated, card('b')]), [{ id: 'a', title: 'Before' }, { id: 'b' }], [{ id: 'a', title: 'After' }, { id: 'b' }]);
    assert.deepEqual(current.children, [updated, b]);
});
