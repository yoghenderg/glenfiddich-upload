// Reuse unchanged cards so decoded previews, focus, and action state survive polls.
export function reconcileGalleryCards(current, incoming, previousItems, nextItems) {
    const oldData = new Map(previousItems.map(item => [item.id, JSON.stringify(item)]));
    const newData = new Map(nextItems.map(item => [item.id, JSON.stringify(item)]));
    const existing = new Map([...current.querySelectorAll('[data-media-card]')].map(card => [card.dataset.mediaCard, card]));
    const cards = [...incoming.querySelectorAll('[data-media-card]')].map(card => {
        const id = card.dataset.mediaCard;
        return existing.has(id) && oldData.get(id) === newData.get(id) ? existing.get(id) : card;
    });
    // Insert only moved/new nodes; leave retained cards attached to avoid replaying animations.
    cards.forEach((card, index) => {
        if (current.children[index] !== card) current.insertBefore(card, current.children[index] || null);
    });
    const retained = new Set(cards);
    [...current.children].forEach(card => { if (!retained.has(card)) card.remove(); });
    current.dataset.count = cards.length;
}
