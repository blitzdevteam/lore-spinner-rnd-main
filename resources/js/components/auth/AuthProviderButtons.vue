<script setup lang="ts">
import appleIcon from '@/assets/auth/apple.svg';
import dividerIcon from '@/assets/auth/divider.svg';
import googleIcon from '@/assets/auth/google.svg';
</script>

<template>
    <div class="flex w-full flex-col gap-7">
        <div class="grid w-full grid-cols-2 gap-[1.125rem]">
            <button type="button" class="auth-provider-btn">
                <img :src="googleIcon" alt="" />
                <span>Google</span>
            </button>
            <button type="button" class="auth-provider-btn">
                <img :src="appleIcon" alt="" />
                <span>Apple</span>
            </button>
        </div>
        <div class="flex w-full items-center gap-3.5">
            <img :src="dividerIcon" alt="" class="h-px min-w-0 flex-1" />
            <span class="font-inter text-base text-[#585858]">Or</span>
            <img :src="dividerIcon" alt="" class="h-px min-w-0 flex-1" />
        </div>
    </div>
</template>

<style scoped>
.auth-provider-btn {
    display: flex;
    height: 3.5rem;
    align-items: center;
    justify-content: center;
    gap: 5px;
    border-radius: 10px;
    border: 2px solid #191919;
    background: transparent;
    color: #585858;
    font-family: Inter, sans-serif;
    font-size: 1rem;
    font-weight: 400;
}
</style>
