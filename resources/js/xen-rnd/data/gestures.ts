import xenPoint from '../assets/poses/XEN.png';
import xenChefKiss from '../assets/poses/XEN-1.png';
import xenMatch from '../assets/poses/XEN-02.png';
import xenSurprise from '../assets/poses/XEN-03.png';
import xenYourMove from '../assets/poses/XEN-04.png';
import xenBold from '../assets/poses/XEN-05.png';
import xenYesMaster from '../assets/poses/XEN-06.png';

/**
 * Portal from PNG white blobs. mouth = center of the hole (exit face),
 * so the wrist seats IN the ellipse — not floating on the rim, not beside it.
 */

export type XenEmergeFrom = 'bottom' | 'left' | 'right';

export interface XenPortal {
    cx: number;
    cy: number;
    rx: number;
    ry: number;
    /** Exit face through the middle of the hole */
    mouth: number;
}

export interface XenGesture {
    id: string;
    label: string;
    caption?: string;
    src: string;
    aspectRatio: number;
    emergeFrom: XenEmergeFrom;
    portal: XenPortal;
    punchExtra?: Array<Pick<XenPortal, 'cx' | 'cy' | 'rx' | 'ry'>>;
    emergeDistance: number;
}

function withMouth(
    p: Omit<XenPortal, 'mouth'>,
    from: XenEmergeFrom,
): XenPortal {
    // Mouth through the center of the hole so the wrist is seated inside it
    const mouth = from === 'bottom' ? p.cy : p.cx;
    return { ...p, mouth };
}

export const XEN_GESTURES: XenGesture[] = [
    {
        id: 'point',
        label: 'Point Up',
        src: xenPoint,
        aspectRatio: 1280 / 960,
        emergeFrom: 'bottom',
        portal: withMouth({ cx: 49.84, cy: 88.18, rx: 15.55, ry: 3.07 }, 'bottom'),
        emergeDistance: 72,
    },
    {
        id: 'chef-kiss',
        label: 'Chef Kiss',
        src: xenChefKiss,
        aspectRatio: 1280 / 960,
        emergeFrom: 'left',
        portal: withMouth({ cx: 18.98, cy: 51.67, rx: 6.02, ry: 31.56 }, 'left'),
        emergeDistance: 58,
    },
    {
        id: 'match',
        label: 'Lit Match',
        caption: 'Arm enters from the right — matchbox pull left.',
        src: xenMatch,
        aspectRatio: 1280 / 960,
        emergeFrom: 'right',
        portal: withMouth({ cx: 98.5, cy: 73.18, rx: 2.1, ry: 10.12 }, 'right'),
        emergeDistance: 56,
    },
    {
        id: 'surprise',
        label: 'Surprise Me',
        caption: 'Go ahead. Surprise me.',
        src: xenSurprise,
        aspectRatio: 1280 / 961,
        emergeFrom: 'bottom',
        portal: withMouth({ cx: 51.02, cy: 84.65, rx: 20.5, ry: 5.1 }, 'bottom'),
        emergeDistance: 64,
    },
    {
        id: 'your-move',
        label: 'Your Move',
        caption: 'Your move.',
        src: xenYourMove,
        aspectRatio: 1280 / 960,
        emergeFrom: 'bottom',
        portal: withMouth({ cx: 50.94, cy: 84.74, rx: 20.5, ry: 5.1 }, 'bottom'),
        emergeDistance: 64,
    },
    {
        id: 'bold',
        label: 'Bold Tray',
        caption: 'Bold. Possibly Unwise.',
        src: xenBold,
        aspectRatio: 1024 / 1280,
        emergeFrom: 'bottom',
        portal: withMouth({ cx: 59.28, cy: 86.64, rx: 18.36, ry: 3.2 }, 'bottom'),
        emergeDistance: 58,
    },
    {
        id: 'yes-master',
        label: 'Yes, Master',
        caption: 'Yes, Master.',
        src: xenYesMaster,
        aspectRatio: 1280 / 960,
        emergeFrom: 'right',
        portal: withMouth({ cx: 83.32, cy: 51.3, rx: 5.59, ry: 31.3 }, 'right'),
        emergeDistance: 58,
    },
];

/** Hard slot through the portal center — printer mouth. */
export function xenClip(mouth: number, from: XenEmergeFrom): string {
    switch (from) {
        case 'bottom':
            return `inset(0 0 ${(100 - mouth).toFixed(2)}% 0)`;
        case 'left':
            return `inset(0 0 0 ${mouth.toFixed(2)}%)`;
        case 'right':
            return `inset(0 ${(100 - mouth).toFixed(2)}% 0 0)`;
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
