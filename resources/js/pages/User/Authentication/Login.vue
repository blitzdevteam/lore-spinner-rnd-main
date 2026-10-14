<script setup lang="ts">
import logoTag from '@/assets/logo/logo-tag.png';
import AuthField from '@/components/auth/AuthField.vue';
import AuthProviderButtons from '@/components/auth/AuthProviderButtons.vue';
import AuthSplitLayout from '@/components/auth/AuthSplitLayout.vue';
import { store } from '@/wayfinder/actions/App/Http/Controllers/User/Authentication/LoginController';
import { index } from '@/wayfinder/routes';
import forgotPassword from '@/wayfinder/routes/user/authentication/forgot-password';
import register from '@/wayfinder/routes/user/authentication/register';
import { Form, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const successMessages = computed(() => page.props.flash?.success ?? []);
const errorMessages = computed(() => page.props.flash?.error ?? []);
const mobileFormOpen = ref(false);
const logoTagCss = `url("${logoTag}")`;

const fieldClass =
    'h-10 w-full rounded-[10px] border-0 bg-[#191919] px-4 font-inter text-sm text-white outline-none focus:ring-2 focus:ring-[#59c1ca]/50 lg:h-14 lg:bg-[#161616] lg:text-base';

function mobilePresentation(errors: Partial<Record<string, string>>): 'gate' | 'sheet' {
    if (mobileFormOpen.value || Boolean(errors.email) || Boolean(errors.password)) {
        return 'sheet';
    }

    return 'gate';
}
</script>

<template>
    <Form :action="store()" #default="{ errors, processing }">
        <AuthSplitLayout :back-href="index().url" :mobile="mobilePresentation(errors)">
            <div v-if="mobilePresentation(errors) === 'gate'" class="auth-gate lg:hidden">
                <div class="auth-gate__logo" role="img" aria-label="LoreSpinner">
                    <div class="auth-gate__mark" />
                    <div class="auth-gate__word" />
                </div>
                <div class="auth-gate__messages">
                    <p v-for="message in successMessages" :key="message" class="text-center font-inter text-sm text-[#59c1ca]">
                        {{ message }}
                    </p>
                    <p
                        v-for="message in errorMessages"
                        :key="`error-${message}`"
                        class="text-center font-inter text-sm text-red-400"
                    >
                        {{ message }}
                    </p>
                </div>
                <div class="auth-gate__actions">
                    <button type="button" class="auth-gate__sign-in" @click="mobileFormOpen = true">Sign In</button>
                    <Link :href="register.create.url()" class="auth-gate__sign-up">Sign Up</Link>
                </div>
            </div>

            <div
                class="w-full flex-col items-center gap-6 lg:gap-[30px]"
                :class="mobilePresentation(errors) === 'gate' ? 'hidden lg:flex' : 'flex'"
            >
                <div class="flex w-full flex-col items-center gap-2.5 text-center lg:gap-5">
                    <h1 class="font-inter text-xl leading-[35px] font-semibold text-white lg:text-2xl lg:leading-none lg:font-medium">
                        Sign In
                    </h1>
                    <div class="flex w-full flex-col gap-2 lg:gap-2.5">
                        <p class="font-inter text-sm text-[#ffbe58] lg:text-lg">Continue Your Story</p>
                        <p class="font-inter text-xs leading-5 text-[#585858] lg:text-base lg:leading-[22px] lg:text-[#83b1ce]">
                            Your adventures have been waiting for you. Pick up where you left off.
                        </p>
                    </div>
                </div>

                <p v-for="message in successMessages" :key="message" class="text-center font-inter text-sm text-[#59c1ca]">
                    {{ message }}
                </p>
                <p v-for="message in errorMessages" :key="`error-${message}`" class="text-center font-inter text-sm text-red-400">
                    {{ message }}
                </p>

                <AuthProviderButtons />

                <div class="flex w-full flex-col gap-3.5 lg:gap-5">
                    <div class="flex w-full flex-col gap-3.5 lg:gap-5">
                        <AuthField label="Email Address" :error="errors.email">
                            <input name="email" type="email" autocomplete="email" :class="fieldClass" />
                        </AuthField>
                        <div class="flex w-full flex-col gap-1">
                            <AuthField label="Password" :error="errors.password">
                                <input name="password" type="password" autocomplete="current-password" :class="fieldClass" />
                            </AuthField>
                            <p class="font-inter text-xs text-[#585858] lg:hidden">Must be at least 8 characters</p>
                            <Link
                                :href="forgotPassword.create.url()"
                                class="text-center font-inter text-[13px] text-[#59c1ca] lg:hidden"
                            >
                                Forgot Password?
                            </Link>
                            <div class="hidden items-start justify-between gap-3 lg:flex">
                                <p class="font-inter text-[13px] text-[#585858]">Must be at least 8 characters</p>
                                <Link :href="forgotPassword.create.url()" class="shrink-0 font-inter text-[13px] text-[#585858] underline">
                                    Forgot password?
                                </Link>
                            </div>
                        </div>
                    </div>

                    <div class="flex w-full flex-col items-center gap-3.5 lg:gap-5">
                        <button type="submit" class="auth-submit" :disabled="processing">Sign In</button>
                        <p class="text-center font-inter text-sm text-[#585858] lg:text-[15px]">
                            Don't have an account?
                            <Link :href="register.create.url()" class="font-semibold text-white underline">Sign up</Link>
                        </p>
                    </div>
                </div>
            </div>
        </AuthSplitLayout>
    </Form>
</template>

<style scoped>
.auth-gate {
    display: flex;
    min-height: 15.625rem;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
}

.auth-gate__logo {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}

.auth-gate__mark,
.auth-gate__word {
    background-image: v-bind(logoTagCss);
    background-repeat: no-repeat;
}

.auth-gate__mark {
    width: 83px;
    height: 78px;
    background-size: 484.74px 135.54px;
    background-position: -12.01px -24.81px;
}

.auth-gate__word {
    width: 169px;
    height: 34px;
    background-size: 229.63px 68.78px;
    background-position: -51.72px -16.09px;
}

.auth-gate__messages:empty {
    display: none;
}

.auth-gate__actions {
    display: flex;
    width: 100%;
    flex-direction: column;
    gap: 14px;
}

.auth-gate__sign-in,
.auth-gate__sign-up,
.auth-submit {
    display: flex;
    height: 3.125rem;
    width: 100%;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    font-family: Inter, sans-serif;
    font-size: 1rem;
}

.auth-gate__sign-in,
.auth-submit {
    border: 0.5px solid #59c1ca;
    background: #59c1ca;
    color: #0f0f0f;
    font-weight: 600;
    text-shadow: 0 0 35px #008ab8;
}

.auth-gate__sign-up {
    border-radius: 12px;
    color: #373737;
    font-weight: 500;
}

.auth-submit:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

@media (min-width: 1024px) {
    .auth-gate {
        display: none;
    }

    .auth-submit {
        border: 0.5px solid #59c1ca;
        font-weight: 500;
        color: #000;
        text-shadow: none;
    }
}
</style>
