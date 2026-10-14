<script setup lang="ts">
import AuthField from '@/components/auth/AuthField.vue';
import AuthProviderButtons from '@/components/auth/AuthProviderButtons.vue';
import AuthSplitLayout from '@/components/auth/AuthSplitLayout.vue';
import { store } from '@/wayfinder/actions/App/Http/Controllers/User/Authentication/RegisterController';
import { index } from '@/wayfinder/routes';
import forgotPassword from '@/wayfinder/routes/user/authentication/forgot-password';
import login from '@/wayfinder/routes/user/authentication/login';
import { Form, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    returnTo?: string | null;
}>();

const password = ref('');

const fieldClass =
    'h-10 w-full rounded-[10px] border-0 bg-[#191919] px-4 font-inter text-sm text-white outline-none focus:ring-2 focus:ring-[#59c1ca]/50 lg:h-14 lg:text-base';
</script>

<template>
    <Form :action="store()" #default="{ errors, processing }">
        <AuthSplitLayout :back-href="index().url">
            <div class="flex w-full flex-col items-center gap-6 lg:gap-5">
                <input v-if="props.returnTo" type="hidden" name="return_to" :value="props.returnTo" />
                <div class="flex w-full flex-col items-center gap-2.5 text-center">
                    <h1 class="font-inter text-xl leading-[35px] font-semibold text-white lg:text-2xl lg:leading-none lg:font-medium">
                        Sign Up
                    </h1>
                    <div class="flex w-full flex-col gap-2 lg:gap-2.5">
                        <p class="font-inter text-sm text-[#ffbe58] lg:text-lg">Step Into The Story</p>
                        <p class="font-inter text-xs leading-5 text-[#585858] lg:text-base lg:leading-[22px] lg:text-[#83b1ce]">
                            Every story changes because of your imagination and choices.
                        </p>
                    </div>
                </div>

                <AuthProviderButtons />

                <div class="flex w-full flex-col gap-[30px] lg:gap-5">
                <div class="flex w-full flex-col gap-[14px]">
                    <div class="grid w-full grid-cols-1 gap-[14px] lg:grid-cols-2 lg:gap-[1.125rem]">
                        <AuthField label="First Name" :error="errors.first_name">
                            <input name="first_name" type="text" autocomplete="given-name" :class="fieldClass" />
                        </AuthField>
                        <AuthField label="Last Name" :error="errors.last_name">
                            <input name="last_name" type="text" autocomplete="family-name" :class="fieldClass" />
                        </AuthField>
                    </div>
                    <AuthField label="Email Address" :error="errors.email">
                        <input name="email" type="email" autocomplete="email" :class="fieldClass" />
                    </AuthField>
                    <div class="flex w-full flex-col gap-1.5">
                        <AuthField label="Password" :error="errors.password">
                            <input
                                v-model="password"
                                name="password"
                                type="password"
                                autocomplete="new-password"
                                :class="fieldClass"
                            />
                        </AuthField>
                        <input type="hidden" name="password_confirmation" :value="password" />
                        <p v-if="!errors.password" class="font-inter text-xs text-[#585858] lg:text-[13px]">
                            Must be at least 8 characters
                        </p>
                        <Link
                            :href="forgotPassword.create.url()"
                            class="text-center font-inter text-[13px] text-[#59c1ca] lg:hidden"
                        >
                            Forgot Password?
                        </Link>
                    </div>
                </div>

                <div class="flex w-full flex-col items-center gap-3.5 lg:gap-5">
                    <button type="submit" class="auth-submit" :disabled="processing">
                        <span class="lg:hidden">Sign up</span>
                        <span class="hidden lg:inline">Sign Up</span>
                    </button>
                    <p class="text-center font-inter text-sm text-[#585858] lg:text-[15px]">
                        Already have an account?
                        <Link :href="login.create.url()" class="font-semibold text-white underline">Sign in</Link>
                    </p>
                </div>
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
    color: #0f0f0f;
    font-family: Inter, sans-serif;
    font-size: 1rem;
    font-weight: 500;
    text-shadow: 0 0 35px #008ab8;
}

@media (min-width: 1024px) {
    .auth-submit {
        color: #000;
        text-shadow: none;
    }
}

.auth-submit:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}
</style>
