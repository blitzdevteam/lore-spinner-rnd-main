import { computed, onUnmounted, ref } from 'vue';

const SPEED_OPTIONS = [1, 1.25, 1.5, 1.75, 2] as const;

// iOS Safari reports Infinity for duration on chunked streaming responses
// (no Content-Length). The file is cached server-side after the first play,
// so subsequent plays get a proper Content-Length and full seek support.
const IOS = typeof navigator !== 'undefined' && /iP(hone|od|ad)/.test(navigator.userAgent);

// Module-level singleton — shared across all chaos components in the page
const audioCache = new Map<string, HTMLAudioElement>();

// Track per-key retry attempts for iOS error recovery
const retryCounts = new Map<string, number>();

const isPlaying   = ref(false);
const isLoading   = ref(false);
const currentTime = ref(0);
const duration    = ref(0);
const playbackRate = ref(1);
const activeKey   = ref<string | null>(null);

let currentAudio: HTMLAudioElement | null = null;
let rafId: number | null = null;

function updateTime() {
    if (currentAudio) {
        currentTime.value = currentAudio.currentTime;
        // Duration is Infinity on first iOS play of a streamed (uncached) response;
        // only surface it once it resolves to a finite value.
        const d = currentAudio.duration;
        if (d && isFinite(d)) {
            duration.value = d;
        }
    }
    if (isPlaying.value) {
        rafId = requestAnimationFrame(updateTime);
    }
}

function stopTimeUpdates() {
    if (rafId !== null) {
        cancelAnimationFrame(rafId);
        rafId = null;
    }
}

function attachListeners(audio: HTMLAudioElement, key: string, src: string) {
    if ((audio as any).__chaosListenersAttached) return;
    (audio as any).__chaosListenersAttached = true;

    audio.addEventListener('canplay', () => {
        isLoading.value = false;
        const d = audio.duration;
        if (d && isFinite(d)) duration.value = d;
    });

    audio.addEventListener('playing', () => {
        isPlaying.value = true;
        isLoading.value = false;
        const d = audio.duration;
        if (d && isFinite(d)) duration.value = d;
        updateTime();
    });

    audio.addEventListener('ended', () => {
        isPlaying.value = false;
        stopTimeUpdates();
    });

    audio.addEventListener('pause', () => {
        isPlaying.value = false;
        stopTimeUpdates();
    });

    audio.addEventListener('loadedmetadata', () => {
        const d = audio.duration;
        if (d && isFinite(d)) duration.value = d;
    });

    // `waiting` fires when playback stalls mid-stream (common on mobile/iOS when
    // the network can't keep up). Show the loading spinner so the user knows it's
    // buffering, not frozen.
    audio.addEventListener('waiting', () => {
        if (activeKey.value === key) {
            isLoading.value = true;
        }
    });

    // `stalled` fires when the browser can't fetch data for ~3 seconds. On iOS
    // this sometimes happens on the very first play of a chunked streaming response.
    // Calling load() + play() forces WebKit to restart the HTTP request.
    audio.addEventListener('stalled', () => {
        if (activeKey.value !== key || audio.readyState >= 3) return;

        const retries = retryCounts.get(key) ?? 0;
        if (retries >= 2) return;

        retryCounts.set(key, retries + 1);
        isLoading.value = true;

        audio.load();
        audio.play().catch(() => {
            isPlaying.value = false;
            isLoading.value = false;
        });
    });

    audio.addEventListener('error', () => {
        const retries = retryCounts.get(key) ?? 0;

        // On iOS, a streaming (uncached) response can occasionally trigger a
        // transient MEDIA_ERR_NETWORK. Retry once with a brief delay before giving up.
        if (IOS && retries < 1 && activeKey.value === key) {
            retryCounts.set(key, retries + 1);
            isLoading.value = true;

            setTimeout(() => {
                // Remove the stale element and request a fresh one from the server.
                // The server will either stream again or serve from disk if it finished
                // caching during the first attempt.
                audioCache.delete(key);
                const fresh = new Audio(src);
                fresh.preload      = 'auto';
                fresh.playbackRate = playbackRate.value;
                attachListeners(fresh, key, src);
                audioCache.set(key, fresh);
                currentAudio    = fresh;
                activeKey.value = key;
                isLoading.value = true;
                fresh.play().catch(() => {
                    isPlaying.value = false;
                    isLoading.value = false;
                });
            }, 800);

            return;
        }

        isPlaying.value = false;
        isLoading.value = false;
        stopTimeUpdates();
        console.warn('[ChaosTTS] audio error (no more retries)', key);
    });
}

/** Play the narrator turn at `turnIndex` for the given chaos session. */
function play(sessionId: string, turnIndex: number) {
    const key = `${sessionId}:${turnIndex}`;
    const src = `/chaos-mode/${sessionId}/tts/${turnIndex}`;

    if (currentAudio && activeKey.value === key && !currentAudio.ended) {
        if (currentAudio.paused) {
            currentAudio.play().catch(() => {
                isPlaying.value = false;
                isLoading.value = false;
            });
        }
        return;
    }

    stop();

    if (audioCache.has(key)) {
        currentAudio              = audioCache.get(key)!;
        activeKey.value           = key;
        currentAudio.currentTime  = 0;
        currentAudio.playbackRate = playbackRate.value;
        currentAudio.play().catch(() => {
            isPlaying.value = false;
            isLoading.value = false;
        });
        return;
    }

    retryCounts.delete(key);

    isLoading.value = true;
    const audio = new Audio(src);
    audio.preload      = 'auto';
    audio.playbackRate = playbackRate.value;

    // iOS Safari: setting crossOrigin to anonymous lets the browser cache the
    // response in the HTTP cache. Without this, Safari re-fetches on every play.
    if (IOS) {
        audio.crossOrigin = 'anonymous';
    }

    attachListeners(audio, key, src);
    audioCache.set(key, audio);
    currentAudio    = audio;
    activeKey.value = key;

    audio.play().catch(() => {
        isPlaying.value = false;
        isLoading.value = false;
    });
}

function stop() {
    if (currentAudio) {
        currentAudio.pause();
        currentAudio.currentTime = 0;
    }
    isPlaying.value   = false;
    isLoading.value   = false;
    currentTime.value = 0;
    stopTimeUpdates();
}

function dismiss() {
    stop();
    activeKey.value = null;
}

function pause() {
    if (currentAudio && !currentAudio.paused) {
        currentAudio.pause();
    }
}

function resume() {
    if (currentAudio && currentAudio.paused && !currentAudio.ended) {
        currentAudio.play().catch(() => {
            isPlaying.value = false;
        });
    }
}

function togglePause() {
    if (isPlaying.value) pause();
    else resume();
}

function toggle(sessionId: string, turnIndex: number) {
    const key = `${sessionId}:${turnIndex}`;
    if (isPlaying.value && activeKey.value === key) {
        pause();
    } else if (activeKey.value === key && currentAudio && !currentAudio.ended) {
        resume();
    } else {
        play(sessionId, turnIndex);
    }
}

function cycleSpeed() {
    const idx  = SPEED_OPTIONS.indexOf(playbackRate.value as (typeof SPEED_OPTIONS)[number]);
    const next = (idx + 1) % SPEED_OPTIONS.length;
    playbackRate.value = SPEED_OPTIONS[next];
    if (currentAudio) currentAudio.playbackRate = playbackRate.value;
}

const isActive = computed(() => activeKey.value !== null);

const formattedCurrentTime = computed(() => formatTime(currentTime.value));
const formattedDuration    = computed(() => formatTime(duration.value));

function formatTime(seconds: number): string {
    if (!seconds || !isFinite(seconds)) return '0:00';
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);
    return `${m}:${s.toString().padStart(2, '0')}`;
}

export function useChaosTextToSpeech() {
    onUnmounted(() => {
        stopTimeUpdates();
    });

    return {
        isPlaying,
        isLoading,
        isActive,
        currentTime,
        duration,
        playbackRate,
        formattedCurrentTime,
        formattedDuration,
        activeKey,
        play,
        stop,
        dismiss,
        pause,
        resume,
        toggle,
        togglePause,
        cycleSpeed,
    };
}
