import xenPoint from '../assets/poses/XEN.png';
import xenChefKiss from '../assets/poses/XEN-1.png';
import xenMatch from '../assets/poses/XEN-02.png';
import xenSurprise from '../assets/poses/XEN-03.png';
import xenYourMove from '../assets/poses/XEN-04.png';
import xenBold from '../assets/poses/XEN-05.png';
import xenYesMaster from '../assets/poses/XEN-06.png';

/**
 * Printer-hole model
 *
 * 1. Cover (clip out) the baked white portal in the PNG.
 * 2. Draw our own fixed white portal = the hole.
 * 3. Clip so only the “outside” of that hole is visible.
 * 4. Slide the image so the hand feeds through the hole.
 * 5. Rest = translate(0): wrist sits in the center of the portal,
 *    same composition as the original art.
 *
 * All coords are % of the image. Stage aspect matches the PNG;
 * object-fit: contain — never stretch.
 */

export type XenEmergeFrom = 'bottom' | 'left' | 'right';

export interface XenPortal {
    cx: number;
    cy: number;
    rx: number;
    ry: number;
}

export interface XenGesture {
    id: string;
    label: string;
    caption?: string;
    src: string;
    aspectRatio: number;
    emergeFrom: XenEmergeFrom;
    /** Our hole — also used to cover the baked portal in the PNG. */
    portal: XenPortal;
    /** Extra baked whites to cover (lower reflection, etc.). */
    coverExtra?: XenPortal[];
    /** How far the image starts behind the hole (% of stage). */
    emergeDistance: number;
}

export const XEN_GESTURES: XenGesture[] = [
    {
        id: 'point',
        label: 'Point Up',
        src: xenPoint,
        aspectRatio: 1280 / 960,
        emergeFrom: 'bottom',
        portal: { cx: 49.84, cy: 88.54, rx: 15.63, ry: 3.44 },
        emergeDistance: 72,
    },
    {
        id: 'chef-kiss',
        label: 'Chef Kiss',
        src: xenChefKiss,
        aspectRatio: 1280 / 960,
        emergeFrom: 'left',
        portal: { cx: 22.07, cy: 51.61, rx: 9.1, ry: 31.61 },
        emergeDistance: 58,
    },
    {
        id: 'match',
        label: 'Lit Match',
        caption: 'Arm enters from the right — matchbox pull left.',
        src: xenMatch,
        aspectRatio: 1280 / 960,
        emergeFrom: 'right',
        // No baked portal — synthetic hole at the wrist edge, vertically centered on the arm
        portal: { cx: 99.2, cy: 71.2, rx: 1.8, ry: 14.0 },
        emergeDistance: 52,
    },
    {
        id: 'surprise',
        label: 'Surprise Me',
        caption: 'Go ahead. Surprise me.',
        src: xenSurprise,
        aspectRatio: 1280 / 961,
        emergeFrom: 'bottom',
        portal: { cx: 44.73, cy: 82.62, rx: 14.73, ry: 4.68 },
        coverExtra: [{ cx: 50, cy: 90.5, rx: 18, ry: 3.5 }],
        emergeDistance: 68,
    },
    {
        id: 'your-move',
        label: 'Your Move',
        caption: 'Your move.',
        src: xenYourMove,
        aspectRatio: 1280 / 960,
        emergeFrom: 'bottom',
        portal: { cx: 44.69, cy: 82.66, rx: 14.69, ry: 4.74 },
        coverExtra: [{ cx: 50, cy: 90.5, rx: 18, ry: 3.5 }],
        emergeDistance: 68,
    },
    {
        id: 'bold',
        label: 'Bold Tray',
        caption: 'Bold. Possibly Unwise.',
        src: xenBold,
        aspectRatio: 1024 / 1280,
        emergeFrom: 'bottom',
        portal: { cx: 59.23, cy: 86.84, rx: 18.41, ry: 3.55 },
        emergeDistance: 64,
    },
    {
        id: 'yes-master',
        label: 'Yes, Master',
        caption: 'Yes, Master.',
        src: xenYesMaster,
        aspectRatio: 1280 / 960,
        emergeFrom: 'right',
        portal: { cx: 83.44, cy: 51.35, rx: 5.78, ry: 31.35 },
        emergeDistance: 56,
    },
];

/** Hole mouth = portal center along the emerge axis. Hand rests in the middle of the hole. */
export function xenMouth(portal: XenPortal, from: XenEmergeFrom): number {
    return from === 'bottom' ? portal.cy : portal.cx;
}

/** Visible = outside the hole (the direction the hand exits). */
export function xenHoleClip(portal: XenPortal, from: XenEmergeFrom): string {
    const mouth = xenMouth(portal, from);
    switch (from) {
        case 'bottom':
            return `inset(0 0 ${(100 - mouth).toFixed(3)}% 0)`;
        case 'left':
            return `inset(0 0 0 ${mouth.toFixed(3)}%)`;
        case 'right':
            return `inset(0 ${(100 - mouth).toFixed(3)}% 0 0)`;
    }
}

export function xenRetractTransform(from: XenEmergeFrom, distance: number): string {
    switch (from) {
        case 'bottom':
            return `translate3d(0, ${distance}%, 0)`;
        case 'left':
            return `translate3d(-${distance}%, 0, 0)`;
        case 'right':
            return `translate3d(${distance}%, 0, 0)`;
    }
}
