import xenPoint from '../assets/poses/XEN.png';
import xenChefKiss from '../assets/poses/XEN-1.png';
import xenMatch from '../assets/poses/XEN-02.png';
import xenSurprise from '../assets/poses/XEN-03.png';
import xenYourMove from '../assets/poses/XEN-04.png';
import xenBold from '../assets/poses/XEN-05.png';
import xenYesMaster from '../assets/poses/XEN-06.png';

export type XenEmergeAxis = 'x' | 'y';
export type XenPortalSide = 'bottom' | 'left' | 'right';

export interface XenGesture {
    id: string;
    label: string;
    caption?: string;
    src: string;
    axis: XenEmergeAxis;
    /** 1 = emerge up / right, -1 = emerge left */
    direction: 1 | -1;
    portalSide: XenPortalSide;
    /** Portal ellipse size relative to stage (%) */
    portalWidth: number;
    portalHeight: number;
    /** Portal center offset from its anchored edge (%) */
    portalInset: number;
    /** How far the hand travels while emerging (% of stage) */
    travel: number;
    /** Fine-tune hand anchor at rest (%) */
    handNudgeX: number;
    handNudgeY: number;
}

export const XEN_GESTURES: XenGesture[] = [
    {
        id: 'point',
        label: 'Point Up',
        src: xenPoint,
        axis: 'y',
        direction: 1,
        portalSide: 'bottom',
        portalWidth: 38,
        portalHeight: 7,
        portalInset: 7,
        travel: 72,
        handNudgeX: 0,
        handNudgeY: 0,
    },
    {
        id: 'chef-kiss',
        label: 'Chef Kiss',
        src: xenChefKiss,
        axis: 'x',
        direction: 1,
        portalSide: 'left',
        portalWidth: 7,
        portalHeight: 52,
        portalInset: 11,
        travel: 68,
        handNudgeX: 0,
        handNudgeY: 0,
    },
    {
        id: 'match',
        label: 'Lit Match',
        caption: 'Arm enters from the right — matchbox pull left.',
        src: xenMatch,
        axis: 'x',
        direction: -1,
        portalSide: 'right',
        portalWidth: 6,
        portalHeight: 48,
        portalInset: 0,
        travel: 62,
        handNudgeX: 0,
        handNudgeY: 0,
    },
    {
        id: 'surprise',
        label: 'Surprise Me',
        caption: 'Go ahead. Surprise me.',
        src: xenSurprise,
        axis: 'y',
        direction: 1,
        portalSide: 'bottom',
        portalWidth: 36,
        portalHeight: 7,
        portalInset: 8,
        travel: 74,
        handNudgeX: 0,
        handNudgeY: 0,
    },
    {
        id: 'your-move',
        label: 'Your Move',
        caption: 'Your move.',
        src: xenYourMove,
        axis: 'y',
        direction: 1,
        portalSide: 'bottom',
        portalWidth: 36,
        portalHeight: 7,
        portalInset: 8,
        travel: 74,
        handNudgeX: 0,
        handNudgeY: 0,
    },
    {
        id: 'bold',
        label: 'Bold Tray',
        caption: 'Bold. Possibly Unwise.',
        src: xenBold,
        axis: 'y',
        direction: 1,
        portalSide: 'bottom',
        portalWidth: 34,
        portalHeight: 6,
        portalInset: 7,
        travel: 78,
        handNudgeX: 0,
        handNudgeY: 0,
    },
    {
        id: 'yes-master',
        label: 'Yes, Master',
        caption: 'Yes, Master.',
        src: xenYesMaster,
        axis: 'x',
        direction: -1,
        portalSide: 'right',
        portalWidth: 7,
        portalHeight: 50,
        portalInset: 10,
        travel: 66,
        handNudgeX: 0,
        handNudgeY: 0,
    },
];
