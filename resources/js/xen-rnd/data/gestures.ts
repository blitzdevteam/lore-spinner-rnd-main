import xenPoint from '../assets/poses/XEN.png';
import xenChefKiss from '../assets/poses/XEN-1.png';
import xenMatch from '../assets/poses/XEN-02.png';
import xenSurprise from '../assets/poses/XEN-03.png';
import xenYourMove from '../assets/poses/XEN-04.png';
import xenBold from '../assets/poses/XEN-05.png';
import xenYesMaster from '../assets/poses/XEN-06.png';

/**
 * Minimal gesture config. Portal/hand layers + mouth are derived from each
 * PNG at runtime so the rest frame is always an exact composite of the original.
 */
export type XenEmergeFrom = 'bottom' | 'left' | 'right';

export interface XenGesture {
    id: string;
    label: string;
    caption?: string;
    src: string;
    aspectRatio: number;
    emergeFrom: XenEmergeFrom;
    /** Retract distance as % of the stage along the emerge axis. */
    emergeDistance: number;
}

export const XEN_GESTURES: XenGesture[] = [
    {
        id: 'point',
        label: 'Point Up',
        src: xenPoint,
        aspectRatio: 1280 / 960,
        emergeFrom: 'bottom',
        emergeDistance: 75,
    },
    {
        id: 'chef-kiss',
        label: 'Chef Kiss',
        src: xenChefKiss,
        aspectRatio: 1280 / 960,
        emergeFrom: 'left',
        emergeDistance: 60,
    },
    {
        id: 'match',
        label: 'Lit Match',
        caption: 'Arm enters from the right — matchbox pull left.',
        src: xenMatch,
        aspectRatio: 1280 / 960,
        emergeFrom: 'right',
        emergeDistance: 55,
    },
    {
        id: 'surprise',
        label: 'Surprise Me',
        caption: 'Go ahead. Surprise me.',
        src: xenSurprise,
        aspectRatio: 1280 / 961,
        emergeFrom: 'bottom',
        emergeDistance: 70,
    },
    {
        id: 'your-move',
        label: 'Your Move',
        caption: 'Your move.',
        src: xenYourMove,
        aspectRatio: 1280 / 960,
        emergeFrom: 'bottom',
        emergeDistance: 70,
    },
    {
        id: 'bold',
        label: 'Bold Tray',
        caption: 'Bold. Possibly Unwise.',
        src: xenBold,
        aspectRatio: 1024 / 1280,
        emergeFrom: 'bottom',
        emergeDistance: 68,
    },
    {
        id: 'yes-master',
        label: 'Yes, Master',
        caption: 'Yes, Master.',
        src: xenYesMaster,
        aspectRatio: 1280 / 960,
        emergeFrom: 'right',
        emergeDistance: 58,
    },
];

export function xenHoleClip(mouthPercent: number, from: XenEmergeFrom): string {
    switch (from) {
        case 'bottom':
            return `inset(0 0 ${(100 - mouthPercent).toFixed(3)}% 0)`;
        case 'left':
            return `inset(0 0 0 ${mouthPercent.toFixed(3)}%)`;
        case 'right':
            return `inset(0 ${(100 - mouthPercent).toFixed(3)}% 0 0)`;
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
