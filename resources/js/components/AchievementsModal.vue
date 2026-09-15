<script setup lang="ts">
import GameCompletionCeremony from '@/components/GameCompletionCeremony.vue';
import type { AchievementCard, AchievementStory } from '@/types';
import { index as achievementsIndex } from '@/wayfinder/routes/user/achievements';
import { ArrowLeft, BookOpen, Search, Trophy, X } from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    close: [];
}>();

type CardFilter = 'all' | 'episodes' | 'story';
type View = 'stories' | 'cards' | 'receipt';

const stories = ref<AchievementStory[]>([]);
const loading = ref(false);
const loadError = ref(false);
const search = ref('');
const view = ref<View>('stories');
const selectedStory = ref<AchievementStory | null>(null);
const selectedCard = ref<AchievementCard | null>(null);
const cardFilter = ref<CardFilter>('all');

const filteredStories = computed(() => {
    const query = search.value.trim().toLowerCase();

    if (query === '') {
        return stories.value;
    }

    return stories.value.filter((story) => story.title.toLowerCase().includes(query));
});

const visibleCards = computed((): AchievementCard[] => {
    const story = selectedStory.value;

    if (!story) {
        return [];
    }

    const episodes = story.episodes;
    const storyCard = story.story_card ? [story.story_card] : [];

    if (cardFilter.value === 'episodes') {
        return episodes;
    }

    if (cardFilter.value === 'story') {
        return storyCard;
    }

    return [...storyCard, ...episodes];
});

const storySummary = computed(() => {
    const story = selectedStory.value;

    if (!story) {
        return '';
    }

    const parts: string[] = [];

    if (story.episode_count > 0) {
        parts.push(`${story.episode_count} episode${story.episode_count === 1 ? '' : 's'}`);
    }

    if (story.story_complete) {
        parts.push('story ending');
    }

    if (story.total_sessions > 0 && !story.story_complete) {
        parts.push(`${story.episode_count} of ${story.total_sessions} episodes earned`);
    }

    return parts.join(' · ');
});

function storyCountLabel(story: AchievementStory): string {
    const parts: string[] = [];

    if (story.episode_count > 0) {
        parts.push(`${story.episode_count} episode${story.episode_count === 1 ? '' : 's'}`);
    }

    if (story.story_complete) {
        parts.push('Story complete');
    }

    return parts.join(' · ') || 'No cards yet';
}

function close(): void {
    emit('close');
}

function resetView(): void {
    view.value = 'stories';
    selectedStory.value = null;
    selectedCard.value = null;
    cardFilter.value = 'all';
    search.value = '';
}

function backFromCards(): void {
    view.value = 'stories';
    selectedStory.value = null;
    selectedCard.value = null;
    cardFilter.value = 'all';
}

function backFromReceipt(): void {
    view.value = 'cards';
    selectedCard.value = null;
}

function openStory(story: AchievementStory): void {
    selectedStory.value = story;
    selectedCard.value = null;
    cardFilter.value = 'all';
    view.value = 'cards';
}

function openCard(card: AchievementCard): void {
    selectedCard.value = card;
    view.value = 'receipt';
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key !== 'Escape') {
        return;
    }

    if (view.value === 'receipt') {
        backFromReceipt();
        return;
    }

    if (view.value === 'cards') {
        backFromCards();
        return;
    }

    close();
}

async function loadStories(): Promise<void> {
    loading.value = true;
    loadError.value = false;

    try {
        const response = await fetch(achievementsIndex.url(), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Failed to load achievements');
        }

        const payload = (await response.json()) as { stories?: AchievementStory[] };
        stories.value = payload.stories ?? [];
    } catch {
        loadError.value = true;
        stories.value = [];
    } finally {
        loading.value = false;
    }
}

watch(
    () => props.open,
    (open) => {
        if (typeof document === 'undefined') {
            return;
        }

        if (open) {
            document.body.classList.add('overflow-hidden');
            window.addEventListener('keydown', onKeydown);
            resetView();
            void loadStories();
        } else {
            document.body.classList.remove('overflow-hidden');
            window.removeEventListener('keydown', onKeydown);
        }
    },
);

onUnmounted(() => {
    if (typeof document === 'undefined') {
        return;
    }

    document.body.classList.remove('overflow-hidden');
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <Transition name="achievements-overlay">
            <div
                v-if="open"
                class="fixed inset-0 z-[300] flex items-end justify-center sm:items-center sm:p-6"
                role="dialog"
                aria-modal="true"
                aria-labelledby="achievements-modal-title"
            >
                <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="close" />

                <Transition name="achievements-panel" appear>
                    <section
                        class="achievements-modal relative z-10 flex w-full max-w-4xl flex-col overflow-hidden rounded-t-2xl border border-white/[0.08] bg-[#0a0d0e] sm:rounded-2xl"
                        @click.stop
                    >
                        <div
                            class="pointer-events-none absolute inset-x-0 top-0 z-20 h-px bg-gradient-to-r from-transparent via-primary/50 to-transparent"
                            aria-hidden="true"
                        />

                        <header class="relative z-10 flex items-start justify-between gap-3 border-b border-white/10 px-5 py-4 sm:px-6">
                            <div class="min-w-0">
                                <p class="text-[0.6875rem] font-medium tracking-[0.16em] text-primary-200 uppercase">
                                    Achievements
                                </p>
                                <h2 id="achievements-modal-title" class="mt-1 font-[Inter] text-xl font-semibold text-white">
                                    <template v-if="view === 'stories'">Your completion cards</template>
                                    <template v-else>{{ selectedStory?.title }}</template>
                                </h2>
                                <p class="mt-1 text-sm leading-relaxed text-[#a4b0b3]">
                                    <template v-if="view === 'stories'">
                                        Choose a story, then an episode or story ending to reopen its card.
                                    </template>
                                    <template v-else-if="view === 'cards'">
                                        {{ storySummary || 'Choose an episode card or the story ending.' }}
                                    </template>
                                    <template v-else>
                                        A keepsake from the moment you finished this {{ selectedCard?.mode === 'story' ? 'story' : 'episode' }}.
                                    </template>
                                </p>
                            </div>
                            <button
                                type="button"
                                class="grid size-9 shrink-0 place-items-center rounded-full bg-white/5 text-white ring-1 ring-white/15 transition hover:bg-white/10"
                                aria-label="Close achievements"
                                @click="close"
                            >
                                <X class="size-4" :stroke-width="2.25" />
                            </button>
                        </header>

                        <div class="relative z-10 min-h-0 flex-1 overflow-y-auto px-5 py-4 sm:px-6 sm:py-5">
                            <div v-if="view !== 'stories'" class="mb-4">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 text-sm text-[#a4b0b3] transition hover:text-white"
                                    @click="view === 'receipt' ? backFromReceipt() : backFromCards()"
                                >
                                    <ArrowLeft class="size-4" :stroke-width="2" />
                                    {{ view === 'receipt' ? 'Back to cards' : 'All stories' }}
                                </button>
                            </div>

                            <div v-if="loading" class="flex flex-col gap-3" aria-hidden="true">
                                <div class="h-24 animate-pulse rounded-2xl bg-white/5" />
                                <div class="h-24 animate-pulse rounded-2xl bg-white/5" />
                                <div class="h-24 animate-pulse rounded-2xl bg-white/5" />
                            </div>

                            <div v-else-if="loadError" class="rounded-2xl border border-white/10 px-5 py-8 text-center">
                                <p class="font-[Inter] text-sm font-semibold text-white">Couldn’t load your cards</p>
                                <p class="mt-1.5 text-sm text-[#a4b0b3]">Try again in a moment.</p>
                                <button
                                    type="button"
                                    class="mt-4 rounded-lg border border-primary/60 bg-primary/10 px-4 py-2 text-sm font-semibold text-primary-100"
                                    @click="loadStories"
                                >
                                    Retry
                                </button>
                            </div>

                            <div v-else-if="view === 'stories'" class="flex flex-col gap-4">
                                <label class="relative block">
                                    <Search class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-[#7d888b]" />
                                    <input
                                        v-model="search"
                                        type="search"
                                        placeholder="Search your stories"
                                        class="h-11 w-full rounded-xl border border-white/10 bg-white/5 pr-3 pl-10 text-sm text-white outline-none placeholder:text-[#7d888b] focus:border-primary/50"
                                    />
                                </label>

                                <div v-if="filteredStories.length" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <button
                                        v-for="story in filteredStories"
                                        :key="story.id"
                                        type="button"
                                        class="achievement-story-card flex min-h-[6.5rem] overflow-hidden rounded-2xl border border-white/10 text-left transition hover:border-primary/40"
                                        @click="openStory(story)"
                                    >
                                        <span class="relative block w-24 shrink-0 self-stretch bg-[#111618] sm:w-28">
                                            <img
                                                v-if="story.cover"
                                                :src="story.cover"
                                                :alt="story.title"
                                                class="absolute inset-0 size-full object-cover"
                                            />
                                            <span
                                                v-else
                                                class="absolute inset-0 grid place-items-center bg-gradient-to-br from-primary/20 to-black"
                                            >
                                                <BookOpen class="size-6 text-primary-200" />
                                            </span>
                                        </span>
                                        <span class="flex min-w-0 flex-1 flex-col justify-center gap-1 p-3.5">
                                            <span class="truncate font-[Inter] text-sm font-semibold text-white">
                                                {{ story.title }}
                                            </span>
                                            <span class="text-xs text-[#98a2a5]">{{ storyCountLabel(story) }}</span>
                                            <span class="text-[0.6875rem] font-medium tracking-[0.08em] text-primary-200 uppercase">
                                                Open cards
                                            </span>
                                        </span>
                                    </button>
                                </div>

                                <div
                                    v-else
                                    class="flex flex-col items-center gap-3 rounded-2xl border border-white/10 px-5 py-10 text-center"
                                >
                                    <span class="grid size-12 place-items-center rounded-full border border-primary/30 bg-primary/10 text-primary">
                                        <Trophy class="size-5" :stroke-width="2" />
                                    </span>
                                    <p class="font-[Inter] text-sm font-semibold text-white">
                                        {{ search.trim() ? 'No matching stories' : 'No completion cards yet' }}
                                    </p>
                                    <p class="max-w-sm text-sm leading-relaxed text-[#a4b0b3]">
                                        {{
                                            search.trim()
                                                ? 'Try another title, or clear the search to see every story you’ve earned cards for.'
                                                : 'Finish an episode to earn its card. Story endings appear here when you complete the whole book.'
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div v-else-if="view === 'cards' && selectedStory" class="flex flex-col gap-4">
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="rounded-full px-3 py-1.5 text-xs font-medium tracking-[0.04em]"
                                        :class="cardFilter === 'all' ? 'bg-primary/20 text-primary-100' : 'bg-white/5 text-[#a4b0b3]'"
                                        @click="cardFilter = 'all'"
                                    >
                                        All cards
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-full px-3 py-1.5 text-xs font-medium tracking-[0.04em]"
                                        :class="cardFilter === 'episodes' ? 'bg-primary/20 text-primary-100' : 'bg-white/5 text-[#a4b0b3]'"
                                        @click="cardFilter = 'episodes'"
                                    >
                                        Episodes
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-full px-3 py-1.5 text-xs font-medium tracking-[0.04em]"
                                        :class="cardFilter === 'story' ? 'bg-primary/20 text-primary-100' : 'bg-white/5 text-[#a4b0b3]'"
                                        @click="cardFilter = 'story'"
                                    >
                                        Story ending
                                    </button>
                                </div>

                                <div v-if="visibleCards.length" class="grid grid-cols-1 gap-3">
                                    <button
                                        v-for="card in visibleCards"
                                        :key="card.id"
                                        type="button"
                                        class="achievement-card-tile rounded-2xl border border-white/10 p-4 text-left transition hover:border-primary/40"
                                        @click="openCard(card)"
                                    >
                                        <span class="flex items-center justify-between gap-3">
                                            <span
                                                class="rounded-full px-2 py-0.5 text-[0.625rem] font-semibold tracking-[0.12em] uppercase"
                                                :class="
                                                    card.mode === 'story'
                                                        ? 'border border-primary/40 bg-primary/10 text-primary-100'
                                                        : 'border border-[#d4b483]/40 bg-[#d4b483]/10 text-[#d4b483]'
                                                "
                                            >
                                                {{ card.label }}
                                            </span>
                                            <span class="text-[0.625rem] tracking-[0.08em] text-[#7d888b] uppercase">
                                                Open card
                                            </span>
                                        </span>
                                        <span class="mt-3 block font-[Inter] text-sm leading-relaxed text-white">
                                            {{ card.preview || 'Your completion ceremony is waiting.' }}
                                        </span>
                                    </button>
                                </div>

                                <div
                                    v-else
                                    class="rounded-2xl border border-dashed border-white/15 px-5 py-8 text-center"
                                >
                                    <p class="font-[Inter] text-sm font-semibold text-white">
                                        {{ cardFilter === 'story' ? 'No story ending yet' : 'No episode cards yet' }}
                                    </p>
                                    <p class="mt-1.5 text-sm text-[#a4b0b3]">
                                        {{
                                            cardFilter === 'story'
                                                ? 'Finish the last episode of this story to earn the ending card.'
                                                : 'Complete an episode to collect its card here.'
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div v-else-if="view === 'receipt' && selectedCard" class="pb-2">
                                <GameCompletionCeremony :receipt="selectedCard.receipt" keepsake />
                            </div>
                        </div>
                    </section>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.achievements-modal {
    height: min(92svh, 820px);
    background-color: #0a0d0e;
    background-image: linear-gradient(180deg, rgba(111, 175, 186, 0.16) 0%, rgb(10, 13, 14) 22%);
    box-shadow:
        0 -8px 60px rgba(0, 0, 0, 0.7),
        0 0 0 1px rgba(111, 175, 186, 0.1);
}

.achievement-story-card,
.achievement-card-tile {
    background: rgba(255, 255, 255, 0.035);
}

.achievements-overlay-enter-active,
.achievements-overlay-leave-active,
.achievements-panel-enter-active,
.achievements-panel-leave-active {
    transition:
        opacity 0.18s ease,
        transform 0.18s ease;
}

.achievements-overlay-enter-from,
.achievements-overlay-leave-to {
    opacity: 0;
}

.achievements-panel-enter-from,
.achievements-panel-leave-to {
    opacity: 0;
    transform: translateY(0.75rem) scale(0.98);
}
</style>
