<script setup lang="ts">
import { computed, Fragment, h, type VNode } from 'vue';

const props = defineProps<{
    html: string;
}>();

const rootNodes = computed<Node[]>(() => {
    if (typeof document === 'undefined') {
        return [];
    }

    const container = document.createElement('div');
    container.innerHTML = props.html;

    return Array.from(container.childNodes);
});

function renderTextNode(node: Node, path: string): VNode {
    const segments = (node.textContent ?? '').match(/\s+|\S+/g) ?? [];

    return h(
        Fragment,
        { key: path },
        segments.map((segment, index) =>
            h(
                'span',
                {
                    key: `${path}:${index}`,
                    class: /^\s+$/.test(segment) ? undefined : 'streaming-word',
                },
                segment,
            ),
        ),
    );
}

function renderHtmlNode(node: Node, path: string): VNode | null {
    if (node.nodeType === Node.TEXT_NODE) {
        return renderTextNode(node, path);
    }

    if (node.nodeType !== Node.ELEMENT_NODE) {
        return null;
    }

    const element = node as HTMLElement;
    const attributes = Object.fromEntries(Array.from(element.attributes).map((attribute) => [attribute.name, attribute.value]));
    const children = Array.from(element.childNodes)
        .map((child, index) => renderHtmlNode(child, `${path}.${index}`))
        .filter((child): child is VNode => child !== null);

    return h(element.tagName.toLowerCase(), { ...attributes, key: path }, children);
}

const StreamingContent = () =>
    h(
        Fragment,
        null,
        rootNodes.value.map((node, index) => renderHtmlNode(node, String(index))),
    );
</script>

<template>
    <StreamingContent />
</template>
