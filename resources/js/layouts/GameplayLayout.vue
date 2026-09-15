<script setup lang="ts">
import AuroraBackground from '@/components/AuroraBackground.vue';
import BaseButton from '@/components/BaseButton.vue';
import GameplayCapsuleButton from '@/components/GameplayCapsuleButton.vue';
import GameplayAudioPanel from '@/components/GameplayAudioPanel.vue';
import GameplayBottomSheet from '@/components/GameplayBottomSheet.vue';
import GameplayInput from '@/components/GameplayInput.vue';
import GameplayJournalPanel, { type JournalMeta } from '@/components/GameplayJournalPanel.vue';
import GameplayMediaPlayer from '@/components/GameplayMediaPlayer.vue';
import GameplayRecapPanel from '@/components/GameplayRecapPanel.vue';
import GameplaySettingsPanel from '@/components/GameplaySettingsPanel.vue';
import GameplaySubscriptionUsagePanel from '@/components/GameplaySubscriptionUsagePanel.vue';
import { useFeedbackWidget } from '@/composables/useFeedbackWidget';
import { useGameplaySettings } from '@/composables/useGameplaySettings';
import { useInputNudge } from '@/composables/useInputNudge';
import { useTextToSpeech } from '@/composables/useTextToSpeech';
import { GameplayUsageSummary, SubscriptionUsageSummary } from '@/types';
import { router } from '@inertiajs/vue3';
import { LucideChevronDown, LucideChevronLeft, LucideChevronUp, LucideGauge, LucideLoader, LucideMenu, LucideNotebookText, LucidePause, LucideSettings, LucideVolume2, LucideX, LucideZap } from 'lucide-vue-next';
import Tab from 'primevue/tab';
import TabList from 'primevue/tablist';
import TabPanel from 'primevue/tabpanel';
import TabPanels from 'primevue/tabpanels';
import Tabs from 'primevue/tabs';
import { buildAuroraProps } from '@/data/storyAuroraThemes';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        inputDisabled?: boolean;
        gameId?: string;
        usage?: GameplayUsageSummary;
        subscriptionUsage?: SubscriptionUsageSummary | null;
        latestPromptId?: string | null;
        coverUrl?: string | null;
        journalMeta?: JournalMeta;
        storySlug?: string | null;
        /** Forwarded to GameplayInput — used for the cold-open first-move hint. */
        inputPlaceholder?: string;
        /**
         * Incremented by Show.vue each time the choice buttons appear on the first move.
         * Any change starts (or restarts) the escalating twinkle loop.
         */
        nudgeTrigger?: number;
        /**
         * The bar is disabled by design rather than because the AI is working
         * (onboarding turn 2, where the player picks from the listed options).
         * Suppresses the orbit glow so the UI does not read as "thinking".
         */
        inputLockedByDesign?: boolean;
        /**
         * Hide the chat bar entirely (episode/story completion ceremony).
         */
        hideInput?: boolean;
        recapText?: string | null;
        recapLoading?: boolean;
        recapError?: string | null;
    }>(),
    {
        inputDisabled: false,
        gameId: undefined,
        latestPromptId: undefined,
        coverUrl: undefined,
        journalMeta: undefined,
        storySlug: undefined,
        inputPlaceholder: '',
        nudgeTrigger: 0,
        inputLockedByDesign: false,
        hideInput: false,
        recapText: null,
        recapLoading: false,
        recapError: null,
    },
);

const auroraProps = computed(() => buildAuroraProps(props.storySlug));

// Glow: static while reading → orbit while AI works → sweep when user engages → twinkle nudge.
const inputGlowVariant = ref<'sweep' | 'orbit' | 'twinkle' | undefined>(undefined);
let twinkleResetTimer: ReturnType<typeof setTimeout> | null = null;

function fireTwinkle() {
    if (props.inputDisabled || inputFocused.value) return;
    // Don't override sweep (user is already engaging) or orbit (AI working).
    if (inputGlowVariant.value === 'sweep' || inputGlowVariant.value === 'orbit') return;
    if (twinkleResetTimer) clearTimeout(twinkleResetTimer);
    inputGlowVariant.value = 'twinkle';
    // 2 pulses × 1.4 s = 2.8 s; revert to breathe (undefined) after both complete.
    twinkleResetTimer = setTimeout(() => {
        if (inputGlowVariant.value === 'twinkle') {
            inputGlowVariant.value = undefined;
        }
        twinkleResetTimer = null;
    }, 3000);
}

const nudge = useInputNudge({ onTwinkle: fireTwinkle });

watch(
    () => props.nudgeTrigger,
    (val, prev) => {
        if (val !== prev) nudge.start();
    },
);

watch(
    [() => props.inputDisabled, () => props.inputLockedByDesign],
    ([disabled, lockedByDesign]) => {
        if (disabled) {
            nudge.stop();
            inputGlowVariant.value = lockedByDesign ? undefined : 'orbit';
        } else {
            inputGlowVariant.value = undefined;
        }
    },
);

function onInputReadyToType() {
    if (!props.inputDisabled) {
        nudge.stop();
        inputGlowVariant.value = 'sweep';
    }
}

type Panel = 'journal' | 'settings' | 'usage' | 'audio' | null;

const activePanel = ref<Panel>(null);
const recapView = ref<'closed' | 'actions' | 'recap'>('closed');
const recapDrawerEl = ref<HTMLElement | null>(null);
const recapDockEl = ref<HTMLElement | null>(null);
const recapDragOffset = ref(0);
const recapDragging = ref(false);
let recapDragStartY = 0;
let recapDragStartTime = 0;

/** Drag distance (px) past which releasing dismisses the drawer. */
const RECAP_DISMISS_DISTANCE = 96;
/** Downward fling speed (px/ms) that dismisses regardless of distance. */
const RECAP_DISMISS_VELOCITY = 0.55;

const recapOpen = computed(() => recapView.value === 'recap');
const journalTab = ref<'journals' | 'characters'>('journals');
const journalSheetTab = ref<'timeline' | 'characters'>('timeline');
const isMobile = ref(false);
const isIos = ref(false);
const inputFocused = ref(false);

const MOBILE_MQ = '(max-width: 767px)';

const { settings } = useGameplaySettings();
const tts = useTextToSpeech();
const { audioSheetOpen } = useFeedbackWidget();

function syncMobile() {
    if (typeof window === 'undefined') return;
    isMobile.value = window.matchMedia(MOBILE_MQ).matches;
}

onMounted(() => {
    syncMobile();
    window.matchMedia(MOBILE_MQ).addEventListener('change', syncMobile);
    isIos.value = /iPad|iPhone|iPod/.test(navigator.userAgent);
    document.documentElement.classList.add('gameplay-scrollbar-theme');
    document.body.classList.add('gameplay-scrollbar-theme');
    window.addEventListener('keydown', onRecapKeydown);
    document.addEventListener('pointerdown', onRecapPointerDownOutside);
});

onUnmounted(() => {
    window.matchMedia(MOBILE_MQ).removeEventListener('change', syncMobile);
    document.documentElement.classList.remove('gameplay-scrollbar-theme');
    document.body.classList.remove('gameplay-scrollbar-theme');
    window.removeEventListener('keydown', onRecapKeydown);
    document.removeEventListener('pointerdown', onRecapPointerDownOutside);
});

watch(isMobile, (mobile) => {
    if (!mobile && activePanel.value === 'audio') {
        activePanel.value = null;
        tts.revealMediaPlayer();
    }
    audioSheetOpen.value = mobile && activePanel.value === 'audio';
});

watch([isMobile, activePanel], ([mobile, panel]) => {
    audioSheetOpen.value = mobile && panel === 'audio';
});

const toggleJournal = () => {
    activePanel.value = activePanel.value === 'journal' ? null : 'journal';
};

const toggleRecapPanel = () => {
    recapView.value = recapView.value === 'closed' ? 'actions' : 'closed';
};

const toggleRecap = () => {
    recapDragOffset.value = 0;
    recapView.value = 'recap';
    emit('recap');
};

const closeRecap = () => {
    recapDragOffset.value = 0;
    recapView.value = 'actions';
};

function onRecapKeydown(event: KeyboardEvent): void {
    if (event.key !== 'Escape' || recapView.value === 'closed') return;

    if (recapOpen.value) {
        closeRecap();
    } else {
        recapView.value = 'closed';
    }
}

function onRecapPointerDownOutside(event: PointerEvent): void {
    if (!recapOpen.value) return;
    if (recapDockEl.value?.contains(event.target as Node)) return;

    closeRecap();
}

function startRecapDrag(event: PointerEvent): void {
    recapDragStartY = event.clientY;
    recapDragStartTime = event.timeStamp;
    recapDragging.value = true;
    (event.currentTarget as HTMLElement).setPointerCapture(event.pointerId);
}

function moveRecapDrag(event: PointerEvent): void {
    if (!recapDragging.value) return;

    const delta = event.clientY - recapDragStartY;

    recapDragOffset.value = delta >= 0 ? delta : delta / 5;
}

function endRecapDrag(event: PointerEvent): void {
    if (!recapDragging.value) return;

    const travelled = recapDragOffset.value;
    const elapsed = Math.max(1, event.timeStamp - recapDragStartTime);
    const velocity = travelled / elapsed;

    recapDragging.value = false;
    recapDragOffset.value = 0;

    if (velocity > RECAP_DISMISS_VELOCITY || travelled > RECAP_DISMISS_DISTANCE) {
        closeRecap();
    }
}

const refreshUsage = () => {
    router.reload({ only: ['usage', 'subscriptionUsage'] });
};

const toggleSettings = () => {
    const openingSettings = activePanel.value !== 'settings';
    activePanel.value = openingSettings ? 'settings' : null;

    if (openingSettings) {
        refreshUsage();
    }
};

const toggleUsage = () => {
    const openingUsage = activePanel.value !== 'usage';
    activePanel.value = openingUsage ? 'usage' : null;

    if (openingUsage) {
        refreshUsage();
    }
};

watch(
    () => tts.isLoading.value,
    (isLoading, wasLoading) => {
        if (!isLoading && wasLoading) {
            refreshUsage();
        }
    },
);

const toggleAutoplay = () => {
    tts.primeAudio();
    settings.autoplay = !settings.autoplay;
};

const isNarratingLatest = computed(() => {
    return !!props.gameId && !!props.latestPromptId && tts.activeKey.value === `${props.gameId}:${props.latestPromptId}`;
});

const isNarrationLoading = computed(() => isNarratingLatest.value && tts.isLoading.value);
const isNarrationPlaying = computed(() => isNarratingLatest.value && tts.isPlaying.value);

const narrationTitle = computed(() => {
    if (!props.gameId || !props.latestPromptId) {
        return 'Narration will be available when story text arrives';
    }

    if (isNarrationLoading.value) {
        return 'Preparing narration';
    }

    if (isNarrationPlaying.value) {
        return 'Pause narration';
    }

    return isNarratingLatest.value ? 'Narrate again' : 'Narrate latest story text';
});

const toggleNarration = () => {
    if (!props.gameId || !props.latestPromptId) {
        return;
    }

    tts.toggle(props.gameId, props.latestPromptId);
};

function openAudioSettings() {
    if (!isMobile.value || activePanel.value === 'audio') return;
    activePanel.value = 'audio';
    tts.collapseMediaPlayer();
}

watch(inputFocused, (focused) => {
    if (focused) nudge.stop();
});

const closeMobilePanel = () => {
    const wasAudio = activePanel.value === 'audio';
    activePanel.value = null;
    if (wasAudio) {
        tts.revealMediaPlayer();
    }
};

const showDesktopBackdrop = () => !isMobile.value && ['journal', 'settings', 'usage'].includes(activePanel.value ?? '');

const emit = defineEmits<{
    submit: [prompt: string];
    back: [];
    recap: [];
}>();

const handleInputSubmit = (prompt: string) => {
    nudge.stop();
    tts.primeAudio();
    emit('submit', prompt);
};
</script>

<template>
    <div class="gameplay-layout relative min-h-svh">
        <div class="pointer-events-none fixed inset-0 z-0" :style="{ background: auroraProps.deep }" />
        <AuroraBackground
            class="pointer-events-none fixed inset-0 z-0"
            :deep="auroraProps.deep"
            :mids="auroraProps.mids"
            :accent="auroraProps.accent"
            :highlight="auroraProps.highlight"
            :seconds-per-color="auroraProps.secondsPerColor"
            :intensity="auroraProps.intensity"
        />
        <div class="relative flex flex-1">
            <div class="flex w-full flex-1 flex-col">
                <!-- ── Top header bar ── -->
                <div class="sticky top-0 right-0 left-0 z-30 w-full">
                    <div
                        class="z-50 flex h-20 items-center justify-between gap-3 bg-linear-to-b from-black/45 via-black/15 to-transparent px-4 transition-all duration-300 sm:px-8 md:h-24"
                    >
                        <!-- Left: back + settings (desktop) -->
                        <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                            <BaseButton severity="header-glass" :icon-only="true" class="size-11!" title="Go back" @click="$emit('back')">
                                <LucideChevronLeft class="size-6 text-gray-50" :stroke-width="1.75" />
                            </BaseButton>
                            <div class="hidden md:inline-flex">
                                <GameplayCapsuleButton
                                    label="Settings"
                                    label-tone="secondary"
                                    :title="activePanel === 'settings' ? 'Close settings' : 'Settings'"
                                    :active="activePanel === 'settings'"
                                    @click="toggleSettings"
                                >
                                    <LucideX v-if="activePanel === 'settings'" class="size-5 text-secondary-300" />
                                    <LucideSettings v-else class="size-5 text-gray-300" />
                                </GameplayCapsuleButton>
                            </div>
                            <div class="hidden md:inline-flex">
                                <GameplayCapsuleButton
                                    label="Usage"
                                    label-tone="secondary"
                                    :title="activePanel === 'usage' ? 'Close subscription usage' : 'Subscription usage'"
                                    :active="activePanel === 'usage'"
                                    @click="toggleUsage"
                                >
                                    <LucideX v-if="activePanel === 'usage'" class="size-5 text-secondary-300" />
                                    <LucideGauge v-else class="size-5 text-gray-300" />
                                </GameplayCapsuleButton>
                            </div>
                        </div>

                        <!-- Center: media player (desktop) -->
                        <div class="hidden min-w-0 flex-1 items-center justify-center md:flex">
                            <GameplayMediaPlayer :collapsed="tts.mediaCollapsed.value" />
                        </div>

                        <!-- Right: desktop action buttons (capsule expands on hover) -->
                        <div class="hidden shrink-0 items-center gap-2 sm:gap-3 md:flex">
                            <GameplayCapsuleButton
                                label="Auto Play"
                                label-tone="secondary"
                                :title="settings.autoplay ? 'Autoplay on' : 'Autoplay off'"
                                :active="settings.autoplay"
                                @click="toggleAutoplay"
                            >
                                <LucideZap
                                    class="size-5 transition-colors"
                                    :class="settings.autoplay ? 'text-secondary-300' : 'text-gray-300'"
                                />
                            </GameplayCapsuleButton>
                            <GameplayCapsuleButton
                                label="Narrate"
                                label-tone="secondary"
                                :title="narrationTitle"
                                :active="isNarratingLatest"
                                @click="toggleNarration"
                            >
                                <LucideLoader v-if="isNarrationLoading" class="size-5 animate-spin text-secondary-300" />
                                <LucidePause v-else-if="isNarrationPlaying" class="size-5 text-secondary-300" fill="currentColor" />
                                <LucideVolume2 v-else class="size-5" :class="isNarratingLatest ? 'text-secondary-300' : 'text-gray-300'" />
                            </GameplayCapsuleButton>
                            <GameplayCapsuleButton
                                label="Notes"
                                label-tone="secondary"
                                :title="activePanel === 'journal' ? 'Close notes' : 'Notes'"
                                :active="activePanel === 'journal'"
                                @click="toggleJournal"
                            >
                                <LucideX v-if="activePanel === 'journal'" class="size-5 text-secondary-300" />
                                <LucideNotebookText v-else class="size-5 text-gray-300" />
                            </GameplayCapsuleButton>
                        </div>

                        <!-- Mobile: action pill -->
                        <div class="mobile-pill gameplay-header-glass flex md:hidden">
                            <button
                                class="mobile-pill__btn"
                                :class="{ 'mobile-pill__btn--active': activePanel === 'settings' }"
                                :title="activePanel === 'settings' ? 'Close settings' : 'Settings'"
                                @click="toggleSettings"
                            >
                                <LucideX v-if="activePanel === 'settings'" class="size-5 text-secondary-300" />
                                <LucideSettings v-else class="size-5 text-gray-300" />
                            </button>
                            <button
                                class="mobile-pill__btn"
                                :class="{ 'mobile-pill__btn--active': activePanel === 'usage' }"
                                :title="activePanel === 'usage' ? 'Close subscription usage' : 'Subscription usage'"
                                @click="toggleUsage"
                            >
                                <LucideX v-if="activePanel === 'usage'" class="size-5 text-secondary-300" />
                                <LucideGauge v-else class="size-5 text-gray-300" />
                            </button>
                            <button class="mobile-pill__btn" :title="settings.autoplay ? 'Autoplay on' : 'Autoplay off'" @click="toggleAutoplay">
                                <LucideZap
                                    class="size-5 transition-colors"
                                    :class="settings.autoplay ? 'text-secondary-300' : 'text-gray-300'"
                                />
                            </button>
                            <button
                                class="mobile-pill__btn"
                                :class="{ 'mobile-pill__btn--active': isNarratingLatest }"
                                :title="narrationTitle"
                                @click="toggleNarration"
                            >
                                <LucideLoader v-if="isNarrationLoading" class="size-5 animate-spin text-secondary-300" />
                                <LucidePause v-else-if="isNarrationPlaying" class="size-5 text-secondary-300" fill="currentColor" />
                                <LucideVolume2 v-else class="size-5" :class="isNarratingLatest ? 'text-secondary-300' : 'text-gray-300'" />
                            </button>
                            <button
                                class="mobile-pill__btn"
                                :class="{ 'mobile-pill__btn--active': activePanel === 'journal' }"
                                :title="activePanel === 'journal' ? 'Close notes' : 'Notes'"
                                @click="toggleJournal"
                            >
                                <LucideX v-if="activePanel === 'journal'" class="size-5 text-secondary-300" />
                                <LucideNotebookText v-else class="size-5 text-gray-300" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ── Story content + input (single flow — input sits under choices) ── -->
                <div
                    class="z-5 mx-auto flex w-full flex-col transition-colors duration-300"
                    :style="{ fontSize: settings.fontSize + 'px', color: settings.fontColor }"
                >
                    <div :class="props.hideInput ? 'px-3' : 'p-3'">
                        <slot name="header" />
                    </div>
                    <div
                        class="mx-auto flex max-w-3xl flex-col gap-8 p-3"
                        :class="props.hideInput ? 'pb-4' : isMobile ? 'pb-42' : 'pb-32'"
                    >
                        <slot name="game" />
                    </div>
                    <div
                        v-if="!props.hideInput"
                        class="fixed right-0 bottom-0 left-0 z-20 w-full px-3 py-6 md:p-6"
                        :class="{ 'bg-linear-to-t from-black via-black/95 to-transparent': !(isIos && isMobile && inputFocused) }"
                    >
                        <div ref="recapDockEl" class="flex flex-col items-center gap-0 px-0">
                            <Transition name="recap-drawer">
                                <div v-if="recapOpen" class="recap-drawer w-full max-w-3xl">
                                    <div class="min-h-0 overflow-hidden pb-2">
                                        <div
                                            ref="recapDrawerEl"
                                            class="flex max-h-[42vh] flex-col overflow-hidden rounded-2xl border border-primary/20 bg-gray-950/95 shadow-[0_-12px_40px_rgba(0,0,0,0.5)] backdrop-blur-xl"
                                            :class="recapDragging ? 'transition-none' : 'transition-transform duration-200 ease-out'"
                                            :style="{ transform: `translateY(${recapDragOffset}px)` }"
                                            role="dialog"
                                            aria-label="Story recap"
                                        >
                                            <button
                                                type="button"
                                                class="group flex h-8 w-full shrink-0 cursor-grab touch-none items-center justify-center active:cursor-grabbing"
                                                aria-label="Drag down to close recap"
                                                @pointerdown="startRecapDrag"
                                                @pointermove="moveRecapDrag"
                                                @pointerup="endRecapDrag"
                                                @pointercancel="endRecapDrag"
                                            >
                                                <span class="h-1 w-10 rounded-full bg-white/30 transition-colors group-hover:bg-white/60" />
                                            </button>
                                            <div class="flex shrink-0 items-center justify-between px-5 pb-2 md:px-6">
                                                <span class="text-xs font-semibold tracking-[0.18em] text-primary-300 uppercase">Recap</span>
                                                <button
                                                    type="button"
                                                    class="text-xs text-gray-500 transition hover:text-gray-200"
                                                    aria-label="Close recap"
                                                    @click="closeRecap"
                                                >
                                                    Close
                                                </button>
                                            </div>
                                            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 pb-4 md:px-6">
                                                <GameplayRecapPanel
                                                    :text="props.recapText"
                                                    :loading="props.recapLoading"
                                                    :error="props.recapError"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </Transition>

                            <Transition name="recap-nub">
                                <button
                                    v-if="!recapOpen"
                                    type="button"
                                    class="group flex h-6 shrink-0 items-center justify-center"
                                    :title="recapView === 'closed' ? 'Show recap' : 'Hide'"
                                    :aria-label="recapView === 'closed' ? 'Show recap' : 'Hide'"
                                    :aria-expanded="recapView !== 'closed'"
                                    @click="toggleRecapPanel"
                                >
                                    <span class="flex items-center rounded-full border border-white/10 bg-black/40 px-2.5 py-0.5 text-gray-500 transition group-hover:border-primary/30 group-hover:text-primary-300">
                                        <LucideChevronUp v-if="recapView === 'closed'" class="size-3.5" />
                                        <LucideChevronDown v-else class="size-3.5" />
                                    </span>
                                </button>
                            </Transition>

                            <Transition name="action-strip">
                                <div v-if="recapView !== 'closed'" class="action-strip w-full max-w-3xl">
                                    <div class="min-h-0 overflow-hidden">
                                        <div class="mb-2 flex w-full items-center gap-2 rounded-2xl border border-white/10 bg-[#111]/95 px-3 py-2 md:px-4">
                                            <button
                                                type="button"
                                                class="flex h-10 shrink-0 items-center gap-2 rounded-xl border px-3 text-sm transition"
                                                :class="recapOpen
                                                    ? 'border-primary/60 bg-primary/15 text-primary-300'
                                                    : 'border-white/15 bg-black/35 text-gray-300 hover:border-primary/50 hover:text-primary-300'"
                                                :title="recapOpen ? 'Close recap' : 'Catch me up'"
                                                @click="recapOpen ? closeRecap() : toggleRecap()"
                                            >
                                                <LucideX v-if="recapOpen" class="size-4" />
                                                <LucideMenu v-else class="size-4 text-primary-300" />
                                                <span>Recap</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </Transition>

                            <div class="flex w-full max-w-3xl items-center justify-start md:hidden">
                                <GameplayMediaPlayer :collapsed="tts.mediaCollapsed.value" @open-audio-settings="openAudioSettings" />
                            </div>
                            <GameplayInput
                                :disabled="props.inputDisabled"
                                :glow-variant="inputGlowVariant"
                                :placeholder="props.inputPlaceholder"
                                @submit="handleInputSubmit"
                                @ready-to-type="onInputReadyToType"
                                @focus="inputFocused = true"
                                @blur="inputFocused = false"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Desktop: sidebar backdrop -->
            <Transition name="backdrop-fade">
                <div v-if="showDesktopBackdrop()" class="fixed inset-0 z-40 bg-black/50" @click="activePanel = null" />
            </Transition>

            <!-- Desktop: right sidebar panels -->
            <Transition name="sidebar-slide">
                <div
                    v-if="!isMobile && activePanel === 'journal'"
                    key="journal"
                    class="fixed inset-y-0 right-0 z-50 flex h-svh w-[85vw] max-w-sm flex-col overflow-hidden border-s border-gray-700 bg-gray-900 md:sticky md:right-auto md:z-50 md:w-md md:max-w-none md:shrink-0"
                >
                    <div class="flex h-full w-full flex-col">
                        <Tabs v-model:value="journalTab" class="flex h-full w-full flex-col px-4 md:px-8" :show-navigators="false" unstyled>
                            <TabList pt:tab-list="h-20 flex items-center gap-4 md:h-24 shrink-0" pt:content="" pt:active-bar="hidden">
                                <Tab class="flex-1" value="journals" v-slot="slotProps" as-child>
                                    <BaseButton
                                        class="w-full"
                                        :severity="slotProps.active ? 'secondary-muted-outline' : 'gray-muted'"
                                        @click="slotProps.onClick"
                                    >
                                        Journal
                                    </BaseButton>
                                </Tab>
                                <Tab class="flex-1" value="characters" v-slot="slotProps" as-child>
                                    <BaseButton
                                        class="w-full"
                                        :severity="slotProps.active ? 'secondary-muted-outline' : 'gray-muted'"
                                        @click="slotProps.onClick"
                                    >
                                        Characters
                                    </BaseButton>
                                </Tab>
                            </TabList>
                            <TabPanels class="min-h-0 flex-1 overflow-y-auto">
                                <TabPanel value="journals">
                                    <div class="flex flex-col gap-4 pb-8">
                                        <slot name="journals" />
                                    </div>
                                </TabPanel>
                                <TabPanel value="characters">
                                    <div class="flex flex-col gap-3 pb-8">
                                        <slot name="characters" />
                                    </div>
                                </TabPanel>
                            </TabPanels>
                        </Tabs>
                    </div>
                </div>
                <div
                    v-else-if="!isMobile && activePanel === 'settings'"
                    key="settings"
                    class="fixed inset-y-0 right-0 z-50 flex h-svh w-[85vw] max-w-sm flex-col overflow-y-auto border-s border-gray-700 bg-gray-900 md:sticky md:right-auto md:z-50 md:w-sm md:max-w-none md:shrink-0"
                >
                    <div class="flex h-full w-full flex-col px-6 pt-8">
                        <GameplaySettingsPanel
                            variant="sidebar"
                            :game-id="props.gameId"
                            :prompt-id="props.latestPromptId"
                            :usage="props.usage"
                            :subscription-usage="props.subscriptionUsage"
                        />
                    </div>
                </div>
                <div
                    v-else-if="!isMobile && activePanel === 'usage'"
                    key="usage"
                    class="fixed inset-y-0 right-0 z-50 flex h-svh w-[85vw] max-w-sm flex-col overflow-y-auto border-s border-gray-700 bg-gray-900 md:sticky md:right-auto md:z-50 md:w-sm md:max-w-none md:shrink-0"
                >
                    <div class="flex h-full w-full flex-col px-6 pt-8">
                        <GameplaySubscriptionUsagePanel :subscription-usage="props.subscriptionUsage" />
                    </div>
                </div>
            </Transition>
        </div>

        <!-- Mobile: bottom sheets -->
        <GameplayBottomSheet :open="isMobile && activePanel === 'journal'" title="Journal" @close="closeMobilePanel">
            <GameplayJournalPanel v-model:tab="journalSheetTab" :meta="journalMeta">
                <template #timeline>
                    <slot name="journals" />
                </template>
                <template #characters>
                    <slot name="characters" />
                </template>
            </GameplayJournalPanel>
        </GameplayBottomSheet>

        <GameplayBottomSheet :open="isMobile && activePanel === 'settings'" title="Settings" @close="closeMobilePanel">
            <GameplaySettingsPanel
                variant="sheet"
                :game-id="props.gameId"
                :prompt-id="props.latestPromptId"
                :usage="props.usage"
                :subscription-usage="props.subscriptionUsage"
            />
        </GameplayBottomSheet>

        <GameplayBottomSheet :open="isMobile && activePanel === 'usage'" title="Subscription usage" @close="closeMobilePanel">
            <GameplaySubscriptionUsagePanel :subscription-usage="props.subscriptionUsage" />
        </GameplayBottomSheet>

        <GameplayBottomSheet :open="isMobile && activePanel === 'audio'" title="Audio" @close="closeMobilePanel">
            <GameplayAudioPanel />
        </GameplayBottomSheet>
    </div>
</template>

<style scoped>
.gameplay-layout {
    overflow-anchor: none;
}

:global(html.gameplay-scrollbar-theme),
:global(body.gameplay-scrollbar-theme) {
    scrollbar-color: rgba(84, 244, 218, 0.3) transparent;
    scrollbar-width: thin;
}

:global(html.gameplay-scrollbar-theme::-webkit-scrollbar),
:global(body.gameplay-scrollbar-theme::-webkit-scrollbar) {
    width: 4px;
}

:global(html.gameplay-scrollbar-theme::-webkit-scrollbar-track),
:global(body.gameplay-scrollbar-theme::-webkit-scrollbar-track) {
    background: transparent;
}

:global(html.gameplay-scrollbar-theme::-webkit-scrollbar-thumb),
:global(body.gameplay-scrollbar-theme::-webkit-scrollbar-thumb) {
    border-radius: 9999px;
    background: rgba(84, 244, 218, 0.3);
}

:global(html.gameplay-scrollbar-theme::-webkit-scrollbar-thumb:hover),
:global(body.gameplay-scrollbar-theme::-webkit-scrollbar-thumb:hover) {
    background: rgba(84, 244, 218, 0.5);
}

.sidebar-slide-enter-active,
.sidebar-slide-leave-active {
    transition: all 0.3s ease;
}

.sidebar-slide-enter-from,
.sidebar-slide-leave-to {
    width: 0;
    opacity: 0;
}

.backdrop-fade-enter-active,
.backdrop-fade-leave-active {
    transition: opacity 0.3s ease;
}

.backdrop-fade-enter-from,
.backdrop-fade-leave-to {
    opacity: 0;
}

.recap-drawer,
.action-strip {
    display: grid;
    grid-template-rows: 1fr;
}

.recap-drawer-enter-active,
.recap-drawer-leave-active {
    transition:
        grid-template-rows 0.28s cubic-bezier(0.22, 1, 0.36, 1),
        opacity 0.2s ease;
}

.recap-drawer-enter-from,
.recap-drawer-leave-to {
    grid-template-rows: 0fr;
    opacity: 0;
}

.action-strip-enter-active,
.action-strip-leave-active {
    transition:
        grid-template-rows 0.22s cubic-bezier(0.22, 1, 0.36, 1),
        opacity 0.18s ease;
}

.action-strip-enter-from,
.action-strip-leave-to {
    grid-template-rows: 0fr;
    opacity: 0;
}

.recap-nub-enter-active,
.recap-nub-leave-active {
    transition:
        opacity 0.18s ease,
        transform 0.18s ease;
}

.recap-nub-enter-from,
.recap-nub-leave-to {
    opacity: 0;
    transform: translateY(0.25rem);
}

@media (prefers-reduced-motion: reduce) {
    .recap-drawer-enter-active,
    .recap-drawer-leave-active,
    .action-strip-enter-active,
    .action-strip-leave-active,
    .recap-nub-enter-active,
    .recap-nub-leave-active {
        transition-duration: 0.01ms;
    }
}

.mobile-pill {
    align-items: center;
    gap: 2px;
    padding: 6px;
    border-radius: 60px;
}

.mobile-pill__btn {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    border: none;
    background: transparent;
    cursor: pointer;
    transition:
        background 0.15s ease,
        box-shadow 0.15s ease,
        color 0.15s ease;
    flex-shrink: 0;
}

.mobile-pill__btn:hover :deep(svg),
.mobile-pill__btn:focus-visible :deep(svg),
.mobile-pill__btn--active :deep(svg) {
    color: var(--color-secondary-300);
}

.mobile-pill__btn--active {
    background: rgba(255, 255, 255, 0.08);
    box-shadow:
        inset 1px 1px 0.5px -1px rgba(255, 255, 255, 0.14),
        inset 0 0 20px 2px rgba(255, 255, 255, 0.05);
}

.mobile-pill__btn:active {
    transform: scale(0.96);
}
</style>
