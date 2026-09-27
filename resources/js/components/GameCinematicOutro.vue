<script setup lang="ts">
import spinnerImg from '@/assets/intro/spinner.jpg';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps<{
    /**  URL returned by StoryResource outro_poster — null means use the generic fallback. */
    outroPoster: string | null;
}>();

const emit = defineEmits<{
    // Fired when the full sequence has faded out → parent should unmount this component
    done: [];
}>();

const posterSrc = computed(() => props.outroPoster ?? '/images/opening/showcard.webp');

const showChoices = ref(false);
const showCard = ref(false);
const atCardPhase = ref(false);
const fadingOut = ref(false);

const timers: ReturnType<typeof setTimeout>[] = [];
let doneEmitted = false;

function schedule(fn: () => void, ms: number) {
    timers.push(setTimeout(fn, ms));
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
    img.src = posterSrc.value;

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

function doRevealCard() {
    atCardPhase.value = true;
    showCard.value = true;

    // After 5.2s of showcard visible, fade the whole screen to black then signal done
    schedule(() => beginFadeOut(), 5200);
}

function beginFadeOut() {
    fadingOut.value = true;
    schedule(() => emitDone(), 1100); // wait for fade-out CSS transition
}

// Click/tap: skip text phase, jump straight to showcard
function skipToCard() {
    if (atCardPhase.value) return;
    timers.forEach(clearTimeout);
    timers.length = 0;
    showChoices.value = false;
    timers.push(setTimeout(() => revealCardWhenReady(), 250));
}

onMounted(() => {
    preloadCard();

    schedule(() => { showChoices.value = true; }, 400);
    schedule(() => { showChoices.value = false; }, 4800);
    schedule(() => revealCardWhenReady(), 6200);
});

onUnmounted(() => {
    timers.forEach(clearTimeout);
});
</script>

<template>
    <div
        class="co-root"
        :class="{ 'co-root--out': fadingOut }"
        tabindex="0"
        aria-label="Story ending sequence. Tap to skip."
        @click="skipToCard"
        @keydown.space.prevent="skipToCard"
        @keydown.enter.prevent="skipToCard"
    >
        <Transition name="co-phrase">
            <div v-if="showChoices" key="c" class="co-story-phase">
                <img
                    :src="spinnerImg"
                    alt=""
                    class="co-spinner"
                    draggable="false"
                    aria-hidden="true"
                />
                <p class="co-story-phase__text">
                    Every story leaves something behind.
                </p>
            </div>
        </Transition>

        <Transition name="co-card">
            <div v-if="showCard" class="co-card-wrap">
                <img
                    :src="posterSrc"
                    alt="Story complete"
                    class="co-card-img"
                    draggable="false"
                    fetchpriority="high"
                />
            </div>
        </Transition>

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
    transition: opacity 1s ease;
}

.co-root--out {
    opacity: 0;
    pointer-events: none;
}

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

.co-card-wrap {
    position: absolute;
    inset: 0;
    z-index: 20;
    display: flex;
    align-items: center;
    justify-content: center;
}

.co-card-img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    object-position: center;
    display: block;
    user-select: none;
    pointer-events: none;
}

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

.co-phrase-enter-active { transition: opacity 1.2s ease; }
.co-phrase-leave-active { transition: opacity 1.0s ease; }
.co-phrase-enter-from   { opacity: 0; }
.co-phrase-enter-to     { opacity: 1; }
.co-phrase-leave-from   { opacity: 1; }
.co-phrase-leave-to     { opacity: 0; }

.co-card-enter-active { transition: opacity 2s cubic-bezier(0.4, 0, 0.2, 1); }
.co-card-leave-active { transition: opacity 0.6s ease; }
.co-card-enter-from   { opacity: 0; }
.co-card-enter-to     { opacity: 1; }
.co-card-leave-from   { opacity: 1; }
.co-card-leave-to     { opacity: 0; }

.co-hint-enter-active { transition: opacity 0.8s ease 2s; }
.co-hint-leave-active { transition: opacity 0.4s ease; }
.co-hint-enter-from   { opacity: 0; }
.co-hint-enter-to     { opacity: 1; }
.co-hint-leave-from   { opacity: 1; }
.co-hint-leave-to     { opacity: 0; }
</style>
