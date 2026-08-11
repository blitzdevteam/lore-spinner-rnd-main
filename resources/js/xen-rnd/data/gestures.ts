import xenPoint from '../assets/poses/XEN.png';
import xenChefKiss from '../assets/poses/XEN-1.png';
import xenMatch from '../assets/poses/XEN-02.png';
import xenSurprise from '../assets/poses/XEN-03.png';
import xenYourMove from '../assets/poses/XEN-04.png';
import xenBold from '../assets/poses/XEN-05.png';
import xenYesMaster from '../assets/poses/XEN-06.png';

export type XenEmergeFrom = 'bottom' | 'left' | 'right';

/** Ellipse in stage % — pixel-calibrated to each PNG at rest (track translate 0). */
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
    /** White portal rim — aligned to baked portal in art at rest. */
    portal: XenPortalRect;
    /** Erases baked portal pixels (moves with track). Can be larger than portal. */
    bakedCover?: XenPortalRect;
    /** Extra erasers for double-ellipse bottoms (e.g. lower reflection). */
    bakedCoverExtra?: XenPortalRect[];
    emergeFrom: XenEmergeFrom;
    /**
     * Clip plane (% of stage) — hand is visible only past this line toward the emerge direction.
     * Bottom: Y of upper portal edge. Left: X of portal right edge. Right: X of portal left edge.
     */
    emergeLine: number;
    emergeDistance: number;
}

export const XEN_GESTURES: XenGesture[] = [
    {
        id: 'point',
        label: 'Point Up',
        src: xenPoint,
        aspectRatio: 1280 / 960,
        portal: { cx: 49.84, cy: 88.54, rx: 15.63, ry: 3.44 },
        bakedCover: { cx: 49.84, cy: 88.54, rx: 16.2, ry: 3.9 },
        emergeFrom: 'bottom',
        emergeLine: 85.1,
        emergeDistance: 76,
    },
    {
        id: 'chef-kiss',
        label: 'Chef Kiss',
        src: xenChefKiss,
        aspectRatio: 1280 / 960,
        portal: { cx: 20.39, cy: 51.61, rx: 7.42, ry: 31.61 },
        bakedCover: { cx: 20.39, cy: 51.61, rx: 8.2, ry: 32.2 },
        bakedCoverExtra: [{ cx: 18.87, cy: 80.63, rx: 3.5, ry: 3.2 }],
        emergeFrom: 'left',
        emergeLine: 27.81,
        emergeDistance: 54,
    },
    {
        id: 'match',
        label: 'Lit Match',
        caption: 'Arm enters from the right — matchbox pull left.',
        src: xenMatch,
        aspectRatio: 1280 / 960,
        portal: { cx: 99.2, cy: 71.02, rx: 1.8, ry: 14.12 },
        emergeFrom: 'right',
        emergeLine: 97.4,
        emergeDistance: 48,
    },
    {
        id: 'surprise',
        label: 'Surprise Me',
        caption: 'Go ahead. Surprise me.',
        src: xenSurprise,
        aspectRatio: 1280 / 961,
        portal: { cx: 44.22, cy: 84.76, rx: 14.14, ry: 2.65 },
        bakedCover: { cx: 44.22, cy: 84.76, rx: 14.8, ry: 3.2 },
        bakedCoverExtra: [{ cx: 74.88, cy: 84.91, rx: 3, ry: 4.2 }],
        emergeFrom: 'bottom',
        emergeLine: 82.1,
        emergeDistance: 62,
    },
    {
        id: 'your-move',
        label: 'Your Move',
        caption: 'Your move.',
        src: xenYourMove,
        aspectRatio: 1280 / 960,
        portal: { cx: 44.18, cy: 84.74, rx: 14.1, ry: 2.66 },
        bakedCover: { cx: 44.18, cy: 84.74, rx: 14.8, ry: 3.2 },
        bakedCoverExtra: [{ cx: 74.73, cy: 84.84, rx: 3, ry: 4.2 }],
        emergeFrom: 'bottom',
        emergeLine: 82.08,
        emergeDistance: 62,
    },
    {
        id: 'bold',
        label: 'Bold Tray',
        caption: 'Bold. Possibly Unwise.',
        src: xenBold,
        aspectRatio: 1024 / 1280,
        portal: { cx: 59.23, cy: 86.84, rx: 18.41, ry: 3.55 },
        bakedCover: { cx: 59.23, cy: 86.84, rx: 19.2, ry: 4.2 },
        emergeFrom: 'bottom',
        emergeLine: 83.28,
        emergeDistance: 58,
    },
    {
        id: 'yes-master',
        label: 'Yes, Master',
        caption: 'Yes, Master.',
        src: xenYesMaster,
        aspectRatio: 1280 / 960,
        portal: { cx: 83.44, cy: 51.35, rx: 5.78, ry: 31.35 },
        bakedCover: { cx: 83.44, cy: 51.35, rx: 6.8, ry: 32.2 },
        bakedCoverExtra: [{ cx: 83.01, cy: 80.36, rx: 3.5, ry: 3.2 }],
        emergeFrom: 'right',
        emergeLine: 77.66,
        emergeDistance: 52,
    },
];

export function xenEmergeClipPath(emergeLine: number, from: XenEmergeFrom): string {
    switch (from) {
        case 'bottom':
            return `inset(0 0 ${Math.max(0, 100 - emergeLine).toFixed(3)}% 0)`;
        case 'left':
            return `inset(0 0 0 ${emergeLine.toFixed(3)}%)`;
        case 'right':
            return `inset(0 ${Math.max(0, 100 - emergeLine).toFixed(3)}% 0 0)`;
    }
}

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
