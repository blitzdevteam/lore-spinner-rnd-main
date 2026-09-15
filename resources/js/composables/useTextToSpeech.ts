import { computed, onUnmounted, ref } from 'vue';

const SPEED_OPTIONS = [1, 1.25, 1.5, 1.75, 2] as const;

// Short, valid silent WAV — WebKit rejects the zero-length WAV that Chrome accepts.
const SILENT_WAV =
    'data:audio/wav;base64,UklGRrQBAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YZABAACAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICA';
// The endpoint answers 202 while the voice provider is still rendering a turn,
// which can take a couple of minutes for a long narration.
const NARRATION_POLL_INTERVAL_MS = 2000;
const NARRATION_MAX_WAIT_MS = 240_000;

const audioCache = new Map<string, HTMLAudioElement>();
const objectUrls = new Map<string, string>();
const inFlightUrls = new Map<string, Promise<string>>();

const isPlaying = ref(false);
const isLoading = ref(false);
const currentTime = ref(0);
const duration = ref(0);
const playbackRate = ref(1);
const volume = ref(1);
const isMuted = ref(false);
const isLooping = ref(false);
const activeKey = ref<string | null>(null);
const playedKeys = new Set<string>();
const mediaCollapsed = ref(false);

function revealMediaPlayer() {
    mediaCollapsed.value = false;
}

function collapseMediaPlayer() {
    mediaCollapsed.value = true;
}

let currentAudio: HTMLAudioElement | null = null;

// The narration `currentAudio` belongs to. Kept separate from activeKey, which
// is a UI concern that moves ahead of the element while a turn is queued.
let currentKey: string | null = null;

let rafId: number | null = null;
let unlockAudio: HTMLAudioElement | null = null;
let audioUnlocked = false;

// iOS Safari only allows HTMLAudioElement.play() on elements that were previously
// activated during a user gesture. We pre-claim an element so the subsequent async
// autoplay call can reuse it (src-swap preserves iOS activation).
let primedAudio: HTMLAudioElement | null = null;

const AUTOPLAY_GESTURE_EVENTS = ['touchend', 'mousedown', 'click'] as const;
let autoplayGestureListenerInstalled = false;
let autoplayGestureListenerUsers = 0;
let removeAutoplayGestureListeners: (() => void) | null = null;

// Key whose element is claimed but has no real source yet, because the mp3 is
// still downloading. Calling play() on it would fail with NotSupportedError.
let awaitingSourceKey: string | null = null;
let awaitingSourceAudio: HTMLAudioElement | null = null;

// Identifies the newest playback request. Anything that silences audio bumps it,
// so a download that lands afterwards knows not to start on its own.
let playToken = 0;
let pendingSeek: { audio: HTMLAudioElement; key: string; seconds: number } | null = null;

// Keep media on the browser's native HTMLAudioElement path. Routing the element
// through MediaElementAudioSourceNode makes WebKit's seek and rate handling much
// less predictable, while a gesture-primed element is enough to unlock autoplay.
function configureAudioElement(audio: HTMLAudioElement): void {
    audio.setAttribute('playsinline', 'true');
    audio.preload = 'auto';
}

function isAutoplayBlocked(err: unknown): boolean {
    return err instanceof DOMException && err.name === 'NotAllowedError';
}

function isCurrentAudio(audio: HTMLAudioElement, key: string): boolean {
    return currentAudio === audio && currentKey === key;
}

function isCurrentRequest(audio: HTMLAudioElement, key: string, token: number): boolean {
    return isCurrentAudio(audio, key) && playToken === token;
}

/**
 * Only one narration may ever be audible. Every cached turn keeps its own
 * element, so a previous turn can still be mid-playback when a new one takes
 * over — pausing `currentAudio` alone would leave it running.
 */
function silenceOthers(keep: HTMLAudioElement | null) {
    const rewind = (audio: HTMLAudioElement) => {
        if (!audio.paused) audio.pause();
        try {
            if (audio.currentTime) audio.currentTime = 0;
        } catch {
            // The element may still be between source changes in WebKit.
        }
    };

    for (const audio of audioCache.values()) {
        if (audio !== keep) rewind(audio);
    }

    if (currentAudio && currentAudio !== keep) rewind(currentAudio);
}

/** Call synchronously from a user-gesture handler (tap/click) to unlock mobile audio. */
function primeAudio() {
    if (typeof window === 'undefined') return;

    if (!audioUnlocked) {
        // Play a valid silent clip during the gesture. It is deliberately not
        // muted: WebKit may allow muted playback without granting media access.
        if (!unlockAudio) {
            unlockAudio = new Audio(SILENT_WAV);
            unlockAudio.volume = 0.001;
            configureAudioElement(unlockAudio);
        }
        void unlockAudio.play().catch(() => {});

        // Mark unlocked immediately — we ARE inside a user-gesture call stack.
        //    Deferring to .then() risks losing gesture propagation on iOS Safari.
        audioUnlocked = true;
    }

    // Pre-claim an HTMLAudioElement within this gesture context.
    //    iOS Safari permits async .play() on the SAME instance later (even after
    //    src is changed), because the activation is tied to the element object.
    //    Keep an already-primed element instead of creating a new media element
    //    for every captured gesture and leaking stale WebKit media state.
    if (!primedAudio) {
        const primed = new Audio(SILENT_WAV);
        primed.volume = 0.001;
        configureAudioElement(primed);
        primedAudio = primed;
        void primed.play().catch(() => {
            // Do not reuse an element that WebKit failed to activate.
            if (primedAudio === primed) primedAudio = null;
        });
    }
}

/**
 * Safari grants media activation from the completed user gesture, not reliably
 * from pointerdown/touchstart. Capture the first valid gesture at document level
 * so the unlock runs before a gameplay control starts its async request.
 */
function detachAutoplayGestureListeners(): void {
    removeAutoplayGestureListeners?.();
    removeAutoplayGestureListeners = null;
}

function installAutoplayGestureListener(): () => void {
    if (typeof window === 'undefined') {
        return () => {};
    }

    autoplayGestureListenerUsers += 1;

    if (!autoplayGestureListenerInstalled && !audioUnlocked) {
        const unlockFromGesture = () => {
            primeAudio();
            detachAutoplayGestureListeners();
        };

        const removeListeners = () => {
            if (!autoplayGestureListenerInstalled) return;

            for (const eventName of AUTOPLAY_GESTURE_EVENTS) {
                window.removeEventListener(eventName, unlockFromGesture, true);
            }

            autoplayGestureListenerInstalled = false;
        };

        removeAutoplayGestureListeners = removeListeners;
        autoplayGestureListenerInstalled = true;

        for (const eventName of AUTOPLAY_GESTURE_EVENTS) {
            window.addEventListener(eventName, unlockFromGesture, {
                capture: true,
                passive: true,
            });
        }
    }

    let released = false;

    return () => {
        if (released) return;
        released = true;
        autoplayGestureListenerUsers = Math.max(0, autoplayGestureListenerUsers - 1);

        if (autoplayGestureListenerUsers === 0) {
            detachAutoplayGestureListeners();
        }
    };
}

function handlePlayRejected(audio: HTMLAudioElement, key: string, token: number, err: unknown): void {
    // A play() promise can settle after pause, close, or a newer narration has
    // taken over. Those callbacks must not resurrect the old request.
    if (!isCurrentRequest(audio, key, token)) return;

    isPlaying.value = false;
    if (isAutoplayBlocked(err)) {
        // Autoplay may be disallowed after a reload. Leave the narration ready
        // for the player to start with Listen instead of holding the turn in a
        // fake loading state until some unrelated click happens.
        console.debug('[TTS] autoplay needs a player gesture', key);
    }

    isLoading.value = false;
}

function playAudio(audio: HTMLAudioElement, key: string, token: number): void {
    void audio.play().catch((err) => {
        handlePlayRejected(audio, key, token, err);
    });
}

function finiteDuration(value: number): number | null {
    return Number.isFinite(value) && value > 0 ? value : null;
}

function clampSeekTarget(audio: HTMLAudioElement, seconds: number): number {
    const requested = Number.isFinite(seconds) ? Math.max(0, seconds) : 0;
    const max = finiteDuration(audio.duration) ?? finiteDuration(duration.value);

    return max === null ? requested : Math.min(max, requested);
}

function applyPendingSeek(audio: HTMLAudioElement, key: string): void {
    const seek = pendingSeek;

    if (seek === null || seek.audio !== audio || seek.key !== key || audio.readyState < 1) return;

    const target = clampSeekTarget(audio, seek.seconds);

    try {
        audio.currentTime = target;
        pendingSeek = null;
        currentTime.value = Number.isFinite(audio.currentTime) ? audio.currentTime : target;
    } catch {
        // WebKit can still reject the setter during the metadata transition;
        // the next metadata/readiness event will retry the same target.
    }
}

function syncAudioTime(audio: HTMLAudioElement, key: string): void {
    if (!isCurrentAudio(audio, key)) return;

    applyPendingSeek(audio, key);

    if (Number.isFinite(audio.currentTime)) {
        currentTime.value = Math.max(0, audio.currentTime);
    }

    const audioDuration = finiteDuration(audio.duration);
    if (audioDuration !== null) {
        duration.value = audioDuration;
    }
}

function updateTime(audio: HTMLAudioElement, key: string): void {
    if (!isCurrentAudio(audio, key)) return;

    syncAudioTime(audio, key);

    if (isPlaying.value && rafId === null) {
        rafId = window.requestAnimationFrame(() => {
            rafId = null;
            updateTime(audio, key);
        });
    }
}

function stopTimeUpdates() {
    if (rafId !== null) {
        cancelAnimationFrame(rafId);
        rafId = null;
    }
}

function attachListeners(audio: HTMLAudioElement, key: string): void {
    // Guard: only attach once per element so cached replays don't stack handlers.
    if ((audio as any).__ttsListenersAttached) return;
    (audio as any).__ttsListenersAttached = true;

    // Handlers write shared state, so an element from an earlier turn being
    // silenced must not report over the turn that replaced it.
    const isCurrent = () => isCurrentAudio(audio, key);

    audio.addEventListener('canplay', () => {
        if (!isCurrent()) return;
        applyAudioSettings(audio);
        applyPendingSeek(audio, key);
        syncAudioTime(audio, key);
        isLoading.value = false;
        console.debug('[TTS] canplay', key, 'duration', finiteDuration(audio.duration) ?? 0);
    });
    audio.addEventListener('playing', () => {
        if (!isCurrent()) {
            audio.pause(); // a stale turn must never be audible
            return;
        }
        applyAudioSettings(audio);
        applyPendingSeek(audio, key);
        isPlaying.value = true;
        isLoading.value = false;
        syncAudioTime(audio, key);
        playedKeys.add(key);
        console.debug('[TTS] playing', key);
        updateTime(audio, key);
    });
    audio.addEventListener('ended', () => {
        if (!isCurrent()) return;
        if (isLooping.value) {
            try {
                audio.currentTime = 0;
            } catch {
                isPlaying.value = false;
                return;
            }

            const token = playToken;
            void audio.play().catch(() => {
                if (!isCurrentRequest(audio, key, token)) return;
                isPlaying.value = false;
            });
            return;
        }
        isPlaying.value = false;
        stopTimeUpdates();
        console.debug('[TTS] ended', key);
    });
    audio.addEventListener('pause', () => {
        if (!isCurrent()) return;
        isPlaying.value = false;
        stopTimeUpdates();
        console.debug('[TTS] pause', key);
    });
    audio.addEventListener('error', (e) => {
        if (!isCurrent()) return;
        isPlaying.value = false;
        isLoading.value = false;
        stopTimeUpdates();
        console.warn('[TTS] error', key, (e.target as HTMLAudioElement).error);
    });
    audio.addEventListener('loadedmetadata', () => {
        if (!isCurrent()) return;
        applyAudioSettings(audio);
        applyPendingSeek(audio, key);
        syncAudioTime(audio, key);
        console.debug('[TTS] loadedmetadata', key, 'duration', finiteDuration(audio.duration) ?? 0);
    });
    audio.addEventListener('durationchange', () => {
        if (!isCurrent()) return;
        applyPendingSeek(audio, key);
        syncAudioTime(audio, key);
    });
    audio.addEventListener('timeupdate', () => {
        syncAudioTime(audio, key);
    });
    audio.addEventListener('stalled', () => {
        console.warn('[TTS] stalled', key);
    });
    audio.addEventListener('waiting', () => {
        if (!isCurrent()) return;
        isLoading.value = true;
        console.debug('[TTS] waiting (buffering)', key);
    });
}

function delay(ms: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

/**
 * Download a narration mp3, waiting out the server-side render.
 *
 * The audio element is never pointed at the endpoint directly: a cache miss
 * takes far longer than the gateway timeout, which surfaced as a 504 followed
 * by a MediaError "Format error" instead of narration.
 */
async function downloadNarration(endpoint: string): Promise<Blob> {
    const deadline = Date.now() + NARRATION_MAX_WAIT_MS;

    while (Date.now() < deadline) {
        const response = await fetch(endpoint, { headers: { Accept: 'audio/mpeg' } });

        if (response.status === 202) {
            await delay(NARRATION_POLL_INTERVAL_MS);
            continue;
        }

        if (!response.ok) {
            const payload = (await response.json().catch(() => null)) as {
                message?: string;
                reason?: string;
                pricing_url?: string;
            } | null;

            if (response.status === 403 && payload?.reason === 'subscription_required' && payload.pricing_url) {
                window.location.assign(payload.pricing_url);
            }

            throw new Error(payload?.message ?? `narration request failed (${response.status})`);
        }

        const blob = await response.blob();

        // A redirect to the login page would otherwise reach the audio element as HTML.
        if (!blob.type.startsWith('audio/')) {
            throw new Error(`unexpected narration content type "${blob.type || 'unknown'}"`);
        }

        return blob;
    }

    throw new Error('narration render timed out');
}

function narrationUrl(gameId: string, promptId: string): Promise<string> {
    const key = `${gameId}:${promptId}`;
    const ready = objectUrls.get(key);
    if (ready) return Promise.resolve(ready);

    const pending = inFlightUrls.get(key);
    if (pending) return pending;

    const request = downloadNarration(`/user/games/${gameId}/tts/${promptId}`)
        .then((blob) => {
            const url = URL.createObjectURL(blob);
            objectUrls.set(key, url);
            return url;
        })
        .finally(() => {
            inFlightUrls.delete(key);
        });

    inFlightUrls.set(key, request);

    return request;
}

/** Warm the cache (and kick off the server-side render) without playing. */
function prefetch(gameId: string, promptId: string) {
    narrationUrl(gameId, promptId).catch((err) => {
        console.debug('[TTS] prefetch failed', `${gameId}:${promptId}`, err);
    });
}

function applyAudioSettings(audio: HTMLAudioElement) {
    audio.defaultPlaybackRate = playbackRate.value;
    audio.playbackRate = playbackRate.value;
    audio.volume = volume.value;
    audio.muted = isMuted.value;
    audio.loop = false; // looping handled manually in the `ended` listener
}

function play(gameId: string, promptId: string) {
    const key = `${gameId}:${promptId}`;

    if (currentAudio && currentKey === key && !currentAudio.ended) {
        if (awaitingSourceKey === key) return; // download in flight, spinner already up
        if (currentAudio.paused) {
            silenceOthers(currentAudio);
            isLoading.value = true;
            revealMediaPlayer();
            playAudio(currentAudio, key, playToken);
        }
        return;
    }

    revealMediaPlayer();
    stop();

    if (audioCache.has(key)) {
        currentAudio = audioCache.get(key)!;
        currentKey = key;
        activeKey.value = key;
        silenceOthers(currentAudio);
        try {
            currentAudio.currentTime = 0;
        } catch {
            pendingSeek = { audio: currentAudio, key, seconds: 0 };
        }
        applyAudioSettings(currentAudio);
        duration.value = finiteDuration(currentAudio.duration) ?? 0;
        isLoading.value = true;
        playAudio(currentAudio, key, playToken);
        return;
    }

    isLoading.value = true;
    console.debug('[TTS] fetching', key);

    // Claim the element pre-activated during the last user gesture *now*, while
    // we may still be inside the gesture stack. iOS Safari keeps that activation
    // across a later src-swap + load(), so the deferred play() is permitted.
    const audio = primedAudio ?? new Audio();
    primedAudio = null;
    audio.preload = 'auto';

    configureAudioElement(audio);
    applyAudioSettings(audio);
    currentAudio = audio;
    currentKey = key;
    activeKey.value = key;
    awaitingSourceKey = key;
    awaitingSourceAudio = audio;
    const token = playToken;

    narrationUrl(gameId, promptId)
        .then((url) => {
            if (!isCurrentAudio(audio, key)) return; // the player moved on while it rendered

            // Exactly one src assignment on this element, and listeners go on
            // straight after it, so the silent unlock clip it may still be
            // holding cannot report events against this narration.
            audio.src = url;
            audio.load();
            attachListeners(audio, key);
            audioCache.set(key, audio);
            applyAudioSettings(audio);
            if (awaitingSourceAudio === audio && awaitingSourceKey === key) {
                awaitingSourceAudio = null;
                awaitingSourceKey = null;
            }

            // Paused or stopped while this downloaded — hand over the loaded
            // element but leave it silent.
            if (token !== playToken) {
                isLoading.value = false;
                return;
            }

            // A turn that was already playing can have run all the way to this
            // point while the new one downloaded.
            silenceOthers(audio);

            playAudio(audio, key, token);
        })
        .catch((err) => {
            console.warn('[TTS] unavailable', key, err);
            if (isCurrentRequest(audio, key, token)) {
                isLoading.value = false;
            }
        })
        .finally(() => {
            if (awaitingSourceAudio === audio && awaitingSourceKey === key) {
                awaitingSourceAudio = null;
                awaitingSourceKey = null;
            }
        });
}

function autoplay(gameId: string, promptId: string): void {
    // Browsers prohibit audio started during a page load. Do not fetch or charge
    // for narration until the player has interacted with this document.
    if (!audioUnlocked) {
        return;
    }

    play(gameId, promptId);
}

function stop() {
    playToken++;
    pendingSeek = null;
    silenceOthers(null); // rewinds every element, current one included
    isPlaying.value = false;
    isLoading.value = false;
    currentTime.value = 0;
    duration.value = 0;
    stopTimeUpdates();
}

function dismiss() {
    const audio = currentAudio;
    const key = currentKey;

    awaitingSourceAudio = null;
    awaitingSourceKey = null;
    pendingSeek = null;
    stop();

    currentAudio = null;
    currentKey = null;
    activeKey.value = null;

    if (audio) {
        if (key !== null) audioCache.delete(key);
        audio.pause();
        audio.removeAttribute('src');
        audio.load();
    }

    if (primedAudio) {
        primedAudio.pause();
        primedAudio.removeAttribute('src');
        primedAudio.load();
        primedAudio = null;
    }
}

/** Stop playback and discard cached narrations (e.g. after resetting the story). */
function resetForNewStory() {
    dismiss();
    currentAudio = null;
    currentKey = null;
    for (const audio of audioCache.values()) {
        audio.pause();
        try {
            audio.currentTime = 0;
        } catch {
            // The element may not have metadata after a failed source change.
        }
        audio.removeAttribute('src');
        audio.load();
    }
    audioCache.clear();
    for (const url of objectUrls.values()) {
        URL.revokeObjectURL(url);
    }
    objectUrls.clear();
    inFlightUrls.clear();
    playedKeys.clear();
    duration.value = 0;
    mediaCollapsed.value = false;
}

function pause() {
    playToken++;
    isLoading.value = false;

    // Keep the current turn's position, but leave nothing else running.
    silenceOthers(currentAudio);
    if (currentAudio && !currentAudio.paused) {
        currentAudio.pause();
    }
    isPlaying.value = false;
}

function resume() {
    if (awaitingSourceKey !== null) return;
    if (currentAudio && currentAudio.paused && !currentAudio.ended) {
        silenceOthers(currentAudio);
        isLoading.value = true;
        const key = currentKey ?? activeKey.value;
        if (!key) return;
        playAudio(currentAudio, key, playToken);
    }
}

function togglePause() {
    // Pressing pause mid-load has to cancel the pending start, otherwise
    // priming for a resume would flush it straight back into playback.
    if (isPlaying.value || isLoading.value) {
        pause();
        return;
    }
    primeAudio();
    resume();
}

function toggle(gameId: string, promptId: string) {
    primeAudio();
    const key = `${gameId}:${promptId}`;
    if (currentKey === key && isPlaying.value) {
        pause();
    } else if (currentKey === key && currentAudio && !currentAudio.ended) {
        revealMediaPlayer();
        resume();
    } else {
        play(gameId, promptId);
    }
}

function cycleSpeed() {
    const currentIndex = SPEED_OPTIONS.indexOf(playbackRate.value as (typeof SPEED_OPTIONS)[number]);
    const nextIndex = (currentIndex + 1) % SPEED_OPTIONS.length;
    playbackRate.value = SPEED_OPTIONS[nextIndex];
    if (currentAudio) {
        currentAudio.defaultPlaybackRate = playbackRate.value;
        currentAudio.playbackRate = playbackRate.value;
    }
}

function setVolume(value: number) {
    const clamped = Math.min(1, Math.max(0, value));
    volume.value = clamped;
    if (clamped > 0) {
        isMuted.value = false;
    }
    if (currentAudio) {
        currentAudio.volume = clamped;
        currentAudio.muted = isMuted.value;
    }
}

function toggleMute() {
    isMuted.value = !isMuted.value;
    if (currentAudio) {
        currentAudio.muted = isMuted.value;
    }
}

function toggleLoop() {
    isLooping.value = !isLooping.value;
}

function seekTo(seconds: number): void {
    if (!currentAudio || !currentKey) return;

    const audio = currentAudio;
    const key = currentKey;
    const target = clampSeekTarget(audio, seconds);

    if (audio.readyState < 1) {
        pendingSeek = { audio, key, seconds: target };
        currentTime.value = target;
        return;
    }

    try {
        audio.currentTime = target;
        pendingSeek = null;
        syncAudioTime(audio, key);
    } catch {
        pendingSeek = { audio, key, seconds: target };
        currentTime.value = target;
    }
}

function seekBy(delta: number): void {
    if (!currentAudio) return;

    const base = Number.isFinite(currentAudio.currentTime) ? currentAudio.currentTime : currentTime.value;
    seekTo(base + delta);
}

function setSpeed(rate: number): void {
    const normalized = Number.isFinite(rate) && rate > 0 ? rate : 1;
    playbackRate.value = normalized;
    if (currentAudio) {
        currentAudio.defaultPlaybackRate = normalized;
        currentAudio.playbackRate = normalized;
    }
}

const isActive = computed(() => activeKey.value !== null);

const formattedCurrentTime = computed(() => formatTime(currentTime.value));
const formattedDuration = computed(() => formatTime(duration.value));

function formatTime(seconds: number): string {
    if (!seconds || !isFinite(seconds)) return '0:00';
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);
    return `${m}:${s.toString().padStart(2, '0')}`;
}

type UseTextToSpeechOptions = {
    installAutoplayGestureListener?: boolean;
};

export function useTextToSpeech(options: UseTextToSpeechOptions = {}) {
    const releaseAutoplayGestureListener = options.installAutoplayGestureListener !== false ? installAutoplayGestureListener() : null;

    onUnmounted(() => {
        releaseAutoplayGestureListener?.();
        stopTimeUpdates();
    });

    return {
        isPlaying,
        isLoading,
        isActive,
        currentTime,
        duration,
        playbackRate,
        volume,
        isMuted,
        isLooping,
        formattedCurrentTime,
        formattedDuration,
        activeKey,
        mediaCollapsed,
        revealMediaPlayer,
        collapseMediaPlayer,
        play,
        autoplay,
        prefetch,
        stop,
        dismiss,
        resetForNewStory,
        pause,
        resume,
        toggle,
        togglePause,
        cycleSpeed,
        setSpeed,
        setVolume,
        toggleMute,
        toggleLoop,
        seekTo,
        seekBy,
        primeAudio,
        hasPlayed: (gameId: string, promptId: string) => playedKeys.has(`${gameId}:${promptId}`),
    };
}
