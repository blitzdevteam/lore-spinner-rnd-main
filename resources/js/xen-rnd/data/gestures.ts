import xenPoint from '../assets/poses/XEN.png';
import xenChefKiss from '../assets/poses/XEN-1.png';
import xenMatch from '../assets/poses/XEN-02.png';
import xenSurprise from '../assets/poses/XEN-03.png';
import xenYourMove from '../assets/poses/XEN-04.png';
import xenBold from '../assets/poses/XEN-05.png';
import xenYesMaster from '../assets/poses/XEN-06.png';

/**
 * New creamy portal — not the baked crop in the PNG.
 * Center stays synced to the wrist in the art.
 * Opening is sized so the wrist can pass through with cream shoulders.
 * mouth = center of the hole (printer slot).
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
        portal: withMouth({ cx: 49.84, cy: 88.18, rx: 20.5, ry: 8.2 }, 'bottom'),
        emergeDistance: 72,
    },
    {
        id: 'chef-kiss',
        label: 'Chef Kiss',
        src: xenChefKiss,
        aspectRatio: 1280 / 960,
        emergeFrom: 'left',
        portal: withMouth({ cx: 18.98, cy: 51.67, rx: 11.5, ry: 34.0 }, 'left'),
        emergeDistance: 58,
    },
    {
        id: 'match',
        label: 'Lit Match',
        caption: 'Arm enters from the right — matchbox pull left.',
        src: xenMatch,
        aspectRatio: 1280 / 960,
        emergeFrom: 'right',
        portal: withMouth({ cx: 97.2, cy: 74.3, rx: 6.8, ry: 26.0 }, 'right'),
        emergeDistance: 56,
    },
    {
        id: 'surprise',
        label: 'Surprise Me',
        caption: 'Go ahead. Surprise me.',
        src: xenSurprise,
        aspectRatio: 1280 / 961,
        emergeFrom: 'bottom',
        portal: withMouth({ cx: 51.02, cy: 84.65, rx: 23.5, ry: 8.6 }, 'bottom'),
        emergeDistance: 64,
    },
    {
        id: 'your-move',
        label: 'Your Move',
        caption: 'Your move.',
        src: xenYourMove,
        aspectRatio: 1280 / 960,
        emergeFrom: 'bottom',
        portal: withMouth({ cx: 50.94, cy: 84.74, rx: 23.5, ry: 8.6 }, 'bottom'),
        emergeDistance: 64,
    },
    {
        id: 'bold',
        label: 'Bold Tray',
        caption: 'Bold. Possibly Unwise.',
        src: xenBold,
        aspectRatio: 1024 / 1280,
        emergeFrom: 'bottom',
        portal: withMouth({ cx: 59.28, cy: 86.64, rx: 22.0, ry: 8.2 }, 'bottom'),
        emergeDistance: 58,
    },
    {
        id: 'yes-master',
        label: 'Yes, Master',
        caption: 'Yes, Master.',
        src: xenYesMaster,
        aspectRatio: 1280 / 960,
        emergeFrom: 'right',
        portal: withMouth({ cx: 83.32, cy: 51.3, rx: 11.2, ry: 34.0 }, 'right'),
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
