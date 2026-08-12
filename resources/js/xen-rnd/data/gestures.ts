import xenPoint from '../assets/poses/XEN.png';
import xenChefKiss from '../assets/poses/XEN-1.png';
import xenMatch from '../assets/poses/XEN-02.png';
import xenSurprise from '../assets/poses/XEN-03.png';
import xenYourMove from '../assets/poses/XEN-04.png';
import xenBold from '../assets/poses/XEN-05.png';
import xenYesMaster from '../assets/poses/XEN-06.png';

/**
 * Hand over a matched white ellipse.
 * mouth = exit face of the hole (where the wrist sits in the original art).
 */

export type XenEmergeFrom = 'bottom' | 'left' | 'right';

export interface XenPortal {
    cx: number;
    cy: number;
    rx: number;
    ry: number;
    /** Exit face % — bottom: top of ellipse; left: right edge; right: left edge */
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
    /** Extra whites to punch (lower reflection rings, etc.) */
    punchExtra?: Array<Pick<XenPortal, 'cx' | 'cy' | 'rx' | 'ry'>>;
    emergeDistance: number;
}

export const XEN_GESTURES: XenGesture[] = [
    {
        id: 'point',
        label: 'Point Up',
        src: xenPoint,
        aspectRatio: 1280 / 960,
        emergeFrom: 'bottom',
        portal: { cx: 49.84, cy: 88.54, rx: 15.63, ry: 3.44, mouth: 85.1 },
        emergeDistance: 68,
    },
    {
        id: 'chef-kiss',
        label: 'Chef Kiss',
        src: xenChefKiss,
        aspectRatio: 1280 / 960,
        emergeFrom: 'left',
        portal: { cx: 20.4, cy: 51.6, rx: 7.8, ry: 30.5, mouth: 28.2 },
        emergeDistance: 52,
    },
    {
        id: 'match',
        label: 'Lit Match',
        caption: 'Arm enters from the right — matchbox pull left.',
        src: xenMatch,
        aspectRatio: 1280 / 960,
        emergeFrom: 'right',
        portal: { cx: 98.4, cy: 71.0, rx: 2.0, ry: 15.5, mouth: 96.4 },
        emergeDistance: 52,
    },
    {
        id: 'surprise',
        label: 'Surprise Me',
        caption: 'Go ahead. Surprise me.',
        src: xenSurprise,
        aspectRatio: 1280 / 961,
        emergeFrom: 'bottom',
        portal: { cx: 47.0, cy: 84.8, rx: 16.5, ry: 3.6, mouth: 81.2 },
        punchExtra: [{ cx: 50, cy: 90.2, rx: 18, ry: 3.2 }],
        emergeDistance: 62,
    },
    {
        id: 'your-move',
        label: 'Your Move',
        caption: 'Your move.',
        src: xenYourMove,
        aspectRatio: 1280 / 960,
        emergeFrom: 'bottom',
        portal: { cx: 47.0, cy: 84.8, rx: 16.5, ry: 3.6, mouth: 81.2 },
        punchExtra: [{ cx: 50, cy: 90.2, rx: 18, ry: 3.2 }],
        emergeDistance: 62,
    },
    {
        id: 'bold',
        label: 'Bold Tray',
        caption: 'Bold. Possibly Unwise.',
        src: xenBold,
        aspectRatio: 1024 / 1280,
        emergeFrom: 'bottom',
        portal: { cx: 59.23, cy: 86.84, rx: 18.41, ry: 3.55, mouth: 83.28 },
        emergeDistance: 58,
    },
    {
        id: 'yes-master',
        label: 'Yes, Master',
        caption: 'Yes, Master.',
        src: xenYesMaster,
        aspectRatio: 1280 / 960,
        emergeFrom: 'right',
        portal: { cx: 83.44, cy: 51.35, rx: 5.9, ry: 30.5, mouth: 77.5 },
        emergeDistance: 52,
    },
];

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
