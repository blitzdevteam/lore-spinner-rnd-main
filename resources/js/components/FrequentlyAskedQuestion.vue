<script setup lang="ts">
import FrequentlyAskedQuestionItem from '@/components/FrequentlyAskedQuestionItem.vue';
import SectionHeader from '@/components/SectionHeader.vue';
import { LucideChevronDown } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface FaqEntry {
    id: string;
    question: string;
    answer: string;
}

const faqs: FaqEntry[] = [
    {
        id: '1',
        question: 'What is LoreSpinner and how does it work?',
        answer: 'LoreSpinner is an interactive storytelling platform where you step inside authored story worlds and help shape what happens next. Each experience begins with a story created by human writers. From there, your choices and imagination guide the journey. You can choose a path, make decisions, or tell the story what you want to do, and the world responds in real time.',
    },
    {
        id: '2',
        question: 'Can readers edit or change characters in a story?',
        answer: "No. LoreSpinner is not a free-for-all story editor. You can make choices, explore different paths, and influence how the experience unfolds, but the core characters, world, and story integrity remain protected. Think of it as stepping inside a story, not rewriting someone else's book.",
    },
    {
        id: '3',
        question: 'How do I become a writer on LoreSpinner?',
        answer: 'LoreSpinner works with selected writers, creators, and story partners. If you are interested in creating with us, you can reach out through our community or contact channels. As we grow, we plan to open more ways for writers to bring stories into LoreSpinner.',
    },
    {
        id: '4',
        question: 'Can users generate images or artwork on the platform?',
        answer: 'Not in Alpha. LoreSpinner is focused on story, choice, and immersive narrative experiences. Visuals may appear as part of the world, memory, or journey, but the platform is not designed as an image-generation tool.',
    },
    {
        id: '5',
        question: 'Are my reading choices saved?',
        answer: 'Yes. LoreSpinner remembers key moments from your journey so the story can reflect where you have been, what you chose, and how your path unfolded. Your choices help shape the experience and may influence what you see when you return.',
    },
];

const sectionOpen = ref(false);
/** Index of the open accordion item while the section is expanded (matches Figma: first item open). */
const openItemIndex = ref(0);

watch(sectionOpen, (open) => {
    if (open) openItemIndex.value = 0;
});

function toggleSection() {
    sectionOpen.value = !sectionOpen.value;
}

function toggleItem(index: number) {
    openItemIndex.value = openItemIndex.value === index ? -1 : index;
}
</script>

<template>
    <div class="home-section-gap w-full">
        <SectionHeader
            title="Frequently Asked Questions"
            subtitle="Can’t find what you need? Visit our community forum, reach out on Twitter, or email our support team."
        />

        <!-- Collapsed: Show More -->
        <button
            v-if="!sectionOpen"
            type="button"
            class="flex w-fit shrink-0 items-center justify-center gap-2 rounded-[0.625rem] border-[0.5px] border-[#373737] bg-[#0f0f0f] py-2 pr-[0.9375rem] pl-[0.8125rem] transition-opacity hover:opacity-90"
            aria-expanded="false"
            @click="toggleSection"
        >
            <span class="flex h-[0.5625rem] w-[1.125rem] items-center justify-center text-white">
                <LucideChevronDown class="size-[1.125rem] shrink-0 stroke-[2]" aria-hidden="true" />
            </span>
            <span class="text-center text-base leading-normal font-normal text-white">Show More</span>
        </button>

        <!-- Expanded: glass panel -->
        <div v-else class="relative w-full overflow-hidden rounded-[0.875rem] shadow-[0px_4px_80px_0px_rgba(0,0,0,0.2)]">
            <span class="pointer-events-none absolute inset-0 rounded-[0.875rem]" style="background: var(--story-card-bg)" />
            <span
                class="pointer-events-none absolute inset-0 rounded-[0.875rem] bg-[rgba(30,30,30,0.25)] mix-blend-plus-lighter backdrop-blur-[3px]"
            />
            <span
                class="pointer-events-none absolute inset-0 rounded-[inherit] shadow-[inset_0.25px_0.5px_0.5px_0.25px_rgba(255,255,255,0.22),inset_-0.2px_-0.5px_0.15px_0.5px_rgba(255,255,255,0.05)]"
            />

            <div class="relative px-[1.125rem] pt-[4.5rem] pb-6">
                <button
                    type="button"
                    class="absolute top-px left-[0.8125rem] z-10 flex h-[3.1875rem] items-center justify-center gap-[0.625rem] text-primary hover:opacity-90"
                    aria-expanded="true"
                    @click="toggleSection"
                >
                    <span class="flex h-[0.5625rem] w-[1.125rem] items-center justify-center">
                        <LucideChevronDown class="size-[1.125rem] shrink-0 -rotate-180 stroke-[2]" aria-hidden="true" />
                    </span>
                    <span class="text-center text-[1rem] leading-normal font-medium">Show Less</span>
                </button>

                <div class="mx-auto flex w-full max-w-[61.375rem] flex-col gap-[0.625rem]">
                    <FrequentlyAskedQuestionItem
                        v-for="(faq, index) in faqs"
                        :key="faq.id"
                        :question="faq.question"
                        :answer="faq.answer"
                        :expanded="openItemIndex === index"
                        @toggle="toggleItem(index)"
                    />

                    <button
                        type="button"
                        class="w-full pt-1 text-center text-[0.875rem] leading-[2.0625rem] font-normal text-primary hover:underline"
                    >
                        View More
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
