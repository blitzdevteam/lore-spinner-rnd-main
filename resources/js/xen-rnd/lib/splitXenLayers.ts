import type { XenEmergeFrom } from '../data/gestures';

export interface XenSplitLayers {
    handUrl: string;
    portalUrl: string | null;
    /** Clip plane % — exit edge of the portal hole in the original art. */
    mouth: number;
    portal: { cx: number; cy: number; rx: number; ry: number };
    width: number;
    height: number;
}

const WHITE_MIN = 248;

function isWhite(r: number, g: number, b: number, a: number): boolean {
    return a > 200 && r >= WHITE_MIN && g >= WHITE_MIN && b >= WHITE_MIN;
}

function inPortalBand(x: number, y: number, w: number, h: number, from: XenEmergeFrom): boolean {
    switch (from) {
        case 'bottom':
            return y > h * 0.75;
        case 'left':
            return x < w * 0.36;
        case 'right':
            return x > w * 0.64;
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

/**
 * Keep only the largest white connected component in the portal band.
 * Avoids punching cream card whites / flecks out of the hand layer.
 */
function largestWhiteMask(
    data: Uint8ClampedArray,
    w: number,
    h: number,
    from: XenEmergeFrom,
): { mask: Uint8Array; minX: number; maxX: number; minY: number; maxY: number; count: number } {
    const visited = new Uint8Array(w * h);
    const mask = new Uint8Array(w * h);
    let best: number[] = [];
    let bestCount = 0;

    const idx = (x: number, y: number) => y * w + x;

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

            while (stack.length) {
                const [cx, cy] = stack.pop()!;
                cells.push(idx(cx, cy));
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

            if (cells.length > bestCount) {
                bestCount = cells.length;
                best = cells;
            }
        }
    }

    let minX = w;
    let maxX = -1;
    let minY = h;
    let maxY = -1;
    for (const i of best) {
        mask[i] = 1;
        const x = i % w;
        const y = (i / w) | 0;
        if (x < minX) minX = x;
        if (x > maxX) maxX = x;
        if (y < minY) minY = y;
        if (y > maxY) maxY = y;
    }

    return { mask, minX, maxX, minY, maxY, count: bestCount };
}

/**
 * Split a XEN PNG into hand + portal layers from the source pixels.
 * Rest frame (hand @ 0 + portal) == original image.
 */
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

    const { mask, minX, maxX, minY, maxY, count } = largestWhiteMask(data, w, h, from);

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

    let handMinY = h;
    let handMaxY = 0;
    const edgeYs: number[] = [];

    for (let y = 0; y < h; y++) {
        for (let x = 0; x < w; x++) {
            const i = y * w + x;
            const p = i * 4;
            const r = data[p];
            const g = data[p + 1];
            const b = data[p + 2];
            const a = data[p + 3];

            if (mask[i]) {
                hd[p] = 0;
                hd[p + 1] = 0;
                hd[p + 2] = 0;
                hd[p + 3] = 0;
                pd[p] = r;
                pd[p + 1] = g;
                pd[p + 2] = b;
                pd[p + 3] = a;
            } else {
                hd[p] = r;
                hd[p + 1] = g;
                hd[p + 2] = b;
                hd[p + 3] = a;
                pd[p] = 0;
                pd[p + 1] = 0;
                pd[p + 2] = 0;
                pd[p + 3] = 0;

                if (a > 200 && b > 120 && b > r + 20 && r < 210) {
                    if (y < handMinY) handMinY = y;
                    if (y > handMaxY) handMaxY = y;
                    if (from === 'right' && x > w * 0.97) {
                        edgeYs.push(y);
                    }
                }
            }
        }
    }

    hctx.putImageData(handImg, 0, 0);
    const handUrl = hand.toDataURL('image/png');

    let portalUrl: string | null = null;
    let mouth: number;
    let portalBox: XenSplitLayers['portal'];

    if (count > 800) {
        pctx.putImageData(portalImg, 0, 0);
        portalUrl = portal.toDataURL('image/png');
        portalBox = {
            cx: ((minX + maxX) / 2 / w) * 100,
            cy: ((minY + maxY) / 2 / h) * 100,
            rx: ((maxX - minX) / 2 / w) * 100,
            ry: ((maxY - minY) / 2 / h) * 100,
        };

        switch (from) {
            case 'bottom':
                mouth = (minY / h) * 100;
                break;
            case 'left':
                mouth = (maxX / w) * 100;
                break;
            case 'right':
                mouth = (minX / w) * 100;
                break;
        }
    } else {
        edgeYs.sort((a, b) => a - b);
        const lo = edgeYs[Math.floor(edgeYs.length * 0.1)] ?? handMinY;
        const hi = edgeYs[Math.floor(edgeYs.length * 0.9)] ?? handMaxY;
        const cy = ((lo + hi) / 2 / h) * 100;
        const ry = Math.max(8, ((hi - lo) / 2 / h) * 100);
        portalBox = { cx: 99.15, cy, rx: 1.7, ry };
        mouth = 97.8;
    }

    return {
        handUrl,
        portalUrl,
        mouth,
        portal: portalBox,
        width: w,
        height: h,
    };
}
