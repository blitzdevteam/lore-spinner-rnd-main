<script setup lang="ts">
import spinnerImg from '@/assets/intro/spinner.jpg';
import { useTextToSpeech } from '@/composables/useTextToSpeech';
import { onMounted, onUnmounted, ref, watch } from 'vue';

const tts = useTextToSpeech();

const props = defineProps<{
    /** Parent sets true once the /begin call has settled — hold showcard until then. */
    canExit?: boolean;
}>();

const emit = defineEmits<{
    // Fired when the showcard appears. The parent normally starts the game API call on mount;
    // this is the fallback so the card is never shown without a request in flight.
    prepare: [];
    // Fired when the full sequence has faded out → parent should unmount this component
    done: [];
}>();

const showHeadphones = ref(false);
const showStory      = ref(false);
const showShape      = ref(false);
const showCard       = ref(false);
const atCardPhase    = ref(false);
const fadingOut      = ref(false);
const minDwellReached = ref(false);
const exitStarted    = ref(false);
const skipped        = ref(false);

const SHOWCARD_MIN_MS = 5200;
/** A skip is a request to play, not to admire the card — hold only long enough to read it. */
const SHOWCARD_SKIP_MS = 700;
const FADE_OUT_MS = 1100;
const FADE_OUT_SKIP_MS = 400;

const timers: ReturnType<typeof setTimeout>[] = [];
let prepareEmitted = false;
let doneEmitted    = false;

function schedule(fn: () => void, ms: number) {
    timers.push(setTimeout(fn, ms));
}

function emitPrepare() {
    if (prepareEmitted) return;
    prepareEmitted = true;
    emit('prepare');
}

function emitDone() {
    if (doneEmitted) return;
    doneEmitted = true;
    emit('done');
}

// ── Preload showcard so the first appearance is never a rough decode ──
const cardReady = ref(false);

function preloadCard() {
    const img = new window.Image();
    img.onload = img.onerror = () => { cardReady.value = true; };
    img.src = '/images/opening/showcard.webp';

    const spinner = new window.Image();
    spinner.src = spinnerImg;
}

// Poll until image is decoded, then reveal
function revealCardWhenReady() {
    if (cardReady.value) {
        doRevealCard();
    } else {
        timers.push(setTimeout(revealCardWhenReady, 80));
    }
}

function tryBeginFadeOut() {
    if (exitStarted.value || !atCardPhase.value) return;
    if (!minDwellReached.value || !props.canExit) return;
    exitStarted.value = true;
    beginFadeOut();
}

function doRevealCard() {
    atCardPhase.value = true;
    showCard.value    = true;
    emitPrepare();

    schedule(() => {
        minDwellReached.value = true;
        tryBeginFadeOut();
    }, skipped.value ? SHOWCARD_SKIP_MS : SHOWCARD_MIN_MS);
}

function beginFadeOut() {
    fadingOut.value = true;
    schedule(() => emitDone(), skipped.value ? FADE_OUT_SKIP_MS : FADE_OUT_MS);
}

function primeAudioFromGesture() {
    tts.primeAudio();
}

// Click/tap: skip text phases, jump straight to showcard
function skipToCard() {
    primeAudioFromGesture();
    skipped.value = true;

    // A tap once the card is already up drops the remaining dwell. The exit still
    // waits on `canExit`, because there is no game to show until the opening lands.
    if (atCardPhase.value) {
        minDwellReached.value = true;
        tryBeginFadeOut();
        return;
    }

    timers.forEach(clearTimeout);
    timers.length = 0;
    showHeadphones.value = false;
    showStory.value      = false;
    showShape.value      = false;
    // Tiny pause so current opacity transitions settle cleanly
    timers.push(setTimeout(() => revealCardWhenReady(), 150));
}

watch(
    () => props.canExit,
    (ready) => {
        if (ready) tryBeginFadeOut();
    },
);

onMounted(() => {
    preloadCard(); // start loading immediately, in parallel with text phases

    // ── Phase 1: "Headphones Recommended."
    schedule(() => { showHeadphones.value = true;  },  400);
    schedule(() => { showHeadphones.value = false; }, 3800);

    // ── Phase 2: "Let Me Tell You a Story…" (lingers longest)
    schedule(() => { showStory.value = true;  }, 5300);
    schedule(() => { showStory.value = false; }, 9700);

    // ── Phase 3: "You Help Shape What Happens Next."
    schedule(() => { showShape.value = true;  }, 11100);
    schedule(() => { showShape.value = false; }, 14500);

    // ── Phase 4: showcard
    schedule(() => revealCardWhenReady(), 15600);
});

onUnmounted(() => {
    timers.forEach(clearTimeout);
});
</script>

<template>
    <div
        class="co-root"
        :class="{ 'co-root--out': fadingOut, 'co-root--fast': skipped }"
        tabindex="0"
        aria-label="Story opening sequence. Tap to skip."
        @click="skipToCard"
        @pointerdown="primeAudioFromGesture"
        @keydown.space.prevent="skipToCard"
        @keydown.enter.prevent="skipToCard"
    >
        <!-- ── Text phases ── -->
        <Transition name="co-phrase">
            <p v-if="showHeadphones" key="h" class="co-phrase co-phrase--small">
                Headphones Recommended.
            </p>
        </Transition>

        <Transition name="co-phrase">
            <div v-if="showStory" key="s" class="co-story-phase">
                <img
                    :src="spinnerImg"
                    alt=""
                    class="co-spinner"
                    draggable="false"
                    aria-hidden="true"
                />
                <p class="co-story-phase__text">
                    Let Me Tell You a Story&hellip;
                </p>
            </div>
        </Transition>

        <Transition name="co-phrase">
            <p v-if="showShape" key="sh" class="co-phrase">
                You Help Shape What Happens Next.
            </p>
        </Transition>

        <!-- ── Showcard — full viewport, pure fade ── -->
        <Transition :name="skipped ? 'co-card-fast' : 'co-card'">
            <div v-if="showCard" class="co-card-wrap">
                <img
                    src="/images/opening/showcard.webp"
                    alt="LoreSpinner"
                    class="co-card-img"
                    draggable="false"
                    fetchpriority="high"
                />
            </div>
        </Transition>

        <!-- ── Skip hint ── -->
        <Transition name="co-hint">
            <p v-if="!atCardPhase" class="co-skip-hint">tap to skip</p>
        </Transition>
    </div>
</template>

<style scoped>
/* ── Root ─────────────────────────────────────────────────── */
.co-root {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: #000;
    display: flex;
    align-items: center;
    justify-content: center;
    outline: none;
    cursor: default;
    /* Fade-out of the entire screen back to black before handing off to game */
    transition: opacity 1s ease;
}

.co-root--fast {
    transition: opacity 0.4s ease;
}

.co-root--out {
    opacity: 0;
    pointer-events: none;
}

/* Subtle film grain — makes the black feel like a darkened cinema, not a dead screen */
.co-root::before {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    z-index: 50;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.72' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
    background-size: 160px;
    opacity: 0.35;
}

/* ── Text phrases ─────────────────────────────────────────── */
.co-phrase {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 clamp(2rem, 10vw, 6rem);
    text-align: center;
    font-family: 'Marcellus SC', serif;
    font-weight: 400;
    font-size: clamp(1.6rem, 3.8vw, 3.4rem);
    line-height: 1.25;
    letter-spacing: 0.04em;
    color: rgba(250, 246, 238, 0.9);
    text-shadow: 0 0 80px rgba(250, 246, 238, 0.08);
    z-index: 10;
    pointer-events: none;
}

/* "Let Me Tell You a Story…" + spinner mascot */
.co-story-phase {
    position: absolute;
    inset: 0;
    z-index: 10;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: clamp(1.25rem, 3.5vw, 2.25rem);
    padding: 0 clamp(2rem, 10vw, 6rem);
    pointer-events: none;
}

.co-spinner {
    width: clamp(11rem, 34vw, 20rem);
    height: auto;
    object-fit: contain;
}

.co-story-phase__text {
    margin: 0;
    text-align: center;
    font-family: 'Marcellus SC', serif;
    font-weight: 400;
    font-size: clamp(1.6rem, 3.8vw, 3.4rem);
    line-height: 1.25;
    letter-spacing: 0.04em;
    color: rgba(250, 246, 238, 0.9);
    text-shadow: 0 0 80px rgba(250, 246, 238, 0.08);
}

/* "Headphones Recommended." — whisper before the curtain */
.co-phrase--small {
    font-size: clamp(0.7rem, 1.4vw, 1rem);
    letter-spacing: 0.28em;
    color: rgba(250, 246, 238, 0.35);
    text-shadow: none;
}

/* ── Showcard ─────────────────────────────────────────────── */
.co-card-wrap {
    position: absolute;
    inset: 0;
    z-index: 20;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Fill the full viewport; letterbox on non-16:9 screens (seamless on black) */
.co-card-img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    object-position: center;
    display: block;
    user-select: none;
    pointer-events: none;
}

/* ── Skip hint ────────────────────────────────────────────── */
.co-skip-hint {
    position: absolute;
    bottom: 2.5rem;
    left: 50%;
    transform: translateX(-50%);
    font-family: 'Marcellus SC', serif;
    font-size: 0.75rem;
    font-weight: 700;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: #fff;
    text-shadow: 0 1px 10px rgba(0, 0, 0, 0.85);
    z-index: 60;
    pointer-events: none;
    white-space: nowrap;
}

/* ── Transitions ──────────────────────────────────────────── */

/* Text: pure dissolve — no movement, just light */
.co-phrase-enter-active { transition: opacity 1.2s ease; }
.co-phrase-leave-active { transition: opacity 1.0s ease; }
.co-phrase-enter-from   { opacity: 0; }
.co-phrase-enter-to     { opacity: 1; }
.co-phrase-leave-from   { opacity: 1; }
.co-phrase-leave-to     { opacity: 0; }

/* Showcard: clean dissolve in — image already has its own background */
.co-card-enter-active { transition: opacity 2s cubic-bezier(0.4, 0, 0.2, 1); }
.co-card-leave-active { transition: opacity 0.6s ease; }
.co-card-enter-from   { opacity: 0; }
.co-card-enter-to     { opacity: 1; }
.co-card-leave-from   { opacity: 1; }
.co-card-leave-to     { opacity: 0; }

/* Showcard after a skip: same dissolve, but the player asked to move on */
.co-card-fast-enter-active { transition: opacity 0.45s ease; }
.co-card-fast-leave-active { transition: opacity 0.3s ease; }
.co-card-fast-enter-from   { opacity: 0; }
.co-card-fast-enter-to     { opacity: 1; }
.co-card-fast-leave-from   { opacity: 1; }
.co-card-fast-leave-to     { opacity: 0; }

/* Skip hint */
.co-hint-enter-active { transition: opacity 0.8s ease 2s; }
.co-hint-leave-active { transition: opacity 0.4s ease; }
.co-hint-enter-from   { opacity: 0; }
.co-hint-enter-to     { opacity: 1; }
.co-hint-leave-from   { opacity: 1; }
.co-hint-leave-to     { opacity: 0; }
</style>
