<script setup lang="ts">
import BaseButton from '@/components/BaseButton.vue';
import { useSpeechToText } from '@/composables/useSpeechToText';
import { LucideArrowUp, LucideLoader, LucideMic, LucideSquare } from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        disabled?: boolean;
        compact?: boolean;
        /** 'sweep' = idle left↔right loop, 'orbit' = clockwork ring while waiting, 'twinkle' = one-shot nudge pulse */
        glowVariant?: 'sweep' | 'orbit' | 'twinkle';
        /**
         * Optional placeholder text. When provided (cold-open first move), it dims
         * on focus and vanishes when the user types — native browser behaviour.
         * Falls back to '' so the bar stays clean after the first move.
         */
        placeholder?: string;
    }>(),
    { disabled: false, compact: false, placeholder: '' },
);

const emit = defineEmits<{
    submit: [prompt: string];
    /** User focused the bar or started typing — parent may show the sweep glow. */
    readyToType: [];
    /** The draft changed, so multiplayer surfaces can share an ephemeral typing state. */
    typing: [isTyping: boolean];
    focus: [];
    blur: [];
}>();

const inputText = ref('');
const inputEl = ref<{ $el?: HTMLElement } | null>(null);
/** One ready-to-type signal per turn (resets when input is disabled for AI). */
const readySignaled = ref(false);
const stt = useSpeechToText();

const hasText = computed(() => inputText.value.trim().length > 0);

watch(inputText, (text) => {
    emit('typing', text.trim().length > 0);
});

const handleSubmit = () => {
    if (props.disabled) return;
    const text = inputText.value.trim();
    if (!text) return;
    emit('submit', text);
    inputText.value = '';
};

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        handleSubmit();
    }
};

function focusInput() {
    const el = inputEl.value?.$el ?? inputEl.value;
    const input = el instanceof HTMLElement ? el.querySelector('input') : null;
    (input as HTMLInputElement | null)?.focus();
}

function signalReadyToType() {
    if (props.disabled || readySignaled.value) return;
    readySignaled.value = true;
    emit('readyToType');
}

function handleBlur(): void {
    emit('blur');
    emit('typing', false);
}

watch(
    () => props.disabled,
    (disabled) => {
        if (disabled) {
            readySignaled.value = false;
            emit('typing', false);
        }
    },
);

const handleMicToggle = async () => {
    if (stt.isRecording.value) {
        const text = await stt.stopRecording();
        if (text) {
            inputText.value = text;
        }
    } else {
        await stt.startRecording();
    }
};

// ── Smooth crossfade when glow variant switches ───────────────────────────────
const displayedVariant = ref(props.glowVariant);
const isFading = ref(false);
let fadeTimer: ReturnType<typeof setTimeout> | null = null;

watch(
    () => props.glowVariant,
    (next, prev) => {
        if (fadeTimer) clearTimeout(fadeTimer);

        // Twinkle transitions skip the fade-out dip entirely — the animation's
        // own alpha surge handles the visual entry/exit smoothly. A fade-out
        // here would make the bar disappear on every nudge.
        if (next === 'twinkle' || prev === 'twinkle') {
            isFading.value = false;
            displayedVariant.value = next;
            fadeTimer = null;
            return;
        }

        isFading.value = true;
        // Wait for fade-out, then swap gradient, then fade back in
        fadeTimer = setTimeout(() => {
            displayedVariant.value = next;
            fadeTimer = setTimeout(() => {
                isFading.value = false;
                fadeTimer = null;
            }, 60);
        }, 200);
    },
);

onUnmounted(() => {
    if (fadeTimer) clearTimeout(fadeTimer);
});
</script>

<template>
    <div :class="['relative w-full max-w-3xl', props.disabled && 'pointer-events-none']">
        <!-- Gradient border pill -->
        <div
            :class="[
                'flex items-center rounded-full border border-[#373737] backdrop-blur-lg',
                props.compact ? 'h-14 p-1' : 'h-14 p-2 sm:h-[70px] sm:p-2.5',
                !displayedVariant && 'gp-pill--breathe',
                displayedVariant === 'sweep' && 'gp-pill--sweep',
                displayedVariant === 'orbit' && 'gp-pill--orbit',
                displayedVariant === 'twinkle' && 'gp-pill--twinkle',
                isFading && 'gp-pill--fading',
            ]"
        >
            <!-- Inner dark field -->
            <div
                class="flex h-full min-w-0 flex-1 items-center gap-2 rounded-full border border-[#373737] bg-[#1c1c1c]"
                :class="props.compact ? 'px-1.5' : 'px-0 sm:gap-3 sm:px-1'"
                @click="focusInput"
            >
                <!-- Recording pulse indicator -->
                <span v-if="stt.isRecording.value" class="inline-block size-2 shrink-0 animate-pulse rounded-full bg-red-500" />

                <PrimeInputText
                    ref="inputEl"
                    v-model="inputText"
                    class="min-w-0 flex-1 border-none! bg-transparent! py-0! text-base! text-white! shadow-none! ring-0! outline-none!"
                    :class="props.compact ? 'px-0.5! tracking-wide! placeholder:text-[#808080]! sm:text-sm!' : 'px-2! placeholder:text-gray-500!'"
                    :placeholder="stt.isRecording.value ? 'Listening...' : stt.isTranscribing.value ? 'Transcribing...' : props.placeholder"
                    :disabled="props.disabled || stt.isRecording.value || stt.isTranscribing.value"
                    autocomplete="off"
                    autocorrect="off"
                    autocapitalize="off"
                    spellcheck="false"
                    name="story_input"
                    aria-label="Your action"
                    @keydown="handleKeydown"
                    @focus="
                        signalReadyToType();
                        emit('focus');
                    "
                    @blur="handleBlur"
                    @input="signalReadyToType"
                />

                <!-- Right action: send arrow when text present, mic otherwise -->
                <button
                    v-if="hasText"
                    type="button"
                    class="gp-send-btn grid shrink-0 place-items-center rounded-full"
                    :class="props.compact ? 'size-8' : 'size-9 sm:size-10'"
                    :disabled="props.disabled"
                    title="Send"
                    aria-label="Send action"
                    @click="handleSubmit"
                >
                    <LucideArrowUp class="size-4 sm:size-5" :stroke-width="2.75" />
                </button>
                <button
                    v-else-if="props.compact"
                    type="button"
                    class="grid size-8 shrink-0 place-items-center rounded-full bg-[#939393] text-[#303030] transition-colors hover:bg-gray-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-300 disabled:cursor-wait disabled:opacity-60"
                    :disabled="props.disabled || stt.isTranscribing.value"
                    :aria-label="stt.isTranscribing.value ? 'Transcribing audio' : stt.isRecording.value ? 'Stop recording' : 'Record your action'"
                    @click="handleMicToggle"
                >
                    <LucideLoader v-if="stt.isTranscribing.value" class="size-4 animate-spin" />
                    <LucideSquare v-else-if="stt.isRecording.value" class="size-3.5 fill-current" />
                    <LucideMic v-else class="size-4.5" />
                </button>
                <BaseButton v-else severity="glass" :icon-only="true" class="size-9! shrink-0 sm:size-10!" type="button" @click="handleMicToggle">
                    <LucideLoader v-if="stt.isTranscribing.value" class="size-5 animate-spin text-white" />
                    <LucideSquare v-else-if="stt.isRecording.value" fill="white" class="size-3.5 text-white" />
                    <LucideMic v-else class="size-5 text-gray-300" />
                </BaseButton>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* ── Cold-open placeholder fade — dims on focus, gone when typing (native) ── */
:deep(input)::placeholder {
    transition: opacity 0.3s ease;
}

:deep(input:focus)::placeholder {
    opacity: 0.3;
}

.gp-send-btn {
    color: #0a1a1c;
    background: linear-gradient(180deg, #8fcbd3 0%, #6fafba 100%);
    border: 1px solid rgba(143, 203, 211, 0.65);
    box-shadow:
        0 0 0 1px rgba(84, 244, 218, 0.25),
        0 4px 16px rgba(84, 244, 218, 0.35),
        inset 0 1px 0 rgba(255, 255, 255, 0.35);
    transition:
        transform 0.15s ease,
        box-shadow 0.15s ease,
        background 0.15s ease;
}

.gp-send-btn:hover:not(:disabled) {
    background: linear-gradient(180deg, #a8d8de 0%, #7eb8c4 100%);
    box-shadow:
        0 0 0 1px rgba(84, 244, 218, 0.45),
        0 6px 20px rgba(84, 244, 218, 0.45),
        inset 0 1px 0 rgba(255, 255, 255, 0.45);
}

.gp-send-btn:active:not(:disabled) {
    transform: scale(0.94);
}

.gp-send-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

/* ── Animated pill glow (registered custom props for GPU-smooth animation) ─── */
@property --gp-glow-pos {
    syntax: '<percentage>';
    initial-value: -5%;
    inherits: false;
}

@property --gp-glow-angle {
    syntax: '<angle>';
    initial-value: 0deg;
    inherits: false;
}

@property --gp-breathe-alpha {
    syntax: '<number>';
    initial-value: 0.28;
    inherits: false;
}

/* Shared opacity transition so state switches feel smooth */
.gp-pill--breathe,
.gp-pill--sweep,
.gp-pill--orbit,
.gp-pill--twinkle {
    transition: opacity 0.2s ease;
}

/* Resting state: left-edge teal with a slow, gentle heartbeat */
.gp-pill--breathe {
    background: linear-gradient(
        90deg,
        rgba(0, 198, 222, var(--gp-breathe-alpha)) 0%,
        rgba(13, 112, 124, var(--gp-breathe-alpha)) 10.577%,
        rgba(26, 26, 26, 0.14) 21.154%
    );
    animation: gp-glow-breathe 3.8s ease-in-out infinite;
}

@keyframes gp-glow-breathe {
    0%,
    100% {
        --gp-breathe-alpha: 0.22;
    }
    50% {
        --gp-breathe-alpha: 0.48;
    }
}

.gp-pill--fading {
    opacity: 0.18;
}

/* Idle: the teal band sweeps left → right → left smoothly */
.gp-pill--sweep {
    background: linear-gradient(
        90deg,
        transparent calc(var(--gp-glow-pos) - 12%),
        rgba(0, 198, 222, 0.48) var(--gp-glow-pos),
        rgba(13, 112, 124, 0.44) calc(var(--gp-glow-pos) + 10%),
        rgba(26, 26, 26, 0.18) calc(var(--gp-glow-pos) + 22%),
        transparent calc(var(--gp-glow-pos) + 32%)
    );
    animation: gp-glow-sweep 5.5s ease-in-out infinite;
}

@keyframes gp-glow-sweep {
    0%,
    100% {
        --gp-glow-pos: -5%;
    }
    50% {
        --gp-glow-pos: 82%;
    }
}

/* Nudge: full-width glow pulse — same teal as breathe but spread across the whole bar.
   Animates alpha only (never opacity), so the pill border stays visible at all times.
   Two pulses: bright → dim → bright → back to breathe level. */
.gp-pill--twinkle {
    background: linear-gradient(
        90deg,
        rgba(0, 198, 222, var(--gp-breathe-alpha)) 0%,
        rgba(13, 112, 124, var(--gp-breathe-alpha)) 30%,
        rgba(0, 198, 222, var(--gp-breathe-alpha)) 60%,
        rgba(13, 112, 124, var(--gp-breathe-alpha)) 80%,
        rgba(0, 198, 222, var(--gp-breathe-alpha)) 100%
    );
    animation: gp-glow-twinkle-pulse 1.4s ease-in-out 2;
}

@keyframes gp-glow-twinkle-pulse {
    0% {
        --gp-breathe-alpha: 0.22;
    }
    35% {
        --gp-breathe-alpha: 0.78;
    }
    65% {
        --gp-breathe-alpha: 0.78;
    }
    100% {
        --gp-breathe-alpha: 0.22;
    }
}

/* Sending: the glow travels clockwise around the pill perimeter */
.gp-pill--orbit {
    background: conic-gradient(
        from var(--gp-glow-angle) at 50% 50%,
        rgba(8, 206, 230, 0.92) 0deg,
        rgba(8, 206, 230, 0.55) 18deg,
        rgba(26, 26, 26, 0.14) 36deg 324deg,
        rgba(8, 206, 230, 0.55) 342deg,
        rgba(8, 206, 230, 0.92) 360deg
    );
    animation: gp-glow-orbit 4.6s linear infinite;
}

@keyframes gp-glow-orbit {
    from {
        --gp-glow-angle: 0deg;
    }
    to {
        --gp-glow-angle: 360deg;
    }
}
</style>
