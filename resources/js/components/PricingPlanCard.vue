<script setup lang="ts">
import actionInfo from '@/assets/pricing/action-info.svg';
import cardDivider from '@/assets/pricing/card-divider.svg';
import checkTeal from '@/assets/pricing/check-teal.svg';

defineProps<{
    name: string;
    tagline: string;
    price: string;
    bonus: string | null;
    description: string;
    actionAllowance: string;
    actionHint: string | null;
    voiceAllowance: string;
    features: readonly string[];
    cta: string;
    href: string;
    icon: string;
    featured?: boolean;
    badge: string | null;
}>();
</script>

<template>
    <article class="pricing-card font-inter" :class="{ 'pricing-card--featured': featured }">
        <div v-if="featured && badge" class="pricing-card__badge">
            <p class="text-center text-xs leading-normal font-medium text-black">{{ badge }}</p>
        </div>

        <div class="pricing-card__body">
            <div class="flex flex-col gap-5">
                <div class="flex flex-col items-center gap-4">
                    <div class="flex w-full flex-col gap-5">
                        <div class="flex items-center justify-center gap-2.5">
                            <div
                                class="relative size-[70px] shrink-0 overflow-hidden rounded-full border-[0.5px]"
                                :class="featured ? 'border-primary' : 'border-[#373737]'"
                            >
                                <img :src="icon" alt="" class="size-full object-cover" width="70" height="70" />
                            </div>
                            <div class="flex min-w-0 flex-col gap-2">
                                <h2
                                    class="font-marcellus-sc text-[1.875rem] leading-[33px] capitalize"
                                    :class="featured ? 'text-primary' : 'text-white'"
                                >
                                    {{ name }}
                                </h2>
                                <p v-if="tagline" class="text-sm leading-normal text-[#8f8f8f]">{{ tagline }}</p>
                            </div>
                        </div>

                        <div class="flex h-11 items-center gap-2.5" :class="bonus ? 'justify-between' : 'justify-center'">
                            <div class="flex items-end gap-1 whitespace-nowrap">
                                <span class="text-[2.1875rem] leading-none font-semibold tracking-[-0.35px] text-white">{{ price }}</span>
                                <span class="text-sm leading-[44px] tracking-[-0.28px] text-[#8f8f8f]">/month</span>
                            </div>
                            <span
                                v-if="bonus"
                                class="inline-flex h-6 shrink-0 items-center justify-center rounded-full bg-[#1d170c] px-1.5 text-sm leading-normal text-[#ffbe58]"
                            >
                                {{ bonus }}
                            </span>
                        </div>
                    </div>

                    <p
                        v-if="description"
                        class="min-h-[3.25rem] w-full text-center text-sm leading-[26px] tracking-[-0.14px] text-[#8f8f8f] opacity-80"
                    >
                        {{ description }}
                    </p>
                </div>

                <div class="flex flex-col gap-[18px] opacity-80">
                    <img :src="cardDivider" alt="" class="h-px w-full" aria-hidden="true" />

                    <div class="flex flex-col gap-3.5 px-2.5">
                        <div>
                            <p class="text-base leading-normal font-medium tracking-[-0.16px] text-white">{{ actionAllowance }}</p>
                            <div class="flex items-center gap-2">
                                <p class="text-[13px] leading-normal tracking-[-0.13px] text-white">Actions / month</p>
                                <button v-if="actionHint" type="button" class="pricing-card__hint" :aria-label="actionHint">
                                    <img :src="actionInfo" alt="" width="16" height="16" />
                                    <span class="pricing-card__hint-bubble" role="tooltip">{{ actionHint }}</span>
                                </button>
                                <img v-else :src="actionInfo" alt="" width="16" height="16" aria-hidden="true" />
                            </div>
                        </div>

                        <div>
                            <p class="text-base leading-normal font-medium tracking-[-0.16px] text-white">{{ voiceAllowance }}</p>
                            <p class="text-[13px] leading-normal tracking-[-0.13px] text-white">Voice Narration / month</p>
                        </div>
                    </div>

                    <img :src="cardDivider" alt="" class="h-px w-full" aria-hidden="true" />

                    <ul class="flex flex-col gap-[18px]">
                        <li v-for="feature in features" :key="feature" class="flex items-center gap-2">
                            <img :src="checkTeal" alt="" width="22" height="22" class="shrink-0" />
                            <span class="text-[13px] leading-normal tracking-[-0.13px] text-white">{{ feature }}</span>
                        </li>
                    </ul>
                </div>
            </div>

            <a :href="href" class="pricing-card__cta" :class="featured ? 'pricing-card__cta--filled' : 'pricing-card__cta--outline'">
                {{ cta }}
            </a>
        </div>
    </article>
</template>

<style scoped>
.pricing-card {
    position: relative;
    display: flex;
    width: 100%;
    min-width: 0;
    height: 100%;
    flex-direction: column;
}

.pricing-card__body {
    position: relative;
    z-index: 1;
    display: flex;
    height: 100%;
    min-height: 0;
    flex-direction: column;
    justify-content: space-between;
    gap: 1.25rem;
    border: 0.5px solid #373737;
    border-radius: 22px;
    background: #000;
    padding: 1.25rem 0.625rem;
}

.pricing-card__body::before {
    content: '';
    position: absolute;
    inset: 0;
    z-index: 2;
    border-radius: inherit;
    padding: 1.5px;
    background: conic-gradient(
        from var(--pricing-flow-angle),
        transparent 0deg,
        #59c1ca 48deg,
        transparent 108deg,
        transparent 180deg,
        #59c1ca 228deg,
        transparent 300deg
    );
    opacity: 0;
    pointer-events: none;
    -webkit-mask:
        linear-gradient(#000 0 0) content-box,
        linear-gradient(#000 0 0);
    -webkit-mask-composite: xor;
    mask:
        linear-gradient(#000 0 0) content-box,
        linear-gradient(#000 0 0);
    mask-composite: exclude;
    animation: pricing-border-flow 2.6s linear infinite;
    animation-play-state: paused;
    transition: opacity 200ms ease;
}

.pricing-card:hover .pricing-card__body::before,
.pricing-card:focus-within .pricing-card__body::before {
    opacity: 1;
    animation-play-state: running;
}

@property --pricing-flow-angle {
    syntax: '<angle>';
    inherits: false;
    initial-value: 0deg;
}

@keyframes pricing-border-flow {
    to {
        --pricing-flow-angle: 360deg;
    }
}

.pricing-card--featured {
    z-index: 1;
}

.pricing-card__badge {
    pointer-events: none;
    position: absolute;
    inset-inline: 0;
    top: 0;
    z-index: 0;
    display: flex;
    height: 57px;
    align-items: flex-start;
    justify-content: center;
    border-radius: 20px 20px 0 0;
    background: #59c1ca;
    padding-top: 0.25rem;
    transform: translateY(-25px);
}

.pricing-card__hint {
    position: relative;
    display: inline-flex;
    width: 16px;
    height: 16px;
    flex-shrink: 0;
    cursor: help;
}

.pricing-card__hint-bubble {
    position: absolute;
    bottom: calc(100% + 8px);
    left: 50%;
    z-index: 5;
    width: max-content;
    max-width: 14rem;
    border: 0.5px solid #373737;
    border-radius: 8px;
    background: #1e1e1e;
    padding: 8px 10px;
    color: #fff;
    font-size: 12px;
    font-weight: 400;
    line-height: 1.4;
    text-align: left;
    letter-spacing: normal;
    opacity: 0;
    pointer-events: none;
    transform: translateX(-50%);
    transition: opacity 160ms ease;
}

.pricing-card__hint:hover .pricing-card__hint-bubble,
.pricing-card__hint:focus-visible .pricing-card__hint-bubble {
    opacity: 1;
}

.pricing-card__cta {
    display: flex;
    height: 2.5rem;
    width: calc(100% - 1.25rem);
    align-items: center;
    justify-content: center;
    align-self: center;
    border-radius: 10px;
    border-style: solid;
    font-size: 0.875rem;
    line-height: normal;
    text-align: center;
    transition:
        background-color 180ms ease,
        filter 180ms ease;
}

.pricing-card__cta--outline {
    border-width: 1px;
    border-color: #59c1ca;
    background: #0e1f22;
    color: #fff;
    font-weight: 500;
}

.pricing-card__cta--outline:hover {
    background: #163f46;
}

.pricing-card__cta--filled {
    border-width: 0.5px;
    border-color: #59c1ca;
    background: #59c1ca;
    color: #000;
    font-weight: 600;
}

.pricing-card__cta--filled:hover {
    filter: brightness(1.08);
}

@media (prefers-reduced-motion: reduce) {
    .pricing-card__cta,
    .pricing-card__hint-bubble,
    .pricing-card__body::before {
        transition: none;
        animation: none;
    }
}
</style>
