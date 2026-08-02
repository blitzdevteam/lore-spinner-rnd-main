import { computed, onUnmounted, ref } from 'vue';

const SPEED_OPTIONS = [1, 1.25, 1.5, 1.75, 2] as const;

// Minimal silent WAV — played once during a user gesture to unlock mobile autoplay.
const SILENT_WAV =
    'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA=';

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
let pendingAutoplay: { gameId: string; promptId: string } | null = null;

// iOS Safari only allows HTMLAudioElement.play() on elements that were previously
// activated during a user gesture — even if the AudioContext is already running.
// We pre-claim a fresh element on every user gesture so the subsequent async
// autoplay call can reuse it (src-swap preserves iOS activation).
let primedAudio: HTMLAudioElement | null = null;

// Key whose element is claimed but has no real source yet, because the mp3 is
// still downloading. Calling play() on it would fail with NotSupportedError.
let awaitingSourceKey: string | null = null;

// Identifies the newest playback request. Anything that silences audio bumps it,
// so a download that lands afterwards knows not to start on its own.
let playToken = 0;

// ── Web Audio API unlock ──────────────────────────────────────────────────────
// On iOS Safari, audio playback permission is per-page once ANY audio element
// plays during a user gesture. Routing all HTMLAudioElements through a shared
// AudioContext is the most reliable cross-version strategy:
//   • The AudioContext is unlocked (resumed) during a user gesture.
//   • All elements connected via createMediaElementSource inherit that unlock,
//     so async play() calls (e.g. from watches after a POST) succeed.
//   • If AudioContext is still suspended we proactively queue instead of letting
//     audio play silently (suspended context → play() resolves but no sound).

let audioCtx: AudioContext | null = null;
const ctxConnected = new WeakSet<HTMLAudioElement>();

function getOrCreateAudioCtx(): AudioContext | null {
    if (typeof window === 'undefined') return null;
    try {
        const ACtx = window.AudioContext ?? (window as any).webkitAudioContext;
        if (!ACtx) return null;
        if (!audioCtx) {
            audioCtx = new ACtx() as AudioContext;
            // When AudioContext resumes after a gesture (async resume() resolved),
            // flush any play that was queued while it was still suspended.
            audioCtx.addEventListener('statechange', () => {
                if (audioCtx?.state === 'running' && audioUnlocked) {
                    flushPendingAutoplay();
                }
            });
        }
        return audioCtx;
    } catch {
        return null;
    }
}

/**
 * Connect an HTMLAudioElement to the shared AudioContext graph.
 * Called once per element; repeated calls are no-ops.
 * After connection the element's audio ONLY flows through the AudioContext —
 * meaning it will be silent while the context is suspended and audible once running.
 */
function connectToAudioCtx(audio: HTMLAudioElement): void {
    if (ctxConnected.has(audio)) return;
    const ctx = getOrCreateAudioCtx();
    if (!ctx) return;
    try {
        const source = ctx.createMediaElementSource(audio);
        source.connect(ctx.destination);
        ctxConnected.add(audio);
    } catch {
        // Already connected or unsupported — fall through to browser-default behaviour
    }
}

/**
 * Must be called synchronously inside a user-gesture handler.
 * Resumes the AudioContext and plays 1 frame of silence to fully activate it on iOS.
 */
function unlockAudioCtx(): void {
    const ctx = getOrCreateAudioCtx();
    if (!ctx) return;
    const playOneSample = () => {
        try {
            const buf = ctx.createBuffer(1, 1, ctx.sampleRate);
            const src = ctx.createBufferSource();
            src.buffer = buf;
            src.connect(ctx.destination);
            src.start(0);
        } catch {}
    };
    if (ctx.state === 'suspended') {
        ctx.resume().then(playOneSample).catch(() => {});
    } else {
        playOneSample();
    }
}

function configureAudioElement(audio: HTMLAudioElement) {
    audio.setAttribute('playsinline', 'true');
    // Route through AudioContext so the gesture-unlock propagates to this element.
    // crossOrigin is intentionally omitted: same-origin TTS endpoint; adding it
    // requires CORS headers on the server and breaks playback on iOS without them.
    connectToAudioCtx(audio);
}

function isAutoplayBlocked(err: unknown): boolean {
    return err instanceof DOMException && err.name === 'NotAllowedError';
}

/**
 * Only one narration may ever be audible. Every cached turn keeps its own
 * element, so a previous turn can still be mid-playback when a new one takes
 * over — pausing `currentAudio` alone would leave it running.
 */
function silenceOthers(keep: HTMLAudioElement | null) {
    const rewind = (audio: HTMLAudioElement) => {
        if (!audio.paused) audio.pause();
        if (audio.currentTime) audio.currentTime = 0;
    };

    for (const audio of audioCache.values()) {
        if (audio !== keep) rewind(audio);
    }

    if (currentAudio && currentAudio !== keep) rewind(currentAudio);
}

function queuePendingAutoplay(gameId: string, promptId: string) {
    // The UI is about to point at this turn, so nothing from an earlier one may
    // keep playing underneath it. A retry of the current turn keeps its position.
    // Ordered before the queue is recorded because stop() clears the queue.
    const key = `${gameId}:${promptId}`;
    if (currentKey !== key) {
        stop();
        currentAudio = null;
        currentKey = null;
    }

    pendingAutoplay = { gameId, promptId };

    // Show a pending-play indicator on the relevant card while we wait for the
    // AudioContext to be unlocked via a user gesture.
    revealMediaPlayer();
    activeKey.value = key;
    isLoading.value = true;
}

function flushPendingAutoplay() {
    if (!pendingAutoplay || !audioUnlocked) return;
    const { gameId, promptId } = pendingAutoplay;
    pendingAutoplay = null;
    play(gameId, promptId);
}

/** Call synchronously from a user-gesture handler (tap/click) to unlock mobile audio. */
function primeAudio() {
    if (typeof window === 'undefined') return;

    // 1. Resume the AudioContext — this is the primary iOS unlock signal.
    unlockAudioCtx();

    if (!audioUnlocked) {
        // 2. Also play a silent HTML Audio element as a belt-and-suspenders unlock
        //    for browsers that gate on HTMLAudioElement rather than AudioContext.
        if (!unlockAudio) {
            unlockAudio = new Audio(SILENT_WAV);
            unlockAudio.volume = 0;
            configureAudioElement(unlockAudio);
        }
        unlockAudio.play().catch(() => {});

        // 3. Mark unlocked immediately — we ARE inside a user-gesture call stack.
        //    Deferring to .then() risks losing gesture propagation on iOS Safari.
        audioUnlocked = true;
    }

    // 4. Pre-claim a fresh HTMLAudioElement within this gesture context.
    //    iOS Safari permits async .play() on the SAME instance later (even after
    //    src is changed), because the activation is tied to the element object.
    //    A new element created outside a gesture is always rejected on iOS.
    primedAudio = new Audio(SILENT_WAV);
    primedAudio.volume = 0;
    configureAudioElement(primedAudio);
    primedAudio.play().catch(() => {});

    // 5. Flush any queued play() synchronously while still inside gesture scope.
    flushPendingAutoplay();
}

function handlePlayRejected(key: string, err: unknown, gameId: string, promptId: string) {
    console.warn('[TTS] play() rejected', key, err);
    isPlaying.value = false;
    if (isAutoplayBlocked(err)) {
        // Queue the play — queuePendingAutoplay keeps isLoading = true so the
        // spinner stays visible while we wait for a user gesture to unlock audio.
        queuePendingAutoplay(gameId, promptId);
    } else {
        isLoading.value = false;
    }
}

function updateTime() {
    if (currentAudio) {
        currentTime.value = currentAudio.currentTime;
        duration.value = currentAudio.duration || 0;
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

function attachListeners(audio: HTMLAudioElement, key: string) {
    // Guard: only attach once per element so cached replays don't stack handlers.
    if ((audio as any).__ttsListenersAttached) return;
    (audio as any).__ttsListenersAttached = true;

    // Handlers write shared state, so an element from an earlier turn being
    // silenced must not report over the turn that replaced it.
    const isCurrent = () => currentKey === key;

    audio.addEventListener('canplay', () => {
        if (!isCurrent()) return;
        isLoading.value = false;
        duration.value = audio.duration || 0;
        console.debug('[TTS] canplay', key, 'duration', duration.value);
    });
    audio.addEventListener('playing', () => {
        if (!isCurrent()) {
            audio.pause(); // a stale turn must never be audible
            return;
        }
        isPlaying.value = true;
        isLoading.value = false;
        duration.value = audio.duration || 0;
        playedKeys.add(key);
        console.debug('[TTS] playing', key);
        updateTime();
    });
    audio.addEventListener('ended', () => {
        if (!isCurrent()) return;
        if (isLooping.value && currentAudio) {
            currentAudio.currentTime = 0;
            currentAudio.play().catch(() => {
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
        duration.value = audio.duration || 0;
        console.debug('[TTS] loadedmetadata', key, 'duration', duration.value);
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
            throw new Error(`narration request failed (${response.status})`);
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
    audio.playbackRate = playbackRate.value;
    audio.volume = volume.value;
    audio.muted = isMuted.value;
    audio.loop = false; // looping handled manually in the `ended` listener
}

function play(gameId: string, promptId: string) {
    const key = `${gameId}:${promptId}`;

    // Guard: AudioContext exists but is still suspended — audio would play silently
    // (context suppresses output). Queue and show the spinner; the statechange
    // listener will flush once the context resumes during a user gesture.
    if (audioCtx && audioCtx.state !== 'running') {
        queuePendingAutoplay(gameId, promptId); // sets activeKey + isLoading for the UI
        return;
    }

    if (currentAudio && currentKey === key && !currentAudio.ended) {
        if (awaitingSourceKey === key) return; // download in flight, spinner already up
        if (currentAudio.paused) {
            silenceOthers(currentAudio);
            isLoading.value = true;
            revealMediaPlayer();
            currentAudio.play().catch((err) => {
                handlePlayRejected(key, err, gameId, promptId);
            });
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
        currentAudio.currentTime = 0;
        applyAudioSettings(currentAudio);
        isLoading.value = true;
        currentAudio.play().catch((err) => {
            handlePlayRejected(key, err, gameId, promptId);
        });
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
    const token = ++playToken;

    narrationUrl(gameId, promptId)
        .then((url) => {
            if (currentKey !== key) return; // the player moved on while it rendered

            // Exactly one src assignment on this element, and listeners go on
            // straight after it, so the silent unlock clip it may still be
            // holding cannot report events against this narration.
            audio.src = url;
            audio.load();
            attachListeners(audio, key);
            audioCache.set(key, audio);
            awaitingSourceKey = null;

            // Paused or stopped while this downloaded — hand over the loaded
            // element but leave it silent.
            if (token !== playToken) {
                isLoading.value = false;
                return;
            }

            // A turn that was already playing can have run all the way to this
            // point while the new one downloaded.
            silenceOthers(audio);

            return audio.play().catch((err) => {
                handlePlayRejected(key, err, gameId, promptId);
            });
        })
        .catch((err) => {
            console.warn('[TTS] unavailable', key, err);
            if (currentKey === key) {
                isLoading.value = false;
            }
        })
        .finally(() => {
            if (awaitingSourceKey === key) {
                awaitingSourceKey = null;
            }
        });
}

function stop() {
    // A queued turn left behind here would be replayed by the next unrelated
    // gesture, since every gesture primes audio and primeAudio() flushes.
    pendingAutoplay = null;
    playToken++;
    silenceOthers(null); // rewinds every element, current one included
    isPlaying.value = false;
    isLoading.value = false;
    currentTime.value = 0;
    stopTimeUpdates();
}

function dismiss() {
    pendingAutoplay = null;
    awaitingSourceKey = null;
    stop();
    currentKey = null;
    activeKey.value = null;
}

/** Stop playback and discard cached narrations (e.g. after resetting the story). */
function resetForNewStory() {
    dismiss();
    currentAudio = null;
    currentKey = null;
    for (const audio of audioCache.values()) {
        audio.pause();
        audio.currentTime = 0;
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
    // Pausing means silence: drop any queued turn and cancel a download that
    // would otherwise start playing the moment it lands.
    pendingAutoplay = null;
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
        currentAudio.play().catch((err) => {
            const key = currentKey ?? activeKey.value ?? 'unknown';
            const [gameId = '', promptId = ''] = key.split(':');
            handlePlayRejected(key, err, gameId, promptId);
        });
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

function seekTo(seconds: number) {
    if (!currentAudio) return;
    const max = currentAudio.duration || duration.value || 0;
    const clamped = Math.min(max, Math.max(0, seconds));
    currentAudio.currentTime = clamped;
    currentTime.value = clamped;
}

function seekBy(delta: number) {
    if (!currentAudio) return;
    seekTo(currentAudio.currentTime + delta);
}

function setSpeed(rate: number) {
    playbackRate.value = rate;
    if (currentAudio) {
        currentAudio.playbackRate = rate;
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

export function useTextToSpeech() {
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
