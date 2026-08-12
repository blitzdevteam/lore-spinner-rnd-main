import type { XenGesture, XenPortal } from '../data/gestures';

export interface PunchResult {
    url: string;
    portal: XenPortal;
}

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

/**
 * Punch baked portal whites only. Leave the hand pixels alone so the wrist
 * can sit in the hole at rest (same as the original composite).
 */
export async function punchPortalWhite(gesture: XenGesture): Promise<PunchResult> {
    const img = await loadImage(gesture.src);
    const w = img.naturalWidth;
    const h = img.naturalHeight;

    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    if (!ctx) {
        return { url: gesture.src, portal: gesture.portal };
    }

    ctx.drawImage(img, 0, 0);
    const imageData = ctx.getImageData(0, 0, w, h);
    const d = imageData.data;

    const portal = gesture.portal;
    const ellipses: Array<{ cx: number; cy: number; rx: number; ry: number }> = [
        portal,
        ...(gesture.punchExtra ?? []),
    ].map((p) => ({
        cx: (p.cx / 100) * w,
        cy: (p.cy / 100) * h,
        rx: (p.rx / 100) * w * 1.14,
        ry: (p.ry / 100) * h * 1.16,
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
            // Strict white only — cream cards stay
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
    return { url: canvas.toDataURL('image/png'), portal };
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
