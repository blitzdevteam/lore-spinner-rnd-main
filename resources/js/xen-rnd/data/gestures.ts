import xenPoint from '../assets/poses/XEN.png';
import xenChefKiss from '../assets/poses/XEN-1.png';
import xenMatch from '../assets/poses/XEN-02.png';
import xenSurprise from '../assets/poses/XEN-03.png';
import xenYourMove from '../assets/poses/XEN-04.png';
import xenBold from '../assets/poses/XEN-05.png';
import xenYesMaster from '../assets/poses/XEN-06.png';

/**
 * XEN portal emerge — simple printer model
 *
 * Finish frame = original PNG, unstretched.
 * During motion: a fixed "hole" (clip line) sits at the portal mouth.
 * The image slides so the hand feeds through that hole — fingertips first,
 * wrist last — then settles exactly on the source art.
 *
 * Portal coords are % of the image (same aspect stage, object-fit contain).
 */

export type XenEmergeFrom = 'bottom' | 'left' | 'right';

export interface XenPortal {
    /** Center of the white ellipse in the original art */
    cx: number;
    cy: number;
    rx: number;
    ry: number;
    /**
     * Mouth of the hole — clip plane where the hand exits.
     * bottom → Y% (top edge of ellipse)
     * left   → X% (right edge of ellipse)
     * right  → X% (left edge of ellipse)
     */
    mouth: number;
}

export interface XenGesture {
    id: string;
    label: string;
    caption?: string;
    src: string;
    aspectRatio: number;
    emergeFrom: XenEmergeFrom;
    /** null for match (no baked portal — synthetic rim at mouth only while animating) */
    portal: XenPortal | null;
    /** How far to retract the image at start (% of stage) */
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
        emergeDistance: 70,
    },
    {
        id: 'chef-kiss',
        label: 'Chef Kiss',
        src: xenChefKiss,
        aspectRatio: 1280 / 960,
        emergeFrom: 'left',
        portal: { cx: 21.29, cy: 51.61, rx: 8.32, ry: 31.61, mouth: 29.61 },
        emergeDistance: 55,
    },
    {
        id: 'match',
        label: 'Lit Match',
        caption: 'Arm enters from the right — matchbox pull left.',
        src: xenMatch,
        aspectRatio: 1280 / 960,
        emergeFrom: 'right',
        portal: { cx: 99.0, cy: 71.0, rx: 1.6, ry: 16.0, mouth: 97.5 },
        emergeDistance: 50,
    },
    {
        id: 'surprise',
        label: 'Surprise Me',
        caption: 'Go ahead. Surprise me.',
        src: xenSurprise,
        aspectRatio: 1280 / 961,
        emergeFrom: 'bottom',
        portal: { cx: 45.51, cy: 83.98, rx: 13.48, ry: 3.95, mouth: 80.02 },
        emergeDistance: 65,
    },
    {
        id: 'your-move',
        label: 'Your Move',
        caption: 'Your move.',
        src: xenYourMove,
        aspectRatio: 1280 / 960,
        emergeFrom: 'bottom',
        portal: { cx: 45.43, cy: 84.01, rx: 13.4, ry: 3.91, mouth: 80.1 },
        emergeDistance: 65,
    },
    {
        id: 'bold',
        label: 'Bold Tray',
        caption: 'Bold. Possibly Unwise.',
        src: xenBold,
        aspectRatio: 1024 / 1280,
        emergeFrom: 'bottom',
        portal: { cx: 59.23, cy: 86.84, rx: 18.41, ry: 3.55, mouth: 83.28 },
        emergeDistance: 60,
    },
    {
        id: 'yes-master',
        label: 'Yes, Master',
        caption: 'Yes, Master.',
        src: xenYesMaster,
        aspectRatio: 1280 / 960,
        emergeFrom: 'right',
        portal: { cx: 83.44, cy: 51.35, rx: 5.78, ry: 31.35, mouth: 77.66 },
        emergeDistance: 55,
    },
];

export function xenHoleClip(mouth: number, from: XenEmergeFrom): string {
    // Visible = the side the hand exits into (outside the hole).
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
