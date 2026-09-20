<script setup lang="ts">
import BaseBackgroundGradient from '@/components/BaseBackgroundGradient.vue';
import BaseButton from '@/components/BaseButton.vue';
import BaseInputFormat from '@/components/BaseInputFormat.vue';
import BaseLogo from '@/components/BaseLogo.vue';
import StickyFooterLayout from '@/layouts/StickyFooterLayout.vue';
import { Form } from '@inertiajs/vue3';

defineProps<{
    firstName: string;
    submitUrl: string;
}>();
</script>

<template>
    <BaseBackgroundGradient />
    <StickyFooterLayout class="mx-auto max-w-124">
        <template #body>
            <div class="flex w-full flex-col items-center gap-6 sm:gap-8">
                <BaseLogo class="h-auto w-full max-w-[11.5rem] sm:max-w-[17rem]" />
                <div class="flex flex-col gap-2 text-center">
                    <p class="text-lg text-white">Welcome{{ firstName ? `, ${firstName}` : '' }}</p>
                    <p class="text-sm text-gray-400">Set the email and password you will use to access Lore Spinner.</p>
                </div>
            </div>
        </template>
        <template #footer>
            <Form :action="{ url: submitUrl, method: 'post' }" #default="{ errors, processing }">
                <div class="flex w-full flex-col gap-5 sm:gap-8">
                    <div class="flex w-full flex-col gap-4">
                        <BaseInputFormat label="Email" :error="errors.email">
                            <PrimeInputText name="email" placeholder="Enter your email address" />
                        </BaseInputFormat>
                        <BaseInputFormat label="Password" :error="errors.password">
                            <PrimePassword name="password" placeholder="Enter your password" :feedback="false" toggle-mask />
                        </BaseInputFormat>
                        <BaseInputFormat label="Confirm Password" :error="errors.password_confirmation">
                            <PrimePassword name="password_confirmation" placeholder="Confirm your password" :feedback="false" toggle-mask />
                        </BaseInputFormat>
                    </div>
                    <BaseButton :processing severity="primary" class="auth-submit-btn !w-full">Continue</BaseButton>
                </div>
            </Form>
        </template>
    </StickyFooterLayout>
</template>

<style scoped>
.auth-submit-btn {
    box-sizing: border-box;
    display: flex;
    width: 100%;
    max-width: 100%;
    height: 3rem;
    border-radius: 0.75rem;
    font-size: 1.0625rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    box-shadow: 0 4px 24px rgba(8, 206, 230, 0.2);
}

@media (min-width: 640px) {
    .auth-submit-btn {
        height: 3.25rem;
        font-size: 1.125rem;
    }
}
</style>
