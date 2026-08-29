<script setup lang="ts">
import HomeLayout from '@/layouts/HomeLayout.vue';
import type { WorldInterface } from '@/types';
import { show as storyShow } from '@/wayfinder/routes/stories';
import { index as worldsIndex } from '@/wayfinder/routes/worlds';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps<{
    world: WorldInterface;
}>();

const stories = props.world.stories ?? [];
</script>

<template>
    <HomeLayout>
        <Head :title="world.title" />

        <main class="bg-black py-12 text-white md:py-16">
            <div class="container">
                <div class="container-content flex flex-col gap-8">
                    <header class="flex flex-col gap-3">
                        <Link :href="worldsIndex().url" class="text-sm text-primary">All worlds</Link>
                        <h1 class="font-inria-serif text-3xl font-bold md:text-4xl">{{ world.title }}</h1>
                        <p v-if="world.tagline" class="home-section-description">{{ world.tagline }}</p>
                        <p v-if="world.tags?.length" class="flex flex-wrap gap-x-3 text-sm text-[#d8d8d8]">
                            <span v-for="tag in world.tags" :key="tag">{{ tag }}</span>
                        </p>
                    </header>

                    <section class="flex flex-col gap-4">
                        <h2 class="text-lg font-semibold">Stories</h2>
                        <ul v-if="stories.length" class="flex flex-col gap-3">
                            <li v-for="story in stories" :key="story.id">
                                <Link :href="storyShow(story.slug).url" class="flex items-center gap-4 text-white hover:text-primary">
                                    <img
                                        v-if="story.cover"
                                        :src="story.cover"
                                        :alt="story.title"
                                        class="h-16 w-11 rounded object-cover"
                                    />
                                    <span>{{ story.title }}</span>
                                </Link>
                            </li>
                        </ul>
                        <p v-else class="text-[#8f8f8f]">This world has no published stories yet.</p>
                    </section>
                </div>
            </div>
        </main>
    </HomeLayout>
</template>
