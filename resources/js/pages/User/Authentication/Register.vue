<script setup lang="ts">
import epicStoriesImage from '@/assets/auth/epic-stories.png';
import AuthField from '@/components/auth/AuthField.vue';
import AuthProviderButtons from '@/components/auth/AuthProviderButtons.vue';
import AuthSplitLayout from '@/components/auth/AuthSplitLayout.vue';
import { store } from '@/wayfinder/actions/App/Http/Controllers/User/Authentication/RegisterController';
import login from '@/wayfinder/routes/user/authentication/login';
import { Form, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const password = ref('');

const fieldClass =
    "h-14 w-full rounded-[10px] border-0 bg-[#191919] px-4 font-inter text-base text-white outline-none focus:ring-2 focus:ring-[#59c1ca]/50";
</script>

<template>
    <Form :action="store()" #default="{ errors, processing }">
        <AuthSplitLayout
            :image-src="epicStoriesImage"
            panel-title="Epic stories"
            panel-subtitle="Legends. Conflict. Destiny."
        >
            <div class="flex w-full flex-col items-center gap-7">
                <h1 class="font-inter text-[2.125rem] font-medium leading-none text-white">Sign Up</h1>
                <div class="flex w-full flex-col gap-2.5 text-center">
                    <p class="font-inter text-[1.125rem] text-[#ffbe58]">Step Into The Story</p>
                    <p class="font-inter text-base text-[#83b1ce]">
                        Every story changes because of your imagination and choices.
                    </p>
                </div>

                <AuthProviderButtons />

                <div class="flex w-full flex-col gap-5">
                    <div class="grid w-full grid-cols-2 gap-[1.125rem]">
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
                        <p v-if="!errors.password" class="font-inter text-sm text-[#585858]">Must be at least 8 characters</p>
                    </div>
                </div>

                <div class="flex w-full flex-col items-center gap-5">
                    <button type="submit" class="auth-submit" :disabled="processing">Sign Up</button>
                    <p class="text-center font-inter text-[15px] text-[#585858]">
                        Already have an account?
                        <Link :href="login.create.url()" class="font-semibold text-white underline">Sign in</Link>
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
