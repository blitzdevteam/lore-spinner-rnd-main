<script setup lang="ts">
import BaseButton from '@/components/BaseButton.vue';
import { nextTick, onUnmounted, ref, watch } from 'vue';

const props = defineProps<{
    open: boolean;
    planName: string;
    duration: string;
    expiresOn: string;
}>();

const emit = defineEmits<{
    close: [];
}>();

const continueButton = ref<{ $el?: HTMLElement } | null>(null);

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
        <Transition name="investor-welcome">
            <div
                v-if="open"
                class="fixed inset-0 z-[400] flex items-center justify-center bg-black/35 p-4 backdrop-blur-xl sm:p-6"
                role="dialog"
                aria-modal="true"
                aria-labelledby="investor-welcome-title"
                aria-describedby="investor-welcome-description"
                @click="close"
            >
                <section
                    class="investor-welcome-panel relative z-10 flex w-full max-w-md flex-col gap-6 rounded-2xl bg-black/20 px-5 py-7 shadow-[0_25px_120px_rgba(0,0,0,0.72)] backdrop-blur-2xl sm:px-8 sm:py-8"
                    @click.stop
                >
                    <div class="flex flex-col items-center gap-2 text-center">
                        <h2 id="investor-welcome-title" class="font-marcellus-sc text-2xl leading-tight text-white sm:text-3xl">Congratulations</h2>
                        <p id="investor-welcome-description" class="text-sm leading-relaxed text-white/60">
                            You're signed in. Your investor access is ready.
                        </p>
                    </div>

                    <dl class="flex flex-col gap-3 rounded-xl border border-white/10 bg-white/5 px-4 py-4">
                        <div class="flex items-baseline justify-between gap-4">
                            <dt class="text-xs tracking-[0.04em] text-white/50 uppercase">Plan</dt>
                            <dd class="text-right text-sm font-medium text-primary">{{ planName }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-4">
                            <dt class="text-xs tracking-[0.04em] text-white/50 uppercase">Access length</dt>
                            <dd class="text-right text-sm font-medium text-white">{{ duration }}</dd>
                        </div>
                        <div v-if="expiresOn" class="flex items-baseline justify-between gap-4">
                            <dt class="text-xs tracking-[0.04em] text-white/50 uppercase">Expires</dt>
                            <dd class="text-right text-sm font-medium text-white">{{ expiresOn }}</dd>
                        </div>
                    </dl>

                    <BaseButton ref="continueButton" type="button" severity="primary" class="w-full" @click="close">Continue</BaseButton>
                </section>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.investor-welcome-enter-active,
.investor-welcome-leave-active {
    transition: opacity 220ms ease;
}

.investor-welcome-enter-from,
.investor-welcome-leave-to {
    opacity: 0;
}

.investor-welcome-panel {
    transition: transform 220ms ease;
}

.investor-welcome-enter-from .investor-welcome-panel,
.investor-welcome-leave-to .investor-welcome-panel {
    transform: translateY(0.75rem) scale(0.985);
}

@media (prefers-reduced-motion: reduce) {
    .investor-welcome-enter-active,
    .investor-welcome-leave-active,
    .investor-welcome-panel {
        transition: none;
    }
}
</style>
