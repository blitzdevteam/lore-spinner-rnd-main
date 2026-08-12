import type { XenEmergeFrom } from '../data/gestures';

export interface XenSplitLayers {
    handUrl: string;
    portalUrl: string | null;
    originalUrl: string;
    /** Clip at portal center — hand exits through the middle of the hole. */
    mouth: number;
    portal: { cx: number; cy: number; rx: number; ry: number };
    width: number;
    height: number;
}

const WHITE_MIN = 245;

function isWhite(r: number, g: number, b: number, a: number): boolean {
    return a > 180 && r >= WHITE_MIN && g >= WHITE_MIN && b >= WHITE_MIN;
}

function inPortalBand(x: number, y: number, w: number, h: number, from: XenEmergeFrom): boolean {
    switch (from) {
        case 'bottom':
            return y > h * 0.74;
        case 'left':
            return x < w * 0.4;
        case 'right':
            return x > w * 0.6;
    }
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

/** Dilate a binary mask by 1px so anti-aliased portal edges get punched cleanly. */
function dilate(mask: Uint8Array, w: number, h: number): Uint8Array {
    const out = new Uint8Array(mask);
    for (let y = 1; y < h - 1; y++) {
        for (let x = 1; x < w - 1; x++) {
            const i = y * w + x;
            if (mask[i]) {
                continue;
            }
            if (
                mask[i - 1] ||
                mask[i + 1] ||
                mask[i - w] ||
                mask[i + w] ||
                mask[i - w - 1] ||
                mask[i - w + 1] ||
                mask[i + w - 1] ||
                mask[i + w + 1]
            ) {
                out[i] = 1;
            }
        }
    }
    return out;
}

/**
 * Mark every sizable white connected component in the portal band.
 * Multiple ellipses (main + reflection) are all treated as portal.
 */
function portalMask(
    data: Uint8ClampedArray,
    w: number,
    h: number,
    from: XenEmergeFrom,
): { mask: Uint8Array; minX: number; maxX: number; minY: number; maxY: number; count: number } {
    const visited = new Uint8Array(w * h);
    const mask = new Uint8Array(w * h);
    const idx = (x: number, y: number) => y * w + x;
    const minBlob = Math.floor(w * h * 0.0004); // ~0.04% of image

    let minX = w;
    let maxX = -1;
    let minY = h;
    let maxY = -1;
    let count = 0;

    // Prefer the blob whose center is closest to the emerge edge for "primary" mouth,
    // but punch ALL sizable whites in-band.
    const blobs: { cells: number[]; cx: number; cy: number; count: number }[] = [];

    for (let y = 0; y < h; y++) {
        for (let x = 0; x < w; x++) {
            const i = idx(x, y);
            if (visited[i]) {
                continue;
            }
            const p = i * 4;
            if (!inPortalBand(x, y, w, h, from) || !isWhite(data[p], data[p + 1], data[p + 2], data[p + 3])) {
                visited[i] = 1;
                continue;
            }

            const stack = [[x, y]];
            const cells: number[] = [];
            visited[i] = 1;
            let sx = 0;
            let sy = 0;

            while (stack.length) {
                const [cx, cy] = stack.pop()!;
                cells.push(idx(cx, cy));
                sx += cx;
                sy += cy;
                for (const [nx, ny] of [
                    [cx + 1, cy],
                    [cx - 1, cy],
                    [cx, cy + 1],
                    [cx, cy - 1],
                ] as const) {
                    if (nx < 0 || ny < 0 || nx >= w || ny >= h) {
                        continue;
                    }
                    const ni = idx(nx, ny);
                    if (visited[ni]) {
                        continue;
                    }
                    visited[ni] = 1;
                    const np = ni * 4;
                    if (
                        inPortalBand(nx, ny, w, h, from) &&
                        isWhite(data[np], data[np + 1], data[np + 2], data[np + 3])
                    ) {
                        stack.push([nx, ny]);
                    }
                }
            }

            if (cells.length >= minBlob) {
                blobs.push({
                    cells,
                    cx: sx / cells.length,
                    cy: sy / cells.length,
                    count: cells.length,
                });
            }
        }
    }

    // Keep blobs that look like portal ellipses (not tiny flecks).
    // For bottom: keep the topmost large blob + any below it near center.
    // Simpler: keep all blobs above minBlob in band.
    for (const blob of blobs) {
        for (const i of blob.cells) {
            mask[i] = 1;
            const x = i % w;
            const y = (i / w) | 0;
            if (x < minX) minX = x;
            if (x > maxX) maxX = x;
            if (y < minY) minY = y;
            if (y > maxY) maxY = y;
            count += 1;
        }
    }

    // Primary ellipse for mouth = largest blob (main portal, not reflection)
    blobs.sort((a, b) => b.count - a.count);
    const primary = blobs[0];

    if (primary) {
        let pMinX = w;
        let pMaxX = -1;
        let pMinY = h;
        let pMaxY = -1;
        for (const i of primary.cells) {
            const x = i % w;
            const y = (i / w) | 0;
            if (x < pMinX) pMinX = x;
            if (x > pMaxX) pMaxX = x;
            if (y < pMinY) pMinY = y;
            if (y > pMaxY) pMaxY = y;
        }
        // Use primary bounds for mouth/center (not the union with reflections)
        minX = pMinX;
        maxX = pMaxX;
        minY = pMinY;
        maxY = pMaxY;
    }

    return { mask: dilate(mask, w, h), minX, maxX, minY, maxY, count };
}

export async function splitXenLayers(src: string, from: XenEmergeFrom): Promise<XenSplitLayers> {
    const img = await loadImage(src);
    const w = img.naturalWidth;
    const h = img.naturalHeight;

    const source = document.createElement('canvas');
    source.width = w;
    source.height = h;
    const sctx = source.getContext('2d', { willReadFrequently: true });
    if (!sctx) {
        throw new Error('2d context unavailable');
    }
    sctx.drawImage(img, 0, 0);
    const { data } = sctx.getImageData(0, 0, w, h);

    const { mask, minX, maxX, minY, maxY, count } = portalMask(data, w, h, from);

    const hand = document.createElement('canvas');
    hand.width = w;
    hand.height = h;
    const hctx = hand.getContext('2d');
    if (!hctx) {
        throw new Error('2d context unavailable');
    }
    const handImg = hctx.createImageData(w, h);
    const hd = handImg.data;

    const portal = document.createElement('canvas');
    portal.width = w;
    portal.height = h;
    const pctx = portal.getContext('2d');
    if (!pctx) {
        throw new Error('2d context unavailable');
    }
    const portalImg = pctx.createImageData(w, h);
    const pd = portalImg.data;

    const edgeYs: number[] = [];
    let handMinY = h;
    let handMaxY = 0;

    for (let y = 0; y < h; y++) {
        for (let x = 0; x < w; x++) {
            const i = y * w + x;
            const p = i * 4;
            const r = data[p];
            const g = data[p + 1];
            const b = data[p + 2];
            const a = data[p + 3];

            if (mask[i]) {
                hd[p] = hd[p + 1] = hd[p + 2] = hd[p + 3] = 0;
                pd[p] = r;
                pd[p + 1] = g;
                pd[p + 2] = b;
                pd[p + 3] = a;
            } else {
                hd[p] = r;
                hd[p + 1] = g;
                hd[p + 2] = b;
                hd[p + 3] = a;
                pd[p] = pd[p + 1] = pd[p + 2] = pd[p + 3] = 0;

                if (a > 200 && b > 120 && b > r + 20 && r < 210) {
                    if (y < handMinY) handMinY = y;
                    if (y > handMaxY) handMaxY = y;
                    if (from === 'right' && x > w * 0.965) {
                        edgeYs.push(y);
                    }
                    if (from === 'left' && x < w * 0.35) {
                        // track wrist band for synthetic — unused when portal exists
                    }
                }
            }
        }
    }

    hctx.putImageData(handImg, 0, 0);

    let portalUrl: string | null = null;
    let mouth: number;
    let portalBox: XenSplitLayers['portal'];

    if (count > 600) {
        // Paint ALL masked portal pixels (including reflections) onto portal layer
        // Re-scan full mask onto portal from original — mask already has all blobs+dilate
        pctx.putImageData(portalImg, 0, 0);
        // Also copy dilated edge whites from original for smoother rim
        portalUrl = portal.toDataURL('image/png');

        portalBox = {
            cx: ((minX + maxX) / 2 / w) * 100,
            cy: ((minY + maxY) / 2 / h) * 100,
            rx: ((maxX - minX) / 2 / w) * 100,
            ry: ((maxY - minY) / 2 / h) * 100,
        };

        // Mouth = CENTER of the primary portal hole
        switch (from) {
            case 'bottom':
                mouth = portalBox.cy;
                break;
            case 'left':
            case 'right':
                mouth = portalBox.cx;
                break;
        }
    } else {
        edgeYs.sort((a, b) => a - b);
        const lo = edgeYs[Math.floor(edgeYs.length * 0.08)] ?? handMinY;
        const hi = edgeYs[Math.floor(edgeYs.length * 0.92)] ?? handMaxY;
        const cy = ((lo + hi) / 2 / h) * 100;
        const ry = Math.max(10, ((hi - lo) / 2 / h) * 100 * 1.15);
        portalBox = { cx: 98.6, cy, rx: 2.2, ry };
        mouth = portalBox.cx;
    }

    return {
        handUrl: hand.toDataURL('image/png'),
        portalUrl,
        originalUrl: src,
        mouth,
        portal: portalBox,
        width: w,
        height: h,
    };
}
