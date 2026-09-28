<script setup lang="ts">
import adventurousStoriesImage from '@/assets/auth/adventurous-stories.png';
import AuthField from '@/components/auth/AuthField.vue';
import AuthProviderButtons from '@/components/auth/AuthProviderButtons.vue';
import AuthSplitLayout from '@/components/auth/AuthSplitLayout.vue';
import { store } from '@/wayfinder/actions/App/Http/Controllers/User/Authentication/LoginController';
import forgotPassword from '@/wayfinder/routes/user/authentication/forgot-password';
import register from '@/wayfinder/routes/user/authentication/register';
import { Form, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const successMessages = computed(() => page.props.flash?.success ?? []);
const errorMessages = computed(() => page.props.flash?.error ?? []);

const fieldClass =
    "h-14 w-full rounded-[10px] border-0 bg-[#161616] px-4 font-inter text-base text-white outline-none focus:ring-2 focus:ring-[#59c1ca]/50";
</script>

<template>
    <Form :action="store()" #default="{ errors, processing }">
        <AuthSplitLayout
            :image-src="adventurousStoriesImage"
            panel-title="Adventurous stories"
            panel-subtitle="Discovery. Danger. Wonder."
        >
            <div class="flex w-full flex-col items-center gap-7">
                <h1 class="font-inter text-[2.125rem] font-medium leading-none text-white">Sign In</h1>
                <div class="flex w-full flex-col gap-2.5 text-center">
                    <p class="font-inter text-[1.125rem] text-[#ffbe58]">Continue Your Story</p>
                    <p class="font-inter text-base text-[#83b1ce]">
                        Your adventures have been waiting for you. Pick up where you left off.
                    </p>
                </div>

                <p v-for="message in successMessages" :key="message" class="text-center font-inter text-sm text-[#59c1ca]">
                    {{ message }}
                </p>
                <p v-for="message in errorMessages" :key="`error-${message}`" class="text-center font-inter text-sm text-red-400">
                    {{ message }}
                </p>

                <AuthProviderButtons />

                <div class="flex w-full flex-col gap-5">
                    <AuthField label="Email Address" :error="errors.email">
                        <input name="email" type="email" autocomplete="email" :class="fieldClass" />
                    </AuthField>
                    <div class="flex w-full flex-col gap-1.5">
                        <AuthField label="Password" :error="errors.password">
                            <input name="password" type="password" autocomplete="current-password" :class="fieldClass" />
                        </AuthField>
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-inter text-sm text-[#585858]">Must be at least 8 characters</p>
                            <Link :href="forgotPassword.create.url()" class="shrink-0 font-inter text-sm text-[#585858] underline">
                                Forgot password?
                            </Link>
                        </div>
                    </div>
                </div>

                <div class="flex w-full flex-col items-center gap-5">
                    <button type="submit" class="auth-submit" :disabled="processing">Sign In</button>
                    <p class="text-center font-inter text-[15px] text-[#585858]">
                        Don't have an account?
                        <Link :href="register.create.url()" class="font-semibold text-white underline">Sign up</Link>
                    </p>
                </div>
            </div>
        </AuthSplitLayout>
    </Form>
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
