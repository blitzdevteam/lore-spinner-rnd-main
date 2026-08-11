import xenPoint from '../assets/poses/XEN.png';
import xenChefKiss from '../assets/poses/XEN-1.png';
import xenMatch from '../assets/poses/XEN-02.png';
import xenSurprise from '../assets/poses/XEN-03.png';
import xenYourMove from '../assets/poses/XEN-04.png';
import xenBold from '../assets/poses/XEN-05.png';
import xenYesMaster from '../assets/poses/XEN-06.png';

export type XenEmergeFrom = 'bottom' | 'left' | 'right';

/** Ellipse in stage % — calibrated to each PNG at rest (track translate 0). */
export interface XenPortalRect {
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
    /** Fixed white portal rim on stage. */
    portal: XenPortalRect;
    /** Erases baked white portal in the art (moves with the hand track). */
    bakedCover?: XenPortalRect;
    /** Which edge the hand is pulled through. */
    emergeFrom: XenEmergeFrom;
    /** How far the track starts offset (% of stage) — fully hidden at start, rest at 0. */
    emergeDistance: number;
}

export const XEN_GESTURES: XenGesture[] = [
    {
        id: 'point',
        label: 'Point Up',
        src: xenPoint,
        aspectRatio: 1280 / 960,
        portal: { cx: 49.8, cy: 88.5, rx: 15.6, ry: 3.4 },
        bakedCover: { cx: 49.8, cy: 88.5, rx: 16.5, ry: 4.2 },
        emergeFrom: 'bottom',
        emergeDistance: 78,
    },
    {
        id: 'chef-kiss',
        label: 'Chef Kiss',
        src: xenChefKiss,
        aspectRatio: 1280 / 960,
        portal: { cx: 23.4, cy: 51.6, rx: 10.4, ry: 31.6 },
        bakedCover: { cx: 23.4, cy: 51.6, rx: 11.5, ry: 32.5 },
        emergeFrom: 'left',
        emergeDistance: 62,
    },
    {
        id: 'match',
        label: 'Lit Match',
        caption: 'Arm enters from the right — matchbox pull left.',
        src: xenMatch,
        aspectRatio: 1280 / 960,
        portal: { cx: 98.5, cy: 51, rx: 2.2, ry: 19 },
        emergeFrom: 'right',
        emergeDistance: 58,
    },
    {
        id: 'surprise',
        label: 'Surprise Me',
        caption: 'Go ahead. Surprise me.',
        src: xenSurprise,
        aspectRatio: 1280 / 961,
        portal: { cx: 50, cy: 88.2, rx: 19, ry: 3.2 },
        bakedCover: { cx: 50, cy: 89.8, rx: 21, ry: 6.5 },
        emergeFrom: 'bottom',
        emergeDistance: 78,
    },
    {
        id: 'your-move',
        label: 'Your Move',
        caption: 'Your move.',
        src: xenYourMove,
        aspectRatio: 1280 / 960,
        portal: { cx: 50, cy: 88.2, rx: 19, ry: 3.2 },
        bakedCover: { cx: 50, cy: 89.8, rx: 21, ry: 6.5 },
        emergeFrom: 'bottom',
        emergeDistance: 78,
    },
    {
        id: 'bold',
        label: 'Bold Tray',
        caption: 'Bold. Possibly Unwise.',
        src: xenBold,
        aspectRatio: 1024 / 1280,
        portal: { cx: 59.2, cy: 86.8, rx: 18.4, ry: 3.6 },
        bakedCover: { cx: 59.2, cy: 86.8, rx: 19.5, ry: 4.8 },
        emergeFrom: 'bottom',
        emergeDistance: 76,
    },
    {
        id: 'yes-master',
        label: 'Yes, Master',
        caption: 'Yes, Master.',
        src: xenYesMaster,
        aspectRatio: 1280 / 960,
        portal: { cx: 83.4, cy: 51.4, rx: 5.8, ry: 31.4 },
        bakedCover: { cx: 83.4, cy: 51.4, rx: 6.8, ry: 32.5 },
        emergeFrom: 'right',
        emergeDistance: 58,
    },
];

/** Fixed printer-slot clip — only show hand past the portal exit edge. */
export function xenEmergeClipPath(portal: XenPortalRect, from: XenEmergeFrom): string {
    const top = portal.cy - portal.ry;
    const left = portal.cx - portal.rx;

    switch (from) {
        case 'bottom':
            return `inset(0 0 ${Math.max(0, 100 - top).toFixed(3)}% 0)`;
        case 'left':
            return `inset(0 0 0 ${(portal.cx + portal.rx).toFixed(3)}%)`;
        case 'right':
            return `inset(0 ${Math.max(0, 100 - left).toFixed(3)}% 0 0)`;
    }
}

/** Track offset at animation start — hand fully retracted behind portal. */
export function xenEmergeStartTransform(from: XenEmergeFrom, distance: number): string {
    switch (from) {
        case 'bottom':
            return `translate3d(0, ${distance}%, 0)`;
        case 'left':
            return `translate3d(-${distance}%, 0, 0)`;
        case 'right':
            return `translate3d(${distance}%, 0, 0)`;
    }
}
