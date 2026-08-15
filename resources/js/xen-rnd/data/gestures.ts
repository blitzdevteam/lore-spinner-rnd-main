import xenPoint from '../assets/poses/XEN.png';
import xenChefKiss from '../assets/poses/XEN-1.png';
import xenMatch from '../assets/poses/XEN-02.png';
import xenSurprise from '../assets/poses/XEN-03.png';
import xenYourMove from '../assets/poses/XEN-04.png';
import xenBold from '../assets/poses/XEN-05.png';
import xenYesMaster from '../assets/poses/XEN-06.png';

/**
 * Portal ellipse + curved exit face.
 * mouth = rim facing the hand (not a straight center cut).
 * handScale = normalize wrist→portal fit + overall hand presence.
 */

export type XenEmergeFrom = 'bottom' | 'left' | 'right';

export interface XenPortal {
    cx: number;
    cy: number;
    rx: number;
    ry: number;
    /** Exit rim % — bottom: top of ellipse; left: right rim; right: left rim */
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
    /**
     * Scale hand about the mouth so wrist fill + overall size stay uniform.
     * 1 = source art size.
     */
    handScale: number;
}

/** Rim mouth + portal opening tuned for ~50% wrist fill (cream shoulders). */
function portalOnRim(
    p: Omit<XenPortal, 'mouth'>,
    from: XenEmergeFrom,
): XenPortal {
    const mouth =
        from === 'bottom' ? p.cy - p.ry : from === 'left' ? p.cx + p.rx : p.cx - p.rx;
    return { ...p, mouth };
}

export const XEN_GESTURES: XenGesture[] = [
    {
        id: 'point',
        label: 'Point Up',
        src: xenPoint,
        aspectRatio: 1280 / 960,
        emergeFrom: 'bottom',
        // wrist ~16.6% → open ~33% (rx 16.5)
        portal: portalOnRim({ cx: 49.84, cy: 88.35, rx: 16.5, ry: 3.35 }, 'bottom'),
        emergeDistance: 72,
        handScale: 1,
    },
    {
        id: 'chef-kiss',
        label: 'Chef Kiss',
        src: xenChefKiss,
        aspectRatio: 1280 / 960,
        emergeFrom: 'left',
        // wrist ~23% → open ~46% (ry 23); was oversized
        portal: portalOnRim({ cx: 19.2, cy: 51.67, rx: 6.4, ry: 23.0 }, 'left'),
        emergeDistance: 58,
        handScale: 1.05,
    },
    {
        id: 'match',
        label: 'Lit Match',
        caption: 'Arm enters from the right — matchbox pull left.',
        src: xenMatch,
        aspectRatio: 1280 / 960,
        emergeFrom: 'right',
        // wrist ~28% → open ~56% (ry 28) so shoulders match other holes
        portal: portalOnRim({ cx: 98.4, cy: 74.3, rx: 3.2, ry: 28.0 }, 'right'),
        emergeDistance: 56,
        handScale: 0.92,
    },
    {
        id: 'surprise',
        label: 'Surprise Me',
        caption: 'Go ahead. Surprise me.',
        src: xenSurprise,
        aspectRatio: 1280 / 961,
        emergeFrom: 'bottom',
        portal: portalOnRim({ cx: 51.02, cy: 85.1, rx: 16.0, ry: 4.4 }, 'bottom'),
        emergeDistance: 64,
        handScale: 1.08,
    },
    {
        id: 'your-move',
        label: 'Your Move',
        caption: 'Your move.',
        src: xenYourMove,
        aspectRatio: 1280 / 960,
        emergeFrom: 'bottom',
        portal: portalOnRim({ cx: 50.94, cy: 85.1, rx: 16.0, ry: 4.4 }, 'bottom'),
        emergeDistance: 64,
        handScale: 1.08,
    },
    {
        id: 'bold',
        label: 'Bold Tray',
        caption: 'Bold. Possibly Unwise.',
        src: xenBold,
        aspectRatio: 1024 / 1280,
        emergeFrom: 'bottom',
        portal: portalOnRim({ cx: 59.28, cy: 86.9, rx: 15.5, ry: 3.5 }, 'bottom'),
        emergeDistance: 58,
        handScale: 1.06,
    },
    {
        id: 'yes-master',
        label: 'Yes, Master',
        caption: 'Yes, Master.',
        src: xenYesMaster,
        aspectRatio: 1280 / 960,
        emergeFrom: 'right',
        // wrist ~24% → open ~48% (ry 24); was oversized empty hole
        portal: portalOnRim({ cx: 83.1, cy: 51.3, rx: 6.2, ry: 24.0 }, 'right'),
        emergeDistance: 58,
        handScale: 1.05,
    },
];

/**
 * Curved mouth clip — follows the portal ellipse rim (objectBoundingBox 0–1).
 * Soft cream hole, not a hard straight cut.
 */
export function xenArcClipPath(portal: XenPortal, from: XenEmergeFrom): string {
    const cx = portal.cx / 100;
    const cy = portal.cy / 100;
    const rx = portal.rx / 100;
    const ry = portal.ry / 100;
    const steps = 28;

    if (from === 'bottom') {
        // Visible above the top rim arc of the ellipse
        const parts: string[] = [`M 0 0`, `L 1 0`, `L 1 ${cy - ry}`];
        // top rim from right → left: θ = 0 (right) … π (left) along upper half
        // y = cy - ry * sin(φ), x = cx + rx * cos(φ), φ from 0 to π
        for (let i = 0; i <= steps; i++) {
            const φ = (Math.PI * i) / steps;
            const x = cx + rx * Math.cos(φ);
            const y = cy - ry * Math.sin(φ);
            parts.push(`L ${x.toFixed(4)} ${y.toFixed(4)}`);
        }
        parts.push(`L 0 ${cy - ry}`, `Z`);
        return parts.join(' ');
    }

    if (from === 'left') {
        // Visible right of the right rim arc
        const parts: string[] = [`M 1 0`, `L 1 1`, `L ${cx + rx} 1`];
        // right rim bottom → top: θ from π/2 to -π/2 through the right
        // x = cx + rx * cos(θ), y = cy + ry * sin(θ), θ: +π/2 → -π/2
        for (let i = 0; i <= steps; i++) {
            const θ = Math.PI / 2 - (Math.PI * i) / steps;
            const x = cx + rx * Math.cos(θ);
            const y = cy + ry * Math.sin(θ);
            parts.push(`L ${x.toFixed(4)} ${y.toFixed(4)}`);
        }
        parts.push(`L ${cx + rx} 0`, `Z`);
        return parts.join(' ');
    }

    // right: visible left of the left rim arc
    const parts: string[] = [`M 0 0`, `L ${cx - rx} 0`];
    // left rim top → bottom: θ from -π/2 to +π/2 through the left
    // x = cx - rx * cos(θ), y = cy + ry * sin(θ), θ: -π/2 → +π/2
    for (let i = 0; i <= steps; i++) {
        const θ = -Math.PI / 2 + (Math.PI * i) / steps;
        const x = cx - rx * Math.cos(θ);
        const y = cy + ry * Math.sin(θ);
        parts.push(`L ${x.toFixed(4)} ${y.toFixed(4)}`);
    }
    parts.push(`L 0 1`, `Z`);
    return parts.join(' ');
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
