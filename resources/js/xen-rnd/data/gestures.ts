import xenPoint from '../assets/poses/XEN.png';
import xenChefKiss from '../assets/poses/XEN-1.png';
import xenMatch from '../assets/poses/XEN-02.png';
import xenSurprise from '../assets/poses/XEN-03.png';
import xenYourMove from '../assets/poses/XEN-04.png';
import xenBold from '../assets/poses/XEN-05.png';
import xenYesMaster from '../assets/poses/XEN-06.png';

/**
 * XEN emerge — cream portal that tracks the silhouette coming through it.
 *
 * The pose assets are pre-keyed (transparent background, no baked portal).
 * Each gesture carries a `curve`: the portal half-opening as the hand feeds,
 * sampled at 32 steps of emergence. The portal blooms to `rest` (wrist size),
 * then follows the curve (growing for a wide payload, shrinking back to the
 * wrist) so the hole is always just big enough for what is crossing the mouth.
 */

export type XenEmergeFrom = 'bottom' | 'left' | 'right';

export interface XenPortal {
    cx: number;
    cy: number;
    /** Rest half-opening at the wrist, in % of the frame's long axis. */
    rest: number;
    /** Portal half-opening at 32 steps of emergence (0 = retracted, 31 = settled). */
    curve: number[];
}

export interface XenGesture {
    id: string;
    label: string;
    caption?: string;
    src: string;
    aspectRatio: number;
    emergeFrom: XenEmergeFrom;
    /** Mouth line (% of frame) — the portal sits here, hand clips below it. */
    mouth: number;
    portal: XenPortal;
    /** How far the hand retracts (% of frame) before feeding through. */
    emergeDistance: number;
}

export const XEN_GESTURES: XenGesture[] = [
    {
        id: 'point',
        label: 'Point Up',
        src: xenPoint,
        aspectRatio: 1.21,
        emergeFrom: 'bottom',
        mouth: 82,
        portal: {
            cx: 49.6,
            cy: 82,
            rest: 8,
            curve: [8, 8, 8, 8, 8, 8, 8, 8, 8, 8, 8.3, 8.5, 9.6, 22.5, 23.7, 22, 20.4, 19.3, 18.1, 17.3, 16.7, 16.1, 15.7, 15.2, 14.5, 13.7, 12.8, 11.9, 10.8, 9.6, 8.6, 8.3],
        },
        emergeDistance: 82,
    },
    {
        id: 'chef-kiss',
        label: 'Chef Kiss',
        src: xenChefKiss,
        aspectRatio: 1.355,
        emergeFrom: 'left',
        mouth: 18,
        portal: {
            cx: 18,
            cy: 67.3,
            rest: 14.7,
            curve: [14.7, 14.7, 14.7, 14.7, 14.7, 14.7, 14.7, 14.7, 14.7, 14.7, 14.7, 14.7, 14.7, 14.7, 14.7, 14.7, 14.7, 14.7, 14.7, 14.7, 14.7, 14.7, 14.7, 14.7, 14.7, 14.7, 14.7, 14.7, 14.7, 14.7, 14.7, 14.7],
        },
        emergeDistance: 70,
    },
    {
        id: 'match',
        label: 'Lit Match',
        src: xenMatch,
        aspectRatio: 1.627,
        emergeFrom: 'right',
        mouth: 81.9,
        portal: {
            cx: 81.9,
            cy: 75.3,
            rest: 13.2,
            curve: [13.2, 16.7, 28.5, 67.1, 58.7, 48.6, 47.4, 46.7, 45.6, 44.8, 44.3, 44.2, 43.9, 42.9, 42.1, 41.2, 40.3, 39.4, 38.8, 37.2, 35, 33.3, 30.8, 28.6, 26.4, 21.9, 17.8, 16.2, 15.2, 14.6, 13.9, 13.5],
        },
        emergeDistance: 72,
    },
    {
        id: 'surprise',
        label: 'Surprise Me',
        caption: 'Go ahead. Surprise me.',
        src: xenSurprise,
        aspectRatio: 1.191,
        emergeFrom: 'bottom',
        mouth: 82,
        portal: {
            cx: 66.1,
            cy: 82,
            rest: 8.6,
            curve: [8.6, 51, 51.3, 51.2, 51.2, 51.3, 51.1, 51.3, 51.1, 51.1, 51.3, 51.1, 51.1, 51.2, 47.2, 25.6, 26.5, 26.6, 23.3, 23, 23.3, 22.4, 19.3, 10.3, 9.8, 9.2, 9.3, 9.6, 9.6, 9.7, 9.4, 9],
        },
        emergeDistance: 80,
    },
    {
        id: 'your-move',
        label: 'Your Move',
        caption: 'Your move.',
        src: xenYourMove,
        aspectRatio: 1.627,
        emergeFrom: 'right',
        mouth: 81.9,
        portal: {
            cx: 81.9,
            cy: 75.7,
            rest: 13,
            curve: [13, 35.1, 35.9, 34, 55.5, 57.7, 55.2, 51.2, 47.2, 57.1, 67.6, 65.2, 59.4, 53.5, 47.3, 40.3, 61.6, 68.5, 64.5, 54.9, 39.7, 32, 28, 25.7, 30.3, 38.5, 43.2, 45.2, 46.2, 44.9, 13, 13],
        },
        emergeDistance: 76,
    },
    {
        id: 'bold',
        label: 'Bold Tray',
        caption: 'Bold. Possibly Unwise.',
        src: xenBold,
        aspectRatio: 0.728,
        emergeFrom: 'bottom',
        mouth: 81.9,
        portal: {
            cx: 59.6,
            cy: 81.9,
            rest: 8.4,
            curve: [8.4, 32.3, 35.1, 35.1, 35.1, 35.1, 35.1, 35.1, 35.1, 35.1, 35.1, 35.1, 44.5, 54.5, 45.5, 34, 30.1, 28, 26.4, 25, 23.6, 21.1, 18, 15.5, 13.6, 11.4, 9.9, 10, 9.9, 9.4, 9.3, 8.9],
        },
        emergeDistance: 80,
    },
    {
        id: 'yes-master',
        label: 'Yes, Master',
        caption: 'Yes, Master.',
        src: xenYesMaster,
        aspectRatio: 1.627,
        emergeFrom: 'right',
        mouth: 81.9,
        portal: {
            cx: 81.9,
            cy: 56,
            rest: 15.4,
            curve: [15.4, 17.6, 17.6, 17.6, 17.6, 17.6, 17.6, 17.6, 17.6, 17.5, 17.5, 17.7, 18.9, 20, 21.3, 22.8, 24.2, 25.3, 25.7, 26.2, 26.4, 26.8, 27.3, 28.1, 28.6, 27.7, 26, 23.9, 22.2, 20.5, 18.9, 17.3],
        },
        emergeDistance: 84,
    },
];

/** Wavy clip path is generated in the component — see XenPortalHand.vue. */

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
