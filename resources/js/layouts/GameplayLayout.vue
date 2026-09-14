<script setup lang="ts">
import AuroraBackground from '@/components/AuroraBackground.vue';
import BaseButton from '@/components/BaseButton.vue';
import GameplayCapsuleButton from '@/components/GameplayCapsuleButton.vue';
import GameplayAudioPanel from '@/components/GameplayAudioPanel.vue';
import GameplayBottomSheet from '@/components/GameplayBottomSheet.vue';
import GameplayInput from '@/components/GameplayInput.vue';
import GameplayJournalPanel, { type JournalMeta } from '@/components/GameplayJournalPanel.vue';
import GameplayMediaPlayer from '@/components/GameplayMediaPlayer.vue';
import GameplaySettingsPanel from '@/components/GameplaySettingsPanel.vue';
import GameplaySubscriptionUsagePanel from '@/components/GameplaySubscriptionUsagePanel.vue';
import { useFeedbackWidget } from '@/composables/useFeedbackWidget';
import { useGameplaySettings } from '@/composables/useGameplaySettings';
import { useInputNudge } from '@/composables/useInputNudge';
import { useTextToSpeech } from '@/composables/useTextToSpeech';
import { GameplayUsageSummary, SubscriptionUsageSummary } from '@/types';
import { router } from '@inertiajs/vue3';
import { LucideAudioLines, LucideChevronLeft, LucideGauge, LucideNotebookText, LucideSettings, LucideX, LucideZap } from 'lucide-vue-next';
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
});

onUnmounted(() => {
    window.matchMedia(MOBILE_MQ).removeEventListener('change', syncMobile);
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
        if (!isLoading && wasLoading && activePanel.value === 'settings') {
            refreshUsage();
        }
    },
);

const toggleAutoplay = () => {
    tts.primeAudio();
    settings.autoplay = !settings.autoplay;
};

const toggleMedia = () => {
    if (!tts.isActive.value && props.gameId && props.latestPromptId) {
        tts.primeAudio();
        tts.play(props.gameId, props.latestPromptId);
        return;
    }

    tts.mediaCollapsed.value = !tts.mediaCollapsed.value;
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
}>();

const handleInputSubmit = (prompt: string) => {
    nudge.stop();
    tts.primeAudio();
    emit('submit', prompt);
};
</script>

<template>
    <div class="relative min-h-svh">
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
                        class="z-50 flex h-20 items-center justify-between gap-3 bg-linear-to-b from-gray-950 via-gray-950/60 to-transparent px-4 transition-all duration-300 sm:px-8 md:h-24"
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
                                    <LucideSettings v-else class="size-5 text-secondary-300" />
                                </GameplayCapsuleButton>
                            </div>
                            <div class="hidden md:inline-flex">
                                <GameplayCapsuleButton
                                    label="Usage"
                                    :title="activePanel === 'usage' ? 'Close subscription usage' : 'Subscription usage'"
                                    :active="activePanel === 'usage'"
                                    @click="toggleUsage"
                                >
                                    <LucideX v-if="activePanel === 'usage'" class="size-5 text-secondary-300" />
                                    <LucideGauge v-else class="size-5 text-primary" />
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
                                    :class="settings.autoplay ? 'fill-secondary-300 text-secondary-300' : 'text-gray-300'"
                                />
                            </GameplayCapsuleButton>
                            <GameplayCapsuleButton
                                label="Audio"
                                label-tone="secondary"
                                :title="tts.mediaCollapsed.value ? 'Show audio player' : 'Hide audio player'"
                                :active="tts.isActive.value && !tts.mediaCollapsed.value"
                                @click="toggleMedia"
                            >
                                <LucideAudioLines
                                    class="size-5"
                                    :class="tts.isActive.value && !tts.mediaCollapsed.value ? 'text-secondary-300' : 'text-gray-300'"
                                />
                            </GameplayCapsuleButton>
                            <GameplayCapsuleButton
                                label="Notes"
                                label-tone="secondary"
                                :title="activePanel === 'journal' ? 'Close notes' : 'Notes'"
                                :active="activePanel === 'journal'"
                                @click="toggleJournal"
                            >
                                <LucideX v-if="activePanel === 'journal'" class="size-5 text-secondary-300" />
                                <LucideNotebookText v-else class="size-5 text-secondary-300" />
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
                                    :class="settings.autoplay ? 'fill-secondary-300 text-secondary-300' : 'text-gray-300'"
                                />
                            </button>
                            <button
                                class="mobile-pill__btn"
                                :class="{ 'mobile-pill__btn--active': tts.isActive.value && !tts.mediaCollapsed.value }"
                                :title="tts.mediaCollapsed.value ? 'Show audio player' : 'Hide audio player'"
                                @click="toggleMedia"
                            >
                                <LucideAudioLines class="size-5" :class="tts.isActive.value ? 'text-secondary-300' : 'text-gray-300'" />
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
                    <div class="p-3">
                        <slot name="header" />
                    </div>
                    <div
                        class="mx-auto flex max-w-3xl flex-col gap-8 p-3"
                        :class="isMobile ? 'pb-42' : 'pb-32'"
                    >
                        <slot name="game" />
                    </div>
                    <div
                        class="fixed right-0 bottom-0 left-0 z-20 w-full px-3 py-6 md:p-6"
                        :class="{ 'bg-linear-to-t from-black via-black/95 to-transparent': !(isIos && isMobile && inputFocused) }"
                    >
                        <div class="flex flex-col items-center gap-3 px-0">
                            <div class="flex w-full items-center justify-start md:hidden">
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
        box-shadow 0.15s ease;
    flex-shrink: 0;
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
