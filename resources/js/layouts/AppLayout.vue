<script setup lang="ts">
import FeedbackWidget from '@/components/FeedbackWidget.vue';
import { usePage } from '@inertiajs/vue3';
import { Check, CircleAlert, X } from 'lucide-vue-next';
import { computed, nextTick, onMounted, watch } from 'vue';
import { Toaster, toast } from 'vue-sonner';
import 'vue-sonner/style.css';

const page = usePage();

const flash = computed(() => page.props.flash);

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
    <Toaster position="top-center">
        <template #success-icon>
            <Check :strokeWidth="2.5" />
        </template>
        <template #error-icon>
            <X />
        </template>
        <template #warning-icon>
            <CircleAlert />
        </template>
    </Toaster>
    <slot />
    <FeedbackWidget />
</template>

<style>
@reference "../../css/app.css";

@layer components {
    [data-sonner-toast] {
        @apply flex! items-center! gap-2! rounded-xl! border-none! bg-gray-900/75! p-4! outline-4! backdrop-blur-xl;
        [data-icon] {
            all: unset !important;
            @apply m-0! flex! h-6! w-6! items-center! justify-center! rounded-md! p-0!;
            svg {
                @apply m-0! h-4! w-4! justify-center! p-0!;
            }
        }
        [data-content] {
            [data-title] {
                @apply font-light!;
            }
        }

        &[data-type='success'] {
            @apply text-primary-300! outline-primary-200/15!;

            [data-icon] {
                @apply bg-primary-400/30!;
            }
        }
        &[data-type='warning'] {
            @apply text-secondary-300! outline-secondary-300/10!;

            [data-icon] {
                @apply bg-secondary-400/30!;
            }
        }
        &[data-type='error'] {
            @apply text-red-500! outline-red-500/10!;

            [data-icon] {
                @apply bg-red-500/30!;
            }
        }
    }
}
</style>
