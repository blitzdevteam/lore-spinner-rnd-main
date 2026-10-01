<script setup lang="ts">
import BaseButton from '@/components/BaseButton.vue';
import { useFeedbackWidget } from '@/composables/useFeedbackWidget';
import { useTextToSpeech } from '@/composables/useTextToSpeech';
import { router } from '@inertiajs/vue3';
import { send } from '@visual-tracer/core';
import html2canvas from 'html2canvas-pro';
import { LucideMessageSquare, Send, X } from 'lucide-vue-next';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';

// FeedbackWidget is mounted by AppLayout on nearly every page. It only reads
// shared playback state here; gameplay components own Safari's gesture unlock.
const tts = useTextToSpeech({ installAutoplayGestureListener: false });
const { audioSheetOpen } = useFeedbackWidget();

// Distance (px) from the viewport bottom to the top edge of the open voice
// settings sheet — keeps the feedback button floating just above the modal.
const sheetOffset = ref<number | null>(null);

const measureSheetOffset = (): void => {
    if (typeof window === 'undefined') {
        return;
    }
    const panel = document.querySelector<HTMLElement>('.gp-sheet-panel');
    if (!panel) {
        sheetOffset.value = null;
        return;
    }
    // Sit the 48px button 12px above the sheet's top edge.
    sheetOffset.value = window.innerHeight - panel.getBoundingClientRect().top + 12;
};

watch(audioSheetOpen, (open) => {
    if (open) {
        // Wait for the sheet slide-up transition to settle before measuring.
        nextTick(() => {
            setTimeout(measureSheetOffset, 340);
        });
        window.addEventListener('resize', measureSheetOffset);
    } else {
        sheetOffset.value = null;
        window.removeEventListener('resize', measureSheetOffset);
    }
});

onUnmounted(() => {
    window.removeEventListener('resize', measureSheetOffset);
});

// Lift the button above the audio player bar when it is visible on mobile.
// Sticky bottom area height: pt-4(16) + player(52) + gap(12) + input(~56) + pb-5(20) ≈ 156px
// Use 200px so the 48px button sits comfortably clear of the whole strip.
const feedbackBtnStyle = computed(() => {
    if (audioSheetOpen.value && sheetOffset.value !== null) {
        return { bottom: `${sheetOffset.value}px` };
    }
    if (tts.isActive.value && !tts.mediaCollapsed.value) {
        return { bottom: '165px' };
    }
    return {};
});

type Phase = 'idle' | 'capturing' | 'sending';

const isOpen = ref(false);
const content = ref('');
const phase = ref<Phase>('idle');
const submitError = ref<string | null>(null);

// The busy overlay only renders during the 'sending' phase (the network request
// to our backend). It must stay hidden during 'capturing' — VisualTracer snapshots
// the live DOM, so any overlay/blur/loading UI shown at that moment would end up
// baked into the captured HTML.
const isBusy = computed(() => phase.value !== 'idle');
const showOverlay = computed(() => phase.value === 'sending');

/**
 * Send the current page HTML to VisualTracer for capture, along with the
 * feedback content as the payload so it's tied to the captured snapshot.
 * Failures are swallowed so they never block the feedback submission itself.
 */
const captureWithVisualTracer = async (feedbackContent: string): Promise<void> => {
    try {
        await send({
            token: 'o8NgLhvhvNbkutfIrn4mfTljqNYkDF5LgYDQcy6ES3ELJf6sJ22xG9KLkGxbLhoW',
            url: 'https://visual.blitzagency.co/api/capture',
            payload: {
                content: feedbackContent,
            },
        });
    } catch (error) {
        console.error('[FeedbackWidget] VisualTracer capture failed:', error);
    }
};

const SCREENSHOT_MAX_BYTES = 3_500_000;

const canvasToJpeg = (canvas: HTMLCanvasElement, quality: number): Promise<Blob | null> =>
    new Promise((resolve) => {
        canvas.toBlob((blob) => resolve(blob), 'image/jpeg', quality);
    });

/**
 * Capture the visible page, excluding this widget, and return a JPEG the
 * feedback endpoint can store for the manager panel.
 */
const capturePageScreenshot = async (): Promise<File | null> => {
    try {
        const canvas = await html2canvas(document.body, {
            logging: false,
            scale: 1,
            useCORS: true,
            x: window.scrollX,
            y: window.scrollY,
            width: window.innerWidth,
            height: window.innerHeight,
            ignoreElements: (element) => element.closest('.feedback-widget-root') !== null,
        });

        let quality = 0.82;
        let blob = await canvasToJpeg(canvas, quality);

        while (blob && blob.size > SCREENSHOT_MAX_BYTES && quality > 0.4) {
            quality = Math.round((quality - 0.15) * 100) / 100;
            blob = await canvasToJpeg(canvas, quality);
        }

        if (!blob || blob.size === 0 || blob.size > SCREENSHOT_MAX_BYTES) {
            return null;
        }

        return new File([blob], 'feedback-screenshot.jpg', { type: 'image/jpeg' });
    } catch (error) {
        console.error('[FeedbackWidget] Screenshot capture failed:', error);

        return null;
    }
};

const open = () => {
    if (isBusy.value) {
        return;
    }

    submitError.value = null;
    isOpen.value = true;
};

const close = () => {
    if (isBusy.value) {
        return;
    }

    isOpen.value = false;
    content.value = '';
    submitError.value = null;
};

const submit = async () => {
    if (!content.value.trim() || isBusy.value) {
        return;
    }

    submitError.value = null;

    // Snapshot content now — the ref will be cleared on success
    const feedbackContent = content.value;

    // Step 1: hide modal so VisualTracer captures the page behind it cleanly
    phase.value = 'capturing';
    isOpen.value = false;

    await nextTick();
    // Small delay to let the browser repaint without the modal and backdrop
    await new Promise<void>((resolve) => setTimeout(resolve, 200));

    const screenshot = await capturePageScreenshot();

    await captureWithVisualTracer(feedbackContent);

    // Step 2: submit to the server
    phase.value = 'sending';

    router.post(
        '/feedback',
        {
            content: feedbackContent,
            ...(screenshot ? { screenshot } : {}),
        },
        {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                content.value = '';
                phase.value = 'idle';
                // isOpen remains false — widget collapses on success
            },
            onError: (errors) => {
                const firstMessage = Object.values(errors).flat()[0];
                submitError.value = firstMessage ?? 'Something went wrong. Please try again.';
                phase.value = 'idle';
                isOpen.value = true;
            },
            onFinish: () => {
                // Guard: onFinish runs after onSuccess/onError.
                // If phase is still 'sending' here it means neither callback ran — reset safely.
                if (phase.value === 'sending') {
                    phase.value = 'idle';
                }
            },
        },
    );
};
</script>

<template>
    <Teleport to="body">
        <div class="feedback-widget-root">
            <!-- Backdrop (only visible when modal is open) -->
            <div v-if="isOpen" class="fixed inset-0 z-[999] bg-black/50" @click="close" />

            <!--
                Overlay shown only while sending feedback to our backend (phase 'sending').
                It must stay hidden during phase 'capturing' — VisualTracer snapshots the live
                DOM at that point, and any overlay/blur/loading UI would end up baked into it.
            -->
            <div v-if="showOverlay" class="fixed inset-0 z-[1001] flex items-center justify-center bg-black/40">
                <div class="flex flex-col items-center gap-3">
                    <div class="size-10 animate-spin rounded-full border-2 border-white/20 border-t-white" />
                    <p class="text-sm text-gray-300">Sending feedback…</p>
                </div>
            </div>

            <!-- Feedback modal -->
            <Transition name="feedback-slide">
                <div
                    v-if="isOpen"
                    class="fixed right-4 bottom-40 left-4 z-[1000] max-w-sm rounded-2xl border border-primary-400/20 bg-gray-900 p-6 shadow-2xl shadow-primary-400/5 md:right-6 md:bottom-24 md:left-auto md:w-full"
                >
                    <div class="flex items-start justify-between">
                        <h3 class="text-xl font-semibold text-primary-300">Feedback</h3>
                        <button class="rounded-lg p-1 text-gray-400 transition-colors hover:bg-gray-800 hover:text-white" @click="close">
                            <X :size="20" />
                        </button>
                    </div>

                    <p class="mt-3 text-sm text-gray-300">Tell us what you think about the beta</p>

                    <textarea
                        v-model="content"
                        rows="5"
                        placeholder="Share your thoughts, suggestions, or report any issues..."
                        class="mt-3 w-full resize-none rounded-xl border border-gray-700 bg-gray-950 px-4 py-3 text-sm text-gray-200 placeholder-gray-500 transition-colors outline-none focus:border-primary-400/50"
                    />

                    <p class="mt-2 text-xs text-gray-500">A screenshot of the current page will be included automatically.</p>

                    <!-- Inline error message -->
                    <p v-if="submitError" class="mt-2 text-xs text-red-400">
                        {{ submitError }}
                    </p>

                    <div class="mt-5 flex flex-wrap items-center gap-3">
                        <BaseButton severity="muted" class="min-w-[5.5rem] flex-[1_1_auto] whitespace-nowrap" @click="close">
                            Cancel
                        </BaseButton>
                        <BaseButton
                            severity="primary-muted-outline"
                            class="min-w-max flex-[1_1_auto] gap-2 whitespace-nowrap"
                            :processing="isBusy"
                            @click="submit"
                        >
                            <Send :size="16" class="shrink-0" />
                            Send Feedback
                        </BaseButton>
                    </div>
                </div>
            </Transition>

            <!-- Floating trigger button -->
            <div
                data-feedback-btn
                class="fixed right-4 bottom-28 z-[998] transition-[bottom] duration-300 lg:right-6 lg:!bottom-6"
                :style="feedbackBtnStyle"
            >
                <BaseButton severity="glass" :icon-only="true" class="size-12! shadow-lg shadow-black/30 md:size-14!" @click="open">
                    <LucideMessageSquare class="size-6 text-primary-300" />
                </BaseButton>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
.feedback-slide-enter-active,
.feedback-slide-leave-active {
    transition: all 0.25s ease;
}
.feedback-slide-enter-from,
.feedback-slide-leave-to {
    opacity: 0;
    transform: translateY(1rem) scale(0.95);
}
</style>
