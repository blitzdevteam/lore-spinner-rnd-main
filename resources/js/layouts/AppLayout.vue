<script setup lang="ts">
import errorIcon from '@/assets/notifications/error.svg';
import infoIcon from '@/assets/notifications/info.svg';
import successIcon from '@/assets/notifications/success.svg';
import warningIcon from '@/assets/notifications/warning.svg';
import FeedbackWidget from '@/components/FeedbackWidget.vue';
import InvestorWelcomeModal from '@/components/InvestorWelcomeModal.vue';
import PlayerWelcomeModal from '@/components/PlayerWelcomeModal.vue';
import { usePage } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { Toaster, toast } from 'vue-sonner';
import 'vue-sonner/style.css';

const page = usePage();

const flash = computed(() => page.props.flash);
const investorWelcome = computed(() => flash.value.investor_welcome ?? null);
const investorWelcomeOpen = ref(false);
const playerWelcome = computed(() => flash.value.player_welcome ?? null);
const playerWelcomeOpen = ref(false);

watch(
    investorWelcome,
    (welcome) => {
        investorWelcomeOpen.value = welcome !== null;
    },
    { immediate: true },
);

watch(
    playerWelcome,
    (welcome) => {
        playerWelcomeOpen.value = welcome !== null;
    },
    { immediate: true },
);

onMounted(() => {
    watch(
        flash,
        () => {
            const options = {
                closeButton: false,
            };
            const { success, error, warning } = flash.value;

            nextTick(() => {
                success?.forEach((message) => toast.success(message, options));
                error?.forEach((message) => toast.error(message, options));
                warning?.forEach((message) => toast.warning(message, options));
            });
        },
        {
            immediate: true,
            deep: true,
        },
    );
});
</script>

<template>
    <Toaster position="top-center" theme="dark" :style="{ '--width': 'min(347.5px, calc(100vw - 2rem))' }">
        <template #success-icon>
            <img :src="successIcon" alt="" width="18" height="18" />
        </template>
        <template #info-icon>
            <img :src="infoIcon" alt="" width="18" height="18" />
        </template>
        <template #error-icon>
            <img :src="errorIcon" alt="" width="18" height="18" />
            <span data-toast-label>ERROR</span>
        </template>
        <template #warning-icon>
            <img :src="warningIcon" alt="" width="18" height="18" />
            <span data-toast-label>Attention</span>
        </template>
    </Toaster>
    <slot />
    <InvestorWelcomeModal
        :open="investorWelcomeOpen"
        :plan-name="investorWelcome?.plan_name ?? ''"
        :duration="investorWelcome?.duration ?? ''"
        :expires-on="investorWelcome?.expires_on ?? ''"
        @close="investorWelcomeOpen = false"
    />
    <PlayerWelcomeModal
        :open="playerWelcomeOpen"
        :first-name="playerWelcome?.first_name ?? ''"
        :plan-name="playerWelcome?.plan_name"
        :actions-limit="playerWelcome?.actions_limit"
        :voice-minutes="playerWelcome?.voice_minutes"
        @close="playerWelcomeOpen = false"
    />
    <FeedbackWidget />
</template>

<style>
@reference "../../css/app.css";

@layer components {
    [data-sonner-toaster] {
        @apply font-inter!;
    }

    [data-sonner-toast][data-styled='true'] {
        @apply grid! items-center! gap-x-1.5! gap-y-0! rounded-xl! border-[0.5px]! border-gray-700! bg-black! p-2.5! font-inter! text-[13px]! font-normal! text-white! shadow-none! outline-none!;
        grid-template-columns: 18px minmax(0, 1fr) auto;

        &:focus-visible {
            @apply shadow-none!;
        }

        [data-icon] {
            @apply col-start-1 row-start-1 m-0! flex! size-[18px]! items-center! justify-center! p-0!;

            img {
                @apply block! size-[18px]! max-w-none! shrink-0!;
            }
        }

        [data-content] {
            display: contents !important;
        }

        [data-title] {
            @apply col-start-2 row-start-1 min-w-0 text-[13px]! leading-[18px]! font-normal! text-white!;
        }

        [data-description] {
            @apply col-span-full row-start-2 text-[13px]! leading-[1.45]! font-normal! text-[#8f8f8f]!;
        }

        [data-button] {
            @apply col-start-3 row-start-1 m-0! h-auto! cursor-pointer! rounded-none! border-0! bg-transparent! p-0! text-[13px]! leading-[18px]! font-normal! whitespace-nowrap! text-primary! shadow-none!;
        }

        [data-button]:hover {
            @apply opacity-80;
        }
    }

    [data-sonner-toast][data-styled='true'][data-type='info'] {
        @apply items-start! gap-y-1!;
    }

    [data-sonner-toast][data-styled='true'][data-type='error'],
    [data-sonner-toast][data-styled='true'][data-type='warning'] {
        @apply items-start!;

        [data-icon] {
            display: contents !important;

            img {
                @apply col-start-1 row-start-1;
            }
        }

        [data-toast-label] {
            @apply col-start-2 row-start-1 text-[13px]! leading-[18px]! font-normal! whitespace-nowrap!;
        }

        [data-title],
        [data-button] {
            @apply row-start-2 mt-2.5!;
        }

        [data-title] {
            grid-column: 1 / 3;
            @apply text-sm! leading-5! font-medium! text-white!;
        }

        [data-description] {
            @apply row-start-3 mt-1.5!;
        }
    }

    [data-sonner-toast][data-styled='true'][data-type='error'] [data-toast-label] {
        @apply text-[#b64b4b]!;
    }

    [data-sonner-toast][data-styled='true'][data-type='warning'] [data-toast-label] {
        @apply text-secondary-300!;
    }

    [data-sonner-toast][data-expanded='false'][data-front='false'][data-styled='true'] [data-title],
    [data-sonner-toast][data-expanded='false'][data-front='false'][data-styled='true'] [data-description],
    [data-sonner-toast][data-expanded='false'][data-front='false'][data-styled='true'] [data-toast-label],
    [data-sonner-toast][data-expanded='false'][data-front='false'][data-styled='true'] [data-icon] img,
    [data-sonner-toast][data-expanded='false'][data-front='false'][data-styled='true'] [data-button] {
        opacity: 0;
    }
}
</style>
