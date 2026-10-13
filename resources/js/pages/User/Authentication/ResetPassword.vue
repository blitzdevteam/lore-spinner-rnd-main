<script setup lang="ts">
import AuthField from '@/components/auth/AuthField.vue';
import AuthSplitLayout from '@/components/auth/AuthSplitLayout.vue';
import { store } from '@/wayfinder/actions/App/Http/Controllers/User/Authentication/ResetPasswordController';
import login from '@/wayfinder/routes/user/authentication/login';
import { Form, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    token: string;
    email: string;
}>();

const page = usePage();
const errorMessages = computed(() => page.props.flash?.error ?? []);

const fieldClass =
    "h-14 w-full rounded-[10px] border-0 bg-[#161616] px-4 font-inter text-base text-white outline-none focus:ring-2 focus:ring-[#59c1ca]/50";
</script>

<template>
    <Form :action="store()" #default="{ errors, processing }">
        <AuthSplitLayout :back-href="login.create.url()">
            <div class="flex w-full flex-col items-center gap-10">
                <div class="flex w-full flex-col gap-3.5 text-center">
                    <h1 class="font-inter text-2xl font-medium leading-none text-white">Reset Password</h1>
                    <p class="font-inter text-base text-[#ffbe58]">
                        <template v-if="props.email">Choose a new password for {{ props.email }}.</template>
                        <template v-else>Choose a new password for your account.</template>
                    </p>
                </div>

                <p v-for="message in errorMessages" :key="message" class="text-center font-inter text-sm text-red-400">
                    {{ message }}
                </p>

                <div class="flex w-full flex-col gap-5">
                    <input type="hidden" name="token" :value="props.token" />
                    <input v-if="props.email" type="hidden" name="email" :value="props.email" />
                    <AuthField v-else label="Email Address" :error="errors.email">
                        <input name="email" type="email" autocomplete="email" :class="fieldClass" />
                    </AuthField>
                    <p v-if="props.email && errors.email" class="font-inter text-sm text-red-400">{{ errors.email }}</p>
                    <AuthField label="Password" :error="errors.password">
                        <input name="password" type="password" autocomplete="new-password" :class="fieldClass" />
                    </AuthField>
                    <div class="flex w-full flex-col gap-1.5">
                        <AuthField label="Confirm Password" :error="errors.password_confirmation">
                            <input
                                name="password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                :class="fieldClass"
                            />
                        </AuthField>
                        <p v-if="!errors.password" class="font-inter text-[13px] text-[#585858]">Must be at least 8 characters</p>
                    </div>
                </div>

                <button type="submit" class="auth-submit" :disabled="processing">Reset Password</button>
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
    font-weight: 500;
}

.auth-submit:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}
</style>
