import type { XenEmergeFrom, XenPortal } from '../data/gestures';

/** Punch near-white portal pixels to transparent. Simple threshold, no blob logic. */
export async function punchPortalWhite(
    src: string,
    from: XenEmergeFrom,
    portal: XenPortal,
): Promise<string> {
    const img = await loadImage(src);
    const w = img.naturalWidth;
    const h = img.naturalHeight;

    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    if (!ctx) {
        return src;
    }

    ctx.drawImage(img, 0, 0);
    const imageData = ctx.getImageData(0, 0, w, h);
    const d = imageData.data;

    // Punch a generous ellipse around the known portal (+ margin for glow / double rings)
    const cx = (portal.cx / 100) * w;
    const cy = (portal.cy / 100) * h;
    const rx = (portal.rx / 100) * w * 1.35;
    const ry = (portal.ry / 100) * h * 1.45;

    for (let y = 0; y < h; y++) {
        for (let x = 0; x < w; x++) {
            const i = (y * w + x) * 4;
            const r = d[i];
            const g = d[i + 1];
            const b = d[i + 2];
            const a = d[i + 3];
            if (a < 10) {
                continue;
            }

            // Near-white?
            if (r < 230 || g < 230 || b < 230) {
                continue;
            }

            // Inside / near portal ellipse?
            const nx = (x - cx) / rx;
            const ny = (y - cy) / ry;
            if (nx * nx + ny * ny <= 1) {
                d[i + 3] = 0;
                continue;
            }

            // Also clear whites in the emerge-side band (lower ring, etc.)
            if (from === 'bottom' && y > h * 0.78 && r >= 240 && g >= 240 && b >= 240) {
                d[i + 3] = 0;
            }
            if (from === 'left' && x < w * 0.38 && r >= 240 && g >= 240 && b >= 240) {
                d[i + 3] = 0;
            }
            if (from === 'right' && x > w * 0.72 && r >= 240 && g >= 240 && b >= 240) {
                d[i + 3] = 0;
            }
        }
    }

    ctx.putImageData(imageData, 0, 0);
    return canvas.toDataURL('image/png');
}

function loadImage(src: string): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.decoding = 'async';
        img.onload = () => resolve(img);
        img.onerror = () => reject(new Error(`Failed to load ${src}`));
        img.src = src;
    });
}
