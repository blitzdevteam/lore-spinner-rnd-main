import type { XenEmergeFrom, XenGesture, XenPortal } from '../data/gestures';

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

function isPastMouth(
    x: number,
    y: number,
    mouthPx: number,
    from: XenEmergeFrom,
): boolean {
    switch (from) {
        case 'bottom':
            return y > mouthPx;
        case 'left':
            return x < mouthPx;
        case 'right':
            return x > mouthPx;
    }
}

/**
 * Strip the baked / cropped portal from the PNG.
 * Punch near-white in a generous hole, plus leftover wrist pixels
 * past the mouth so the new creamy disc is the only opening.
 * Cream cards stay (not strict white).
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
    const from = gesture.emergeFrom;
    const mouthPx =
        from === 'bottom' ? (portal.mouth / 100) * h : (portal.mouth / 100) * w;

    const ellipses: Array<{ cx: number; cy: number; rx: number; ry: number }> = [
        portal,
        ...(gesture.punchExtra ?? []),
    ].map((p) => ({
        cx: (p.cx / 100) * w,
        cy: (p.cy / 100) * h,
        rx: (p.rx / 100) * w * 1.45,
        ry: (p.ry / 100) * h * 1.5,
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

            const inside = ellipses.some((e) => inEllipse(x, y, e.cx, e.cy, e.rx, e.ry));
            if (!inside) {
                continue;
            }

            const isWhite = r >= 242 && g >= 242 && b >= 242;
            const pastMouth = isPastMouth(x, y, mouthPx, from);
            const isNearBlack = r < 28 && g < 28 && b < 28;

            if (isWhite || (pastMouth && !isNearBlack)) {
                d[i + 3] = 0;
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
