import type { XenEmergeFrom, XenGesture, XenPortal } from '../data/gestures';

export interface PunchResult {
    url: string;
    portal: XenPortal;
}

/** How far a gray pixel may sit from cyan skin and still count as hand shading. */
const SKIN_HALO_PASSES = 3;
/** How deep the punch bleeds along the baked portal's feathered edge. */
const RIM_BLEED_PASSES = 6;
/** Below this chroma a pixel is portal white or its anti-aliased ramp. */
const MAX_GRAY_CHROMA = 60;
/** Seed the punch only on the portal's solid body, not on its dark outer ramp. */
const PORTAL_BODY_LUMA = 185;

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

function dilate(
    mask: Uint8Array,
    w: number,
    h: number,
    passes: number,
    allowed: (p: number, x: number, y: number) => boolean,
): Uint8Array {
    let current = mask;
    for (let pass = 0; pass < passes; pass++) {
        const grown = new Uint8Array(current);
        for (let y = 1; y < h - 1; y++) {
            const row = y * w;
            for (let x = 1; x < w - 1; x++) {
                const p = row + x;
                if (current[p] || !allowed(p, x, y)) {
                    continue;
                }
                if (
                    current[p - 1] ||
                    current[p + 1] ||
                    current[p - w] ||
                    current[p + w]
                ) {
                    grown[p] = 1;
                }
            }
        }
        current = grown;
    }
    return current;
}

/**
 * Remove the baked portal and its full anti-aliased crop rim.
 *
 * The rim is a gray ramp running from near-black to white, so brightness alone
 * can't separate it from the hand's dark engraving lines. Two guards do it:
 * the punch is only seeded on the portal's solid white body, and it only
 * spreads into gray pixels that aren't within a few px of cyan skin.
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

    const ellipses = [portal, ...(gesture.punchExtra ?? [])].map((p) => ({
        cx: (p.cx / 100) * w,
        cy: (p.cy / 100) * h,
        rx: (p.rx / 100) * w * 1.95,
        ry: (p.ry / 100) * h * 2.0,
    }));

    const total = w * h;
    const skin = new Uint8Array(total);
    const warm = new Uint8Array(total);
    const gray = new Uint8Array(total);

    for (let p = 0; p < total; p++) {
        const i = p * 4;
        const r = d[i];
        const g = d[i + 1];
        const b = d[i + 2];
        if (d[i + 3] < 40) {
            continue;
        }
        // Cyan skin: blue-dominant surface, not an outline or a highlight
        if (b > 130 && b - r > 30 && r < 205) {
            skin[p] = 1;
        }
        // Cream card, gold filigree, match flame
        if (r > 150 && r - b > 22) {
            warm[p] = 1;
        }
        if (Math.max(r, g, b) - Math.min(r, g, b) <= MAX_GRAY_CHROMA) {
            gray[p] = 1;
        }
    }

    const halo = dilate(skin, w, h, SKIN_HALO_PASSES, () => true);

    const inPunchZone = (x: number, y: number) =>
        ellipses.some((e) => inEllipse(x, y, e.cx, e.cy, e.rx, e.ry));

    let punch: Uint8Array = new Uint8Array(total);

    for (let y = 0; y < h; y++) {
        const row = y * w;
        for (let x = 0; x < w; x++) {
            const p = row + x;
            const i = p * 4;
            if (d[i + 3] < 8 || warm[p] || !inPunchZone(x, y)) {
                continue;
            }

            // Inside the hole nothing from the source art survives
            if (isPastMouth(x, y, mouthPx, from)) {
                punch[p] = 1;
                continue;
            }

            if (skin[p] || halo[p] || !gray[p]) {
                continue;
            }
            const luma = 0.3 * d[i] + 0.59 * d[i + 1] + 0.11 * d[i + 2];
            if (luma >= PORTAL_BODY_LUMA) {
                punch[p] = 1;
            }
        }
    }

    punch = dilate(
        punch,
        w,
        h,
        RIM_BLEED_PASSES,
        (p, x, y) =>
            gray[p] === 1 &&
            !skin[p] &&
            !halo[p] &&
            !warm[p] &&
            d[p * 4 + 3] >= 8 &&
            inPunchZone(x, y),
    );

    for (let p = 0; p < total; p++) {
        if (punch[p]) {
            d[p * 4 + 3] = 0;
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
