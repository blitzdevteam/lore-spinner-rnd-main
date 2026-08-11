import xenPoint from '../assets/poses/XEN.png';
import xenChefKiss from '../assets/poses/XEN-1.png';
import xenMatch from '../assets/poses/XEN-02.png';
import xenSurprise from '../assets/poses/XEN-03.png';
import xenYourMove from '../assets/poses/XEN-04.png';
import xenBold from '../assets/poses/XEN-05.png';
import xenYesMaster from '../assets/poses/XEN-06.png';

export type XenEmergeAxis = 'x' | 'y';

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
    /** Stage width / height — matches PNG so portal coords map 1:1. */
    aspectRatio: number;
    /** Live white portal rim — fixed on stage. */
    portal: XenPortalRect;
    /** Moves with the image; erases baked white portal pixels in the art. */
    bakedCover?: XenPortalRect;
    emergeAxis: XenEmergeAxis;
    /** Signed start offset (% of stage). Finish is always translate(0). */
    emergeStart: number;
}

export const XEN_GESTURES: XenGesture[] = [
    {
        id: 'point',
        label: 'Point Up',
        src: xenPoint,
        aspectRatio: 1280 / 960,
        portal: { cx: 50, cy: 88.25, rx: 13.5, ry: 3.1 },
        bakedCover: { cx: 50, cy: 88.25, rx: 14.5, ry: 3.8 },
        emergeAxis: 'y',
        emergeStart: 74,
    },
    {
        id: 'chef-kiss',
        label: 'Chef Kiss',
        src: xenChefKiss,
        aspectRatio: 1280 / 960,
        portal: { cx: 19, cy: 37.9, rx: 6, ry: 17.9 },
        bakedCover: { cx: 19, cy: 50, rx: 7.5, ry: 32 },
        emergeAxis: 'x',
        emergeStart: -68,
    },
    {
        id: 'match',
        label: 'Lit Match',
        caption: 'Arm enters from the right — matchbox pull left.',
        src: xenMatch,
        aspectRatio: 1280 / 960,
        portal: { cx: 97.5, cy: 51, rx: 2.5, ry: 18.5 },
        emergeAxis: 'x',
        emergeStart: 66,
    },
    {
        id: 'surprise',
        label: 'Surprise Me',
        caption: 'Go ahead. Surprise me.',
        src: xenSurprise,
        aspectRatio: 1280 / 961,
        portal: { cx: 50, cy: 84.8, rx: 17, ry: 3.2 },
        bakedCover: { cx: 52, cy: 87.5, rx: 20, ry: 7.5 },
        emergeAxis: 'y',
        emergeStart: 74,
    },
    {
        id: 'your-move',
        label: 'Your Move',
        caption: 'Your move.',
        src: xenYourMove,
        aspectRatio: 1280 / 960,
        portal: { cx: 50, cy: 84.8, rx: 17, ry: 3.2 },
        bakedCover: { cx: 52, cy: 87.5, rx: 20, ry: 7.5 },
        emergeAxis: 'y',
        emergeStart: 74,
    },
    {
        id: 'bold',
        label: 'Bold Tray',
        caption: 'Bold. Possibly Unwise.',
        src: xenBold,
        aspectRatio: 1024 / 1280,
        portal: { cx: 59.7, cy: 86.65, rx: 18, ry: 3.3 },
        bakedCover: { cx: 59.7, cy: 86.65, rx: 19.5, ry: 4.5 },
        emergeAxis: 'y',
        emergeStart: 72,
    },
    {
        id: 'yes-master',
        label: 'Yes, Master',
        caption: 'Yes, Master.',
        src: xenYesMaster,
        aspectRatio: 1280 / 960,
        portal: { cx: 83.1, cy: 53.2, rx: 6.5, ry: 22.5 },
        bakedCover: { cx: 83.1, cy: 53.2, rx: 7.5, ry: 24 },
        emergeAxis: 'x',
        emergeStart: 68,
    },
];
