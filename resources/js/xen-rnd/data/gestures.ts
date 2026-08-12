import xenPoint from '../assets/poses/XEN.png';
import xenChefKiss from '../assets/poses/XEN-1.png';
import xenMatch from '../assets/poses/XEN-02.png';
import xenSurprise from '../assets/poses/XEN-03.png';
import xenYourMove from '../assets/poses/XEN-04.png';
import xenBold from '../assets/poses/XEN-05.png';
import xenYesMaster from '../assets/poses/XEN-06.png';

/**
 * Simple model:
 * 1) Punch baked white portal out of the PNG
 * 2) Draw our white ellipse (size-matched to the original)
 * 3) Hand sits on top and slides out of that ellipse
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
    portal: XenPortal;
    emergeDistance: number;
}

export const XEN_GESTURES: XenGesture[] = [
    {
        id: 'point',
        label: 'Point Up',
        src: xenPoint,
        aspectRatio: 1280 / 960,
        emergeFrom: 'bottom',
        portal: { cx: 50, cy: 88.5, rx: 15.6, ry: 3.4 },
        emergeDistance: 70,
    },
    {
        id: 'chef-kiss',
        label: 'Chef Kiss',
        src: xenChefKiss,
        aspectRatio: 1280 / 960,
        emergeFrom: 'left',
        portal: { cx: 20.5, cy: 51.5, rx: 8.5, ry: 28 },
        emergeDistance: 55,
    },
    {
        id: 'match',
        label: 'Lit Match',
        caption: 'Arm enters from the right — matchbox pull left.',
        src: xenMatch,
        aspectRatio: 1280 / 960,
        emergeFrom: 'right',
        portal: { cx: 98.5, cy: 62, rx: 2.2, ry: 16 },
        emergeDistance: 55,
    },
    {
        id: 'surprise',
        label: 'Surprise Me',
        caption: 'Go ahead. Surprise me.',
        src: xenSurprise,
        aspectRatio: 1280 / 961,
        emergeFrom: 'bottom',
        portal: { cx: 50, cy: 86, rx: 17, ry: 3.5 },
        emergeDistance: 65,
    },
    {
        id: 'your-move',
        label: 'Your Move',
        caption: 'Your move.',
        src: xenYourMove,
        aspectRatio: 1280 / 960,
        emergeFrom: 'bottom',
        portal: { cx: 50, cy: 86, rx: 17, ry: 3.5 },
        emergeDistance: 65,
    },
    {
        id: 'bold',
        label: 'Bold Tray',
        caption: 'Bold. Possibly Unwise.',
        src: xenBold,
        aspectRatio: 1024 / 1280,
        emergeFrom: 'bottom',
        portal: { cx: 59, cy: 87, rx: 18, ry: 3.5 },
        emergeDistance: 60,
    },
    {
        id: 'yes-master',
        label: 'Yes, Master',
        caption: 'Yes, Master.',
        src: xenYesMaster,
        aspectRatio: 1280 / 960,
        emergeFrom: 'right',
        portal: { cx: 84, cy: 51.5, rx: 6, ry: 28 },
        emergeDistance: 55,
    },
];

/** Clip so the hand only exists on the exit side of the portal center. */
export function xenClip(portal: XenPortal, from: XenEmergeFrom): string {
    switch (from) {
        case 'bottom':
            return `inset(0 0 ${(100 - portal.cy).toFixed(2)}% 0)`;
        case 'left':
            return `inset(0 0 0 ${portal.cx.toFixed(2)}%)`;
        case 'right':
            return `inset(0 ${(100 - portal.cx).toFixed(2)}% 0 0)`;
    }
}

export function xenRetract(from: XenEmergeFrom, distance: number): string {
    switch (from) {
        case 'bottom':
            return `translate3d(0, ${distance}%, 0)`;
        case 'left':
            return `translate3d(-${distance}%, 0, 0)`;
        case 'right':
            return `translate3d(${distance}%, 0, 0)`;
    }
}
