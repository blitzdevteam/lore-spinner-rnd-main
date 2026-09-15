<script setup lang="ts">
import BaseButton from '@/components/BaseButton.vue';

export interface JournalMeta {
    storyTitle?: string;
    episodeLabel?: string | null;
    sessionNumber?: number | null;
    turnCount?: number;
    sessionComplete?: boolean;
}

const props = defineProps<{
    meta?: JournalMeta;
}>();

const tab = defineModel<'timeline' | 'characters'>('tab', { default: 'timeline' });
</script>

<template>
    <div class="flex flex-col gap-5">
        <!-- Story Progress Card -->
        <div
            v-if="meta?.storyTitle || meta?.sessionNumber"
            class="gp-journal-card overflow-hidden rounded-2xl border border-primary/15 bg-gradient-to-br from-primary/8 via-white/[0.03] to-transparent p-4"
        >
            <p v-if="meta.storyTitle" class="text-base font-medium leading-snug text-gray-100">
                {{ meta.storyTitle }}
            </p>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <span
                    v-if="meta.episodeLabel"
                    class="rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-xs font-medium text-gray-300"
                >
                    {{ meta.episodeLabel }}
                </span>
                <span
                    v-else-if="meta.sessionNumber"
                    class="rounded-full border border-primary/25 bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary-300"
                >
                    Episode {{ meta.sessionNumber }}
                </span>
                <span
                    v-if="meta.sessionComplete"
                    class="rounded-full border border-primary/30 bg-primary/15 px-2.5 py-1 text-xs font-medium text-primary-200"
                >
                    Complete
                </span>
            </div>
        </div>

        <!-- Episode Stats -->
        <div class="grid grid-cols-2 gap-3">
            <div class="gp-journal-stat rounded-xl border border-white/8 bg-white/[0.03] p-3.5">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500">Turns</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-100">
                    {{ meta?.turnCount ?? 0 }}
                </p>
            </div>
            <div class="gp-journal-stat rounded-xl border border-white/8 bg-white/[0.03] p-3.5">
                <p class="text-[10px] font-semibold tracking-wider text-gray-500 uppercase">Episode</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-100">
                    {{ meta?.sessionNumber ?? 1 }}
                </p>
            </div>
        </div>

        <!-- Tab switcher -->
        <div class="flex gap-2">
            <BaseButton
                class="flex-1"
                :severity="tab === 'timeline' ? 'secondary-muted-outline' : 'gray-muted'"
                @click="tab = 'timeline'"
            >
                Events
            </BaseButton>
            <BaseButton
                class="flex-1"
                :severity="tab === 'characters' ? 'secondary-muted-outline' : 'gray-muted'"
                @click="tab = 'characters'"
            >
                Characters
            </BaseButton>
        </div>

        <!-- Tab content with crossfade -->
        <div class="relative min-h-[12rem]">
            <Transition name="gp-tab-crossfade" mode="out-in">
                <div v-if="tab === 'timeline'" key="timeline" class="flex flex-col gap-3">
                    <slot name="timeline">
                        <p class="py-6 text-center text-sm text-gray-500">No events yet — your story is just beginning.</p>
                    </slot>
                </div>
                <div v-else key="characters" class="flex flex-col gap-3">
                    <slot name="characters">
                        <p class="py-6 text-center text-sm text-gray-500">No characters introduced yet.</p>
                    </slot>
                </div>
            </Transition>
        </div>
    </div>
</template>

<style scoped>
.gp-tab-crossfade-enter-active,
.gp-tab-crossfade-leave-active {
    transition: opacity 0.22s ease;
}

.gp-tab-crossfade-enter-from,
.gp-tab-crossfade-leave-to {
    opacity: 0;
}
</style>
