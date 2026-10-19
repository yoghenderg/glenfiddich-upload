let printing = false;

const templateUrl = '/media/Pocket%20Size_no%20line.png';
// Pixel bounds of the transparent opening in the 1800 × 1200 template.
const photoBox = { x: 62, y: 64, width: 773, height: 891 };

async function loadImage(src) {
    const image = new Image();
    image.src = src;
    await image.decode();
    return image;
}

export async function composePrintPhoto(src) {
    const [photo, template] = await Promise.all([loadImage(src), loadImage(templateUrl)]);
    const canvas = document.createElement('canvas');
    canvas.width = template.naturalWidth;
    canvas.height = template.naturalHeight;
    const context = canvas.getContext('2d');
    if (!context) throw new Error('Image composition is unavailable');

    // Center-crop without stretching, keeping the photo within the opening.
    const scale = Math.max(photoBox.width / photo.naturalWidth, photoBox.height / photo.naturalHeight);
    const cropWidth = photoBox.width / scale;
    const cropHeight = photoBox.height / scale;
    context.drawImage(photo,
        (photo.naturalWidth - cropWidth) / 2, (photo.naturalHeight - cropHeight) / 2,
        cropWidth, cropHeight,
        photoBox.x, photoBox.y, photoBox.width, photoBox.height);
    context.drawImage(template, 0, 0);
    return canvas.toDataURL('image/png');
}

// A separate document prevents modal chrome and scrolling from entering the printout.
export async function printPhoto(item, feedback) {
    if (printing) return;
    printing = true;
    feedback.textContent = 'Preparing photo…';
    const frame = document.createElement('iframe');
    frame.title = '4R photo print';
    frame.setAttribute('aria-hidden', 'true');
    frame.style.cssText = 'position:fixed;left:-10000px;top:0;width:576px;height:384px;border:0';
    document.body.append(frame);
    const cleanup = () => { frame.remove(); printing = false; };
    try {
        const doc = frame.contentDocument;
        const style = doc.createElement('style');
        style.textContent = `
            @page { size: 6in 4in; margin: 0; }
            * { box-sizing: border-box; }
            html, body { margin: 0; padding: 0; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            img { position: fixed; inset: 0; margin: auto; display: block; width: 6in; height: 4in; max-width: 100%; max-height: 100%; object-fit: contain; object-position: center; }
        `;
        doc.head.append(style);
        doc.title = '4R Photo';
        const photo = doc.createElement('img');
        photo.alt = 'Event photo inside the pocket-size frame';
        const loaded = new Promise((resolve, reject) => {
            photo.onload = resolve;
            photo.onerror = () => reject(new Error('Photo could not load'));
        });
        photo.src = await composePrintPhoto(item.src);
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
