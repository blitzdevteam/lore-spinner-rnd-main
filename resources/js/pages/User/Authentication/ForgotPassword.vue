<script setup lang="ts">
import BaseButton from '@/components/BaseButton.vue';
import BaseInputFormat from '@/components/BaseInputFormat.vue';
import AuthenticationLayout from '@/layouts/AuthenticationLayout.vue';
import { store } from '@/wayfinder/actions/App/Http/Controllers/User/Authentication/ForgotPasswordController';
import login from '@/wayfinder/routes/user/authentication/login';
import { Form, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const successMessages = computed(() => page.props.flash?.success ?? []);
const errorMessages = computed(() => page.props.flash?.error ?? []);
</script>

<template>
    <Form :action="store()" #default="{ errors, processing }">
        <AuthenticationLayout>
            <template #body>
                <div class="flex w-full flex-col gap-4">
                    <div class="flex flex-col gap-1 text-center">
                        <p class="text-lg font-medium text-white">Reset your password</p>
                        <p class="text-sm text-gray-400">Enter your email and we will send you a reset link.</p>
                    </div>
                    <p v-for="message in successMessages" :key="message" class="text-center text-sm text-primary-300">
                        {{ message }}
                    </p>
                    <p v-for="message in errorMessages" :key="message" class="text-center text-sm text-red-400">
                        {{ message }}
                    </p>
                    <BaseInputFormat label="Email" :error="errors.email">
                        <PrimeInputText name="email" type="email" autocomplete="email" placeholder="Enter your email address" />
                    </BaseInputFormat>
                </div>
            </template>
            <template #footer>
                <div class="flex w-full flex-col gap-3 sm:gap-4">
                    <BaseButton :processing severity="primary" class="auth-submit-btn !w-full">Send reset link</BaseButton>
                    <div class="flex justify-center">
                        <Link :href="login.create.url()" class="text-sm text-gray-400 transition-colors hover:text-primary-300">Back to login</Link>
                    </div>
                </div>
            </template>
        </AuthenticationLayout>
    </Form>
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
