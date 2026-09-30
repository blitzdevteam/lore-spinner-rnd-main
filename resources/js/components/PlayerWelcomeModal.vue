<script setup lang="ts">
import BaseButton from '@/components/BaseButton.vue';
import { computed, nextTick, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    firstName?: string;
    planName?: string;
    actionsLimit?: number;
    voiceMinutes?: number;
}>();

const emit = defineEmits<{
    close: [];
}>();

const continueButton = ref<{ $el?: HTMLElement } | null>(null);

const greeting = computed(() => {
    const name = props.firstName?.trim();

    return name ? `Hello, ${name}` : 'Hello';
});

function close(): void {
    emit('close');
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        event.preventDefault();
        close();
    }
}

function setModalState(isOpen: boolean): void {
    if (typeof document === 'undefined') {
        return;
    }

    if (isOpen) {
        document.body.classList.add('overflow-hidden');
        window.addEventListener('keydown', onKeydown);

        void nextTick(() => {
            const element = continueButton.value?.$el;

            if (element instanceof HTMLElement) {
                element.focus({ preventScroll: true });
            }
        });

        return;
    }

    document.body.classList.remove('overflow-hidden');
    window.removeEventListener('keydown', onKeydown);
}

watch(
    () => props.open,
    (isOpen) => {
        setModalState(isOpen);
    },
    { immediate: true },
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
        <Transition name="player-welcome">
            <div
                v-if="open"
                class="fixed inset-0 z-[400] flex items-center justify-center bg-black/35 p-4 backdrop-blur-xl sm:p-6"
                role="dialog"
                aria-modal="true"
                aria-labelledby="player-welcome-title"
                aria-describedby="player-welcome-description"
                @click="close"
            >
                <section
                    class="player-welcome-panel relative z-10 flex w-full max-w-md flex-col gap-6 rounded-2xl bg-black/20 px-5 py-7 text-center shadow-[0_25px_120px_rgba(0,0,0,0.72)] backdrop-blur-2xl sm:px-8 sm:py-8"
                    @click.stop
                >
                    <div class="flex flex-col items-center gap-3">
                        <h2 id="player-welcome-title" class="font-marcellus-sc text-2xl leading-tight text-white sm:text-3xl">
                            {{ greeting }}
                        </h2>
                        <p id="player-welcome-description" class="text-sm leading-relaxed text-white/60">
                            Welcome to LoreSpinner. You're not just listening to a story. You're inside one.
                        </p>
                    </div>

                    <dl
                        v-if="planName"
                        class="flex flex-col gap-3 rounded-xl border border-white/10 bg-white/5 px-4 py-4 text-left"
                    >
                        <div class="flex items-baseline justify-between gap-4">
                            <dt class="text-xs tracking-[0.04em] text-white/50 uppercase">Plan</dt>
                            <dd class="text-right text-sm font-medium text-[#ffbe58]">{{ planName }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-4">
                            <dt class="text-xs tracking-[0.04em] text-white/50 uppercase">Actions</dt>
                            <dd class="text-right text-sm font-medium text-[#59c1ca]">{{ actionsLimit ?? 0 }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-4">
                            <dt class="text-xs tracking-[0.04em] text-white/50 uppercase">Voice</dt>
                            <dd class="text-right text-sm font-medium text-white">{{ voiceMinutes ?? 0 }} minutes</dd>
                        </div>
                    </dl>

                    <BaseButton ref="continueButton" type="button" severity="primary" class="w-full" @click="close">Continue</BaseButton>
                </section>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.player-welcome-enter-active,
.player-welcome-leave-active {
    transition: opacity 220ms ease;
}

.player-welcome-enter-from,
.player-welcome-leave-to {
    opacity: 0;
}

.player-welcome-panel {
    transition: transform 220ms ease;
}

.player-welcome-enter-from .player-welcome-panel,
.player-welcome-leave-to .player-welcome-panel {
    transform: translateY(0.75rem) scale(0.985);
}

@media (prefers-reduced-motion: reduce) {
    .player-welcome-enter-active,
    .player-welcome-leave-active,
    .player-welcome-panel {
        transition: none;
    }
}
</style>
