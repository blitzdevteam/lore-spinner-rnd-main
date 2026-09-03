<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{
    html: string;
}>();

// Streaming can deliver dozens of small deltas per frame. Rebuilding a recursive
// VNode tree and one animated span per word for every delta blocks WebKit's main
// thread long enough for Safari to report an unresponsive page. Use the same
// native HTML path as completed narration and publish at most one update/frame.
const renderedHtml = ref(props.html);
let frameId: number | null = null;

function flushHtml(): void {
    frameId = null;
    renderedHtml.value = props.html;
}

function scheduleHtmlUpdate(): void {
    if (typeof window === 'undefined') {
        renderedHtml.value = props.html;
        return;
    }

    if (frameId !== null) {
        return;
    }

    frameId = window.requestAnimationFrame(flushHtml);
}

watch(() => props.html, scheduleHtmlUpdate);

onBeforeUnmount(() => {
    if (frameId !== null && typeof window !== 'undefined') {
        window.cancelAnimationFrame(frameId);
    }
});
</script>

<template>
    <div v-html="renderedHtml" />
</template>
