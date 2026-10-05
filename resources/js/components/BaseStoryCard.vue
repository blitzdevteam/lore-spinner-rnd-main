<script setup lang="ts">
import BaseButton from '@/components/BaseButton.vue';
import { useBookmark } from '@/composables/useBookmark';
import { StoryInterface } from '@/types';
import { show } from '@/wayfinder/routes/stories';
import { Link } from '@inertiajs/vue3';
import { LucideBookmark, LucideLayers2, LucideMessageCircleMore, LucidePlay, LucideStar, LucideTimer, LucideShare2 } from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        story: StoryInterface;
        type?: 'column' | 'row';
        selectable?: boolean;
        active?: boolean;
    }>(),
    {
        type: 'row',
        selectable: false,
        active: false,
    },
);

defineEmits<{
    select: [story: StoryInterface];
}>();

const { isBookmarked, toggleBookmark } = useBookmark(props.story.id, props.story.is_bookmarked ?? false);

const isColumn = computed(() => props.type === 'column');
const isRow = computed(() => props.type === 'row');

const getComponent = computed(() => {
    if (isColumn.value) return 'div';
    if (props.selectable) return 'div';
    return Link;
});

const hasCover = computed(() => !!props.story?.cover);

const timeAgo = computed(() => {
    if (!props.story?.updated_at) return '—';
    const diff = Date.now() - new Date(props.story.updated_at).getTime();
    const days = Math.floor(diff / 86400000);
    if (days < 1) return 'Today';
    if (days < 30) return `${days}d ago`;
    const months = Math.floor(days / 30);
    if (months < 12) return `${months}mo ago`;
    return `${Math.floor(months / 12)}y ago`;
});
</script>

<template>
    <component
        :is="getComponent"
        :class="{
            'h-full flex-col overflow-hidden': isColumn,
            'h-52 flex-row hover:outline-primary-200/20': isRow,
            'cursor-pointer': selectable,
            'border-primary-500 outline-primary-200/20': active,
        }"
        class="flex gap-3 rounded-xl border border-gray-700 bg-gray-800 p-2.5 outline-4 outline-transparent transition hover:border-primary-500"
        :href="!selectable && isRow ? show(story.slug).url : undefined"
        @click="selectable ? $emit('select', story) : undefined"
    >
        <!-- ROW: image block -->
        <div v-if="isRow" class="relative overflow-hidden rounded-xl">
            <img
                v-if="hasCover"
                :src="story.cover"
                alt=""
                class="h-48 w-48 rounded-xl object-cover object-center"
            />
            <div
                v-else
                class="grid h-48 w-48 place-items-center rounded-xl bg-gradient-to-br from-gray-700 via-gray-800 to-gray-900"
            >
                <span class="text-3xl font-bold text-primary/60">{{ story.title?.charAt(0)?.toUpperCase() }}</span>
            </div>
            <div class="absolute start-3 top-3">
                <BaseButton severity="glass" :icon-only="true" @click.prevent.stop="toggleBookmark">
                    <LucideBookmark
                        class="size-6 transition-colors"
                        :class="isBookmarked ? 'fill-secondary-300 text-secondary-300' : 'text-secondary-200'"
                        :stroke-width="1.5"
                    />
                </BaseButton>
            </div>
        </div>

        <!-- COLUMN: image block (taller, full-width) -->
        <div v-if="isColumn" class="relative shrink-0 overflow-hidden rounded-xl">
            <img
                v-if="hasCover"
                :src="story.cover"
                alt=""
                class="h-44 w-full rounded-xl object-cover object-center"
            />
            <div
                v-else
                class="grid h-44 w-full place-items-center rounded-xl bg-gradient-to-br from-gray-700 via-gray-800 to-gray-900"
            >
                <span class="text-3xl font-bold text-primary/60">{{ story.title?.charAt(0)?.toUpperCase() }}</span>
            </div>
            <div class="absolute start-3 top-3 flex items-center gap-2">
                <BaseButton severity="glass" :icon-only="true" @click.prevent.stop="toggleBookmark">
                    <LucideBookmark
                        class="size-5 transition-colors"
                        :class="isBookmarked ? 'fill-secondary-300 text-secondary-300' : 'text-secondary-200'"
                        :stroke-width="1.5"
                    />
                </BaseButton>
                <BaseButton severity="glass" :icon-only="true">
                    <LucideShare2 class="size-5 text-secondary-200" :stroke-width="1.5" />
                </BaseButton>
            </div>
        </div>

        <!-- ROW: text content -->
        <div v-if="isRow" class="flex min-h-0 flex-1 flex-col gap-1.5">
            <div class="flex flex-col">
                <h3 class="text-lg font-semibold">{{ story.title }}</h3>
                <p class="text-sm text-primary-300" v-if="story.creator">{{ story.creator.full_name }}</p>
            </div>
            <p class="line-clamp-3 font-light text-gray-200">{{ story.teaser }}</p>
            <div class="mt-auto">
                <div class="flex items-center gap-1.5">
                    <div class="flex items-center gap-1 rounded-xl bg-white/5 px-1.5 py-1 text-secondary-300">
                        <LucideStar class="size-3.5 fill-current" />
                        <span class="mt-0.5 text-xs">4.6</span>
                    </div>
                    <div class="flex items-center gap-1 rounded-xl bg-white/5 px-1.5 py-1 text-gray-50">
                        <LucidePlay class="size-3.5 fill-current" />
                        <span class="mt-0.5 text-xs">25k</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- COLUMN: detail content -->
        <div v-if="isColumn" class="flex min-h-0 flex-1 flex-col gap-3">
            <!-- Title + Author -->
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold">{{ story.title }}</h3>
                <p class="shrink-0 text-sm text-primary-300" v-if="story.creator">By {{ story.creator.full_name }}</p>
            </div>

            <!-- Stats row: Played | Time | Comments | Category -->
            <div class="grid grid-cols-4 divide-x divide-gray-600">
                <div class="flex flex-col items-center gap-1 px-2">
                    <p class="text-xs text-gray-400">Played</p>
                    <div class="flex items-center gap-1">
                        <LucidePlay class="size-3.5 text-primary" />
                        <span class="text-sm font-semibold text-white">110k</span>
                    </div>
                </div>
                <div class="flex flex-col items-center gap-1 px-2">
                    <p class="text-xs text-gray-400">Time</p>
                    <div class="flex items-center gap-1">
                        <LucideTimer class="size-3.5 text-primary" />
                        <span class="text-sm font-semibold text-white">50 min</span>
                    </div>
                </div>
                <div class="flex flex-col items-center gap-1 px-2">
                    <p class="text-xs text-gray-400">Comments</p>
                    <div class="flex items-center gap-1">
                        <LucideMessageCircleMore class="size-3.5 text-primary" />
                        <span class="text-sm font-semibold text-white">{{ story.comments_count }}</span>
                    </div>
                </div>
                <div class="flex flex-col items-center gap-1 px-2">
                    <p class="text-xs text-gray-400">Category</p>
                    <div class="flex items-center gap-1">
                        <LucideLayers2 class="size-3.5 text-primary" />
                        <span class="truncate text-sm font-semibold text-white">{{ story.category?.title }}</span>
                    </div>
                </div>
            </div>

            <!-- Teaser -->
            <p class="line-clamp-4 text-sm font-light leading-relaxed text-gray-200">{{ story.teaser }}</p>

            <!-- Detail boxes: CHAPTERS | RATING | STATUS | UPDATED (4-col single row) -->
            <div class="grid grid-cols-4 gap-2">
                <div class="flex flex-col items-center justify-center gap-1 rounded-md border border-gray-600 px-2 py-2">
                    <p class="text-[0.625rem] tracking-wider text-gray-400 uppercase">Chapters</p>
                    <span class="text-sm font-semibold text-white">{{ story.chapters_count }}</span>
                </div>
                <div class="flex flex-col items-center justify-center gap-1 rounded-md border border-gray-600 px-2 py-2">
                    <p class="text-[0.625rem] tracking-wider text-gray-400 uppercase">Rating</p>
                    <span class="text-sm font-semibold text-white">{{ story.rating.label }}</span>
                </div>
                <div class="flex flex-col items-center justify-center gap-1 rounded-md border border-gray-600 px-2 py-2">
                    <p class="text-[0.625rem] tracking-wider text-gray-400 uppercase">Status</p>
                    <span class="text-sm font-semibold text-white">{{ story.status.label }}</span>
                </div>
                <div class="flex flex-col items-center justify-center gap-1 rounded-md border border-gray-600 px-2 py-2">
                    <p class="text-[0.625rem] tracking-wider text-gray-400 uppercase">Updated</p>
                    <span class="text-sm font-semibold text-white">{{ timeAgo }}</span>
                </div>
            </div>

            <!-- Star + Play badges -->
            <div class="flex items-center gap-1.5">
                <div class="flex items-center gap-1 rounded-xl bg-white/5 px-1.5 py-1 text-secondary-300">
                    <LucideStar class="size-3.5 fill-current" />
                    <span class="mt-0.5 text-xs">4.6</span>
                </div>
                <div class="flex items-center gap-1 rounded-xl bg-white/5 px-1.5 py-1 text-gray-50">
                    <LucidePlay class="size-3.5 fill-current" />
                    <span class="mt-0.5 text-xs">25k</span>
                </div>
            </div>

            <!-- View More -->
            <div class="mt-auto">
                <BaseButton class="w-full" severity="primary" type="internal-link" :href="show(story.slug).url"> View more </BaseButton>
            </div>
        </div>
    </component>
</template>

<style scoped></style>
