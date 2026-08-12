import type { XenGesture, XenPortal } from '../data/gestures';

function inEllipse(
    x: number,
    y: number,
    cx: number,
    cy: number,
    rx: number,
    ry: number,
): boolean {
    const nx = (x - cx) / rx;
    const ny = (y - cy) / ry;
    return nx * nx + ny * ny <= 1;
}

/** Punch whites only inside the portal ellipse(s) — never band-wipe the card. */
export async function punchPortalWhite(gesture: XenGesture): Promise<string> {
    const img = await loadImage(gesture.src);
    const w = img.naturalWidth;
    const h = img.naturalHeight;

    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    if (!ctx) {
        return gesture.src;
    }

    ctx.drawImage(img, 0, 0);
    const imageData = ctx.getImageData(0, 0, w, h);
    const d = imageData.data;

    const ellipses: Array<Pick<XenPortal, 'cx' | 'cy' | 'rx' | 'ry'>> = [
        gesture.portal,
        ...(gesture.punchExtra ?? []),
    ].map((p) => ({
        cx: (p.cx / 100) * w,
        cy: (p.cy / 100) * h,
        // Slight oversize to clear anti-aliased rim
        rx: (p.rx / 100) * w * 1.18,
        ry: (p.ry / 100) * h * 1.22,
    }));

    for (let y = 0; y < h; y++) {
        for (let x = 0; x < w; x++) {
            const i = (y * w + x) * 4;
            const r = d[i];
            const g = d[i + 1];
            const b = d[i + 2];
            const a = d[i + 3];
            if (a < 8) {
                continue;
            }
            // Strict white — leave cream cards alone
            if (r < 245 || g < 245 || b < 245) {
                continue;
            }
            for (const e of ellipses) {
                if (inEllipse(x, y, e.cx, e.cy, e.rx, e.ry)) {
                    d[i + 3] = 0;
                    break;
                }
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
