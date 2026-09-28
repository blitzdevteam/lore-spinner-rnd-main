<script setup lang="ts">
import mysteriousStoriesImage from '@/assets/auth/mysterious-stories.jpg';
import whimsicalStoriesImage from '@/assets/auth/whimsical-stories.png';
import AuthField from '@/components/auth/AuthField.vue';
import AuthSplitLayout from '@/components/auth/AuthSplitLayout.vue';
import { store } from '@/wayfinder/actions/App/Http/Controllers/User/Authentication/ForgotPasswordController';
import login from '@/wayfinder/routes/user/authentication/login';
import { Form } from '@inertiajs/vue3';

defineProps<{
    resetLinkSent?: boolean;
    email?: string;
}>();

const fieldClass =
    "h-14 w-full rounded-[10px] border-0 bg-[#161616] px-4 font-inter text-base text-white outline-none focus:ring-2 focus:ring-[#59c1ca]/50";

const loginUrl = login.create.url();
</script>

<template>
    <AuthSplitLayout
        :image-src="resetLinkSent ? mysteriousStoriesImage : whimsicalStoriesImage"
        :panel-title="resetLinkSent ? 'Mysterious stories' : 'Whimsical stories'"
        :panel-subtitle="resetLinkSent ? 'Secrets. Questions. Obsession.' : 'Curiosity. Imagination. Delight.'"
        :shift-image="!resetLinkSent"
        :back-href="resetLinkSent ? undefined : loginUrl"
    >
        <div v-if="resetLinkSent" class="flex w-full flex-col gap-3.5 text-center">
            <h1 class="font-inter text-[2.125rem] font-medium leading-none text-white">Check Your Email</h1>
            <p class="font-inter text-base text-[#ffbe58]">
                We’ve sent a password reset link to your email. Open it to create a new password.
            </p>
        </div>

        <Form v-else :action="store()" class="flex w-full flex-col items-center gap-10" #default="{ errors, processing }">
            <div class="flex w-full flex-col gap-3.5 text-center">
                <h1 class="font-inter text-[2.125rem] font-medium leading-none text-white">Forgot Password?</h1>
                <p class="font-inter text-base text-[#ffbe58]">
                    Enter your email and we’ll send you a password reset link.
                </p>
            </div>

            <AuthField label="Email Address" :error="errors.email" class="w-full">
                <input name="email" type="email" autocomplete="email" :value="email" :class="fieldClass" />
            </AuthField>

            <div class="flex w-full flex-col items-center gap-5">
                <button type="submit" class="auth-submit" :disabled="processing">Send Reset Link</button>
                <p class="text-center font-inter text-[15px] text-[#585858]">
                    Didn't receive the email?
                    <button type="submit" class="font-semibold text-white underline" :disabled="processing">Resend Link</button>
                </p>
            </div>
        </Form>
    </AuthSplitLayout>
</template>

<style scoped>
.auth-submit {
    display: flex;
    height: 3.125rem;
    width: 100%;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    border: 0.5px solid #59c1ca;
    background: #59c1ca;
    color: #000;
    font-family: Inter, sans-serif;
    font-size: 1rem;
    font-weight: 600;
}

.auth-submit:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}
</style>
