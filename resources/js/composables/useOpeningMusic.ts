import { onUnmounted, ref } from 'vue';

/**
 * Single centralized audio instance for the LoreSpinner 30s sonic-ident.
 *
 * Lifecycle:
 *   cinematic → loading image → narration
 *      └──── music plays ────┘ → fade out
 *
 * Skip only affects the cinematic visual — the music keeps playing until the
 * narration-ready signal arrives, then fades smoothly to 0 over ~1.5s. The
 * fade is driven by the actual narration-ready event from the parent, never
 * by hardcoded timing.
 */

const OPENING_MUSIC_URL = '/music/LS-opening.wav';
const FADE_DURATION_MS = 1500;

// Module-level singletons: one audio instance for the whole opening flow,
// shared across the cinematic and the loading/narration phases of the page.
let sharedAudio: HTMLAudioElement | null = null;
let fadeIntervalId: ReturnType<typeof setInterval> | null = null;
let fadeStopTimeoutId: ReturnType<typeof setTimeout> | null = null;
let gestureListenerInstalled = false;
let removeGestureListener: (() => void) | null = null;

function clearFade(): void {
    if (fadeIntervalId !== null) {
        clearInterval(fadeIntervalId);
        fadeIntervalId = null;
    }
    if (fadeStopTimeoutId !== null) {
        clearTimeout(fadeStopTimeoutId);
        fadeStopTimeoutId = null;
    }
}

function detachGestureListener(): void {
    removeGestureListener?.();
    removeGestureListener = null;
    gestureListenerInstalled = false;
}

/**
 * Hard stop + reset. Called after a fade completes, on dismiss, and on unmount
 * so a returning player never inherits a stale looping track.
 */
function teardownAudio(): void {
    clearFade();
    detachGestureListener();

    if (sharedAudio) {
        sharedAudio.pause();
        try {
            sharedAudio.currentTime = 0;
        } catch {
            // WebKit can throw during a source change; safe to ignore.
        }
        sharedAudio.removeAttribute('src');
        try {
            sharedAudio.load();
        } catch {
            // Element may already be torn down.
        }
        sharedAudio = null;
    }
}

export function useOpeningMusic() {
    const isPlaying = ref(false);

    /**
     * Start the looping ident when the cinematic begins. Browsers block audio
     * autoplay before a user gesture, so a blocked play() is retried on the
     * first pointer/touch/click — the same gesture that primes narration audio.
     */
    function start(): void {
        if (sharedAudio) return; // already started

        const audio = new Audio(OPENING_MUSIC_URL);
        audio.loop = true;
        audio.volume = 1;
        audio.setAttribute('playsinline', 'true');
        audio.preload = 'auto';
        sharedAudio = audio;

        const attemptPlay = (): void => {
            const playPromise = audio.play();
            if (playPromise && typeof playPromise.then === 'function') {
                playPromise
                    .then(() => {
                        isPlaying.value = true;
                    })
                    .catch(() => {
                        // Autoplay blocked — install a one-time gesture listener.
                        if (!gestureListenerInstalled && sharedAudio === audio) {
                            gestureListenerInstalled = true;
                            const startOnGesture = (): void => {
                                if (sharedAudio === audio && audio.paused) {
                                    void audio
                                        .play()
                                        .then(() => {
                                            isPlaying.value = true;
                                        })
                                        .catch(() => {});
                                }
                                detachGestureListener();
                            };
                            const events: (keyof WindowEventMap)[] = ['pointerdown', 'touchend', 'mousedown', 'click'];
                            for (const eventName of events) {
                                window.addEventListener(eventName, startOnGesture, { capture: true, passive: true });
                            }
                            removeGestureListener = () => {
                                for (const eventName of events) {
                                    window.removeEventListener(eventName, startOnGesture, { capture: true } as EventListenerOptions);
                                }
                            };
                        }
                    });
            } else {
                isPlaying.value = true;
            }
        };

        attemptPlay();
    }

    /**
     * Smoothly fade the music to 0 over the configured duration, then stop and
     * reset the element. Triggered by the narration-ready event — never by a
     * timer. Safe to call whether the cinematic completed normally or was skipped.
     */
    function fadeOut(durationMs: number = FADE_DURATION_MS): void {
        const audio = sharedAudio;
        if (!audio || fadeIntervalId !== null) return;

        const startVolume = Number.isFinite(audio.volume) ? audio.volume : 1;
        if (startVolume <= 0) {
            teardownAudio();
            isPlaying.value = false;
            return;
        }

        const startTime = Date.now();

        const finish = (): void => {
            if (audio === sharedAudio) {
                teardownAudio();
            }
            isPlaying.value = false;
        };

        // Timers rather than requestAnimationFrame: rAF is suspended in background
        // tabs, which left the loop playing while the player waited elsewhere.
        // iOS also ignores element volume, so the hard stop must not depend on it.
        fadeIntervalId = setInterval(() => {
            const progress = Math.min(1, (Date.now() - startTime) / durationMs);

            if (audio === sharedAudio) {
                audio.volume = Math.max(0, startVolume * (1 - progress));
            }

            if (progress >= 1) {
                finish();
            }
        }, 50);
        fadeStopTimeoutId = setTimeout(finish, durationMs + 100);
    }

    /**
     * Immediate stop without a fade. Used when the cinematic is dismissed
     * without narration (e.g. opening error) or on unmount.
     */
    function stop(): void {
        teardownAudio();
        isPlaying.value = false;
    }

    onUnmounted(() => {
        teardownAudio();
        isPlaying.value = false;
    });

    return {
        isPlaying,
        start,
        fadeOut,
        stop,
    };
}
