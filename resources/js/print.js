let printing = false;

// A separate document prevents modal chrome and scrolling from entering the printout.
export async function printPhoto(item, feedback) {
    if (printing) return;
    printing = true;
    feedback.textContent = 'Preparing photo…';
    const frame = document.createElement('iframe');
    frame.title = '4R photo print';
    frame.setAttribute('aria-hidden', 'true');
    frame.style.cssText = 'position:fixed;left:-10000px;top:0;width:384px;height:576px;border:0';
    document.body.append(frame);
    const cleanup = () => { frame.remove(); printing = false; };
    try {
        const doc = frame.contentDocument;
        const style = doc.createElement('style');
        style.textContent = `
            @page { size: 4in 6in; margin: 0; }
            * { box-sizing: border-box; }
            html, body { margin: 0; padding: 0; background: #033037; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            img { position: fixed; inset: 0; margin: auto; display: block; width: 4in; height: 6in; max-width: 100%; max-height: 100%; object-fit: contain; object-position: center; }
        `;
        doc.head.append(style);
        doc.title = '4R Photo';
        const photo = doc.createElement('img');
        photo.alt = 'Complete event photo';
        const loaded = new Promise((resolve, reject) => {
            photo.onload = resolve;
            photo.onerror = () => reject(new Error('Photo could not load'));
        });
        photo.src = item.src;
        doc.body.append(photo);
        await loaded;
        await photo.decode();
        frame.contentWindow.addEventListener('afterprint', cleanup, { once: true });
        feedback.textContent = '';
        frame.contentWindow.focus();
        frame.contentWindow.print();
        // Some browsers omit afterprint; retain the document while their dialog is open.
        window.setTimeout(cleanup, 300000);
    } catch {
        cleanup();
        feedback.textContent = 'Unable to prepare the photo. Please try printing again.';
    }
}
