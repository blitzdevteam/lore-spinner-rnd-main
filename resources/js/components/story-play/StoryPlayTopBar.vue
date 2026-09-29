<script setup lang="ts">
import StoryPlayTabPair from '@/components/story-play/StoryPlayTabPair.vue';
import StoryPlayGlassRoundButton from '@/components/story-play/StoryPlayGlassRoundButton.vue';
import { EllipsisVertical, LucideBookmark, LucideShare2 } from 'lucide-vue-next';
import { onUnmounted, ref, watch } from 'vue';

type Panel = 'details' | 'chapters';

defineProps<{
    tab: Panel;
    bookmarkFilled?: boolean;
    showGameMenu?: boolean;
}>();

const emit = defineEmits<{
    'update:tab': [value: Panel];
    bookmark: [];
    share: [];
    restart: [];
}>();

const menuOpen = ref(false);
const menuButton = ref<HTMLElement | null>(null);
const menuPanel = ref<HTMLElement | null>(null);
const menuStyle = ref({ top: '0px', right: '0px' });

function updateMenuPosition(): void {
    const button = menuButton.value;

    if (!button) {
        return;
    }

    const rect = button.getBoundingClientRect();

    menuStyle.value = {
        top: `${rect.bottom + 12}px`,
        right: `${Math.max(16, window.innerWidth - rect.right)}px`,
    };
}

function closeMenu(): void {
    menuOpen.value = false;
}

function toggleMenu(): void {
    menuOpen.value = !menuOpen.value;

    if (menuOpen.value) {
        updateMenuPosition();
    }
}

function chooseRestart(): void {
    closeMenu();
    emit('restart');
}

function onPointerDown(event: PointerEvent): void {
    const target = event.target;

    if (!(target instanceof Node)) {
        return;
    }

    if (menuButton.value?.contains(target) || menuPanel.value?.contains(target)) {
        return;
    }

    closeMenu();
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        closeMenu();
    }
}

watch(menuOpen, (open) => {
    if (typeof window === 'undefined') {
        return;
    }

    if (open) {
        window.addEventListener('pointerdown', onPointerDown);
        window.addEventListener('keydown', onKeydown);
        window.addEventListener('resize', updateMenuPosition);
        window.addEventListener('scroll', updateMenuPosition, true);

        return;
    }

    window.removeEventListener('pointerdown', onPointerDown);
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('resize', updateMenuPosition);
    window.removeEventListener('scroll', updateMenuPosition, true);
});

onUnmounted(() => {
    if (typeof window === 'undefined') {
        return;
    }

    window.removeEventListener('pointerdown', onPointerDown);
    window.removeEventListener('keydown', onKeydown);
    window.removeEventListener('resize', updateMenuPosition);
    window.removeEventListener('scroll', updateMenuPosition, true);
});
</script>

<template>
    <div class="contents">
        <div class="flex w-full flex-1! flex-wrap items-center justify-between gap-x-4 gap-y-4 overflow-visible">
        <StoryPlayTabPair :model-value="tab" @update:model-value="emit('update:tab', $event)" />

        <div class="flex items-center gap-[0.625rem]">
            <StoryPlayGlassRoundButton aria-label="Share" @click="$emit('share')">
                <LucideShare2 class="!size-5" :stroke-width="1.65" aria-hidden="true" />
            </StoryPlayGlassRoundButton>
            <StoryPlayGlassRoundButton aria-label="Bookmark" @click="$emit('bookmark')">
                <LucideBookmark
                    class="size-5 transition-colors duration-200"
                    :class="
                        bookmarkFilled
                            ? 'fill-primary-500 text-primary-500'
                            : 'text-white/85'
                    "
                    :stroke-width="1.65"
                    aria-hidden="true"
                />
            </StoryPlayGlassRoundButton>
            <div v-if="showGameMenu" ref="menuButton" class="relative">
                <StoryPlayGlassRoundButton
                    aria-label="Story actions"
                    :aria-expanded="menuOpen"
                    @click="toggleMenu"
                >
                    <EllipsisVertical class="!size-5 !text-[#59c1ca]" :stroke-width="2.25" aria-hidden="true" />
                </StoryPlayGlassRoundButton>
            </div>
        </div>

        <Teleport to="body">
            <div
                v-if="showGameMenu && menuOpen"
                ref="menuPanel"
                class="fixed z-[80] w-[10.5rem] rounded-[18px] bg-[#141414] px-3 py-4 shadow-[0_16px_50px_rgba(0,0,0,0.45)]"
                :style="menuStyle"
                role="menu"
                aria-label="Story actions"
            >
                <button
                    type="button"
                    role="menuitem"
                    class="flex h-11 w-full items-center justify-center font-['Inter',sans-serif] text-[18px] font-normal text-white"
                    @click="chooseRestart"
                >
                    Restart
                </button>
                <button
                    type="button"
                    role="menuitem"
                    class="flex h-11 w-full items-center justify-center font-['Inter',sans-serif] text-[18px] font-normal text-white"
                >
                    Archive
                </button>
            </div>
        </Teleport>
        </div>
    </div>
</template>
