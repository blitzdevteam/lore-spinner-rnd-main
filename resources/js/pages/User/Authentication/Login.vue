<script setup lang="ts">
import BaseButton from '@/components/BaseButton.vue';
import BaseInputFormat from '@/components/BaseInputFormat.vue';
import AuthenticationLayout from '@/layouts/AuthenticationLayout.vue';
import { store } from '@/wayfinder/actions/App/Http/Controllers/User/Authentication/LoginController';
import forgotPassword from '@/wayfinder/routes/user/authentication/forgot-password';
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
                    <p v-for="message in successMessages" :key="message" class="text-center text-sm text-primary-300">
                        {{ message }}
                    </p>
                    <p v-for="message in errorMessages" :key="`error-${message}`" class="text-center text-sm text-red-400">
                        {{ message }}
                    </p>
                    <BaseInputFormat label="Email" :error="errors.email">
                        <PrimeInputText name="email" placeholder="Enter your email address" />
                    </BaseInputFormat>
                    <BaseInputFormat label="Password" :error="errors.password">
                        <PrimePassword name="password" placeholder="Enter your password" :feedback="false" toggle-mask />
                    </BaseInputFormat>
                </div>
            </template>
            <template #footer>
                <div class="flex w-full flex-col gap-5 sm:gap-8">
                    <div class="flex w-full flex-col gap-3 sm:gap-4">
                        <BaseButton :processing severity="primary" class="auth-submit-btn !w-full">Login</BaseButton>
                        <div class="flex justify-center">
                            <Link :href="forgotPassword.create.url()" class="text-sm text-gray-400 transition-colors hover:text-primary-300">Forgot password?</Link>
                        </div>
                    </div>
                    <div class="auth-social-grid w-full">
                        <button type="button" class="auth-social-btn">
                            <img src="@/assets/brands/google.svg" class="auth-social-btn__icon brightness-0 invert" alt="" />
                            <span class="auth-social-btn__label">Google</span>
                        </button>
                        <button type="button" class="auth-social-btn">
                            <img src="@/assets/brands/facebook.svg" class="auth-social-btn__icon" alt="" />
                            <span class="auth-social-btn__label">Facebook</span>
                        </button>
                        <button type="button" class="auth-social-btn">
                            <img src="@/assets/brands/apple.svg" class="auth-social-btn__icon brightness-0 invert" alt="" />
                            <span class="auth-social-btn__label">Apple</span>
                        </button>
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

.auth-social-grid {
    display: grid;
    width: 100%;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.625rem;
}

@media (min-width: 640px) {
    .auth-social-grid {
        gap: 1rem;
    }
}

.auth-social-btn {
    display: grid;
    min-height: 4.5rem;
    cursor: pointer;
    place-items: center;
    gap: 0.375rem;
    border-radius: 0.75rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(255, 255, 255, 0.04);
    padding: 0.75rem 0.5rem;
    transition:
        border-color 150ms ease,
        background-color 150ms ease,
        transform 150ms ease;
}

@media (min-width: 640px) {
    .auth-social-btn {
        min-height: 5.5rem;
    }
}

.auth-social-btn:hover {
    border-color: rgba(8, 206, 230, 0.35);
    background: rgba(255, 255, 255, 0.08);
}

.auth-social-btn:active {
    transform: scale(0.98);
}

.auth-social-btn__icon {
    width: 1.5rem;
    height: 1.5rem;
}

.auth-social-btn__label {
    font-size: 0.8125rem;
    font-weight: 500;
    color: rgb(229, 231, 235);
}
</style>
