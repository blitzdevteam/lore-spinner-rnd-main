<script setup lang="ts">
import checkGold from '@/assets/pricing/check-gold.svg';
import checkTeal from '@/assets/pricing/check-teal.svg';
import chronicleGlowTl from '@/assets/pricing/chronicle-glow-tl.svg';
import dividerGold from '@/assets/pricing/divider-gold.svg';
import dividerTeal from '@/assets/pricing/divider-teal.svg';
import { computed } from 'vue';

export type PricingAccent = 'teal' | 'gold';

const props = defineProps<{
    name: string;
    tagline: string;
    price: string;
    description: string;
    features: readonly string[];
    cta: string;
    href: string;
    icon: string;
    accent: PricingAccent;
    featured?: boolean;
}>();

const cornerColor = computed(() => (props.accent === 'gold' ? '#fdd24f' : '#59c1ca'));

const maskGlowStyle = computed(() => ({
    backgroundColor: cornerColor.value,
    WebkitMaskImage: `url("${chronicleGlowTl}")`,
    maskImage: `url("${chronicleGlowTl}")`,
    WebkitMaskSize: '100% 100%',
    maskSize: '100% 100%',
    WebkitMaskRepeat: 'no-repeat',
    maskRepeat: 'no-repeat',
}));
</script>

<template>
    <article class="pricing-card" :class="featured ? 'pricing-card--featured' : `pricing-card--${accent}`">
        <template v-if="featured">
            <img
                :src="chronicleGlowTl"
                alt=""
                class="pricing-card__glow pricing-card__glow--tl shrink-0"
                width="164"
                height="194"
                aria-hidden="true"
            />
            <img
                :src="chronicleGlowTl"
                alt=""
                class="pricing-card__glow pricing-card__glow--br shrink-0"
                width="164"
                height="194"
                aria-hidden="true"
            />
        </template>
        <template v-else>
            <span class="pricing-card__glow pricing-card__glow--tl pricing-card__glow--mask" :style="maskGlowStyle" aria-hidden="true" />
            <span class="pricing-card__glow pricing-card__glow--br pricing-card__glow--mask" :style="maskGlowStyle" aria-hidden="true" />
        </template>

        <div class="relative z-[1] flex w-full flex-col">
            <div class="flex flex-col gap-5">
                <div class="flex flex-col gap-[1.625rem]">
                    <div class="flex flex-col gap-4">
                        <div class="flex flex-col gap-5">
                            <div class="flex min-h-[7.9375rem] flex-col items-center gap-3">
                                <div
                                    class="pricing-card__icon relative size-[70px] shrink-0 overflow-hidden rounded-full"
                                    :class="accent === 'gold' || featured ? 'pricing-card__icon--gold' : 'pricing-card__icon--muted'"
                                >
                                    <img
                                        :src="icon"
                                        alt=""
                                        class="absolute top-1/2 left-1/2 size-[45px] -translate-x-1/2 -translate-y-1/2 object-contain"
                                        width="45"
                                        height="45"
                                    />
                                </div>
                                <h2
                                    class="w-full text-center font-instrument-serif text-[2.125rem] leading-[33px] capitalize"
                                    :class="accent === 'gold' ? 'text-[#fdd24f]' : 'text-primary'"
                                >
                                    {{ name }}
                                </h2>
                            </div>
                            <p class="text-center text-sm leading-normal text-[#838383]">{{ tagline }}</p>
                        </div>

                        <div class="flex h-[3.625rem] items-end justify-center gap-1 whitespace-nowrap">
                            <span class="text-[3rem] leading-none font-semibold tracking-[-0.48px] text-white">{{ price }}</span>
                            <span class="pb-[2px] text-sm leading-[44px] tracking-[-0.28px] text-[#838383]">/monthly</span>
                        </div>
                    </div>

                    <div class="h-px w-full overflow-hidden">
                        <img :src="accent === 'gold' ? dividerGold : dividerTeal" alt="" class="h-px w-full object-cover" aria-hidden="true" />
                    </div>

                    <p class="text-center text-base leading-[1.625rem] tracking-[-0.16px] text-white opacity-80">
                        {{ description }}
                    </p>
                </div>

                <div class="flex items-center gap-3.5">
                    <span class="pricing-card__rule" :class="`pricing-card__rule--${accent}`" aria-hidden="true" />
                    <p class="shrink-0 text-[0.75rem] leading-[1.3125rem] tracking-[2.4px] text-white uppercase">what you will get</p>
                    <span class="pricing-card__rule pricing-card__rule--rtl" :class="`pricing-card__rule--${accent}`" aria-hidden="true" />
                </div>
            </div>

            <ul class="mt-5 mb-10 flex flex-col gap-[1.125rem] opacity-80">
                <li v-for="feature in features" :key="feature" class="flex items-center gap-2">
                    <span class="relative size-[22px] shrink-0 overflow-hidden">
                        <img
                            :src="accent === 'gold' ? checkGold : checkTeal"
                            alt=""
                            class="absolute inset-0 size-full object-contain"
                            width="22"
                            height="22"
                        />
                    </span>
                    <span class="text-base leading-normal tracking-[-0.16px] text-white">{{ feature }}</span>
                </li>
            </ul>

            <a :href="href" class="pricing-card__cta" :class="featured ? 'pricing-card__cta--teal' : `pricing-card__cta--${accent}`">
                {{ cta }}
            </a>
        </div>
    </article>
</template>

<style scoped>
.pricing-card {
    position: relative;
    z-index: 0;
    display: flex;
    width: 100%;
    min-width: 0;
    flex-direction: column;
    overflow: visible;
    border: 0.5px solid transparent;
    border-radius: 1.375rem;
    background: #0f0f0f;
    padding: 1.5rem;
    transition:
        transform 280ms ease,
        box-shadow 280ms ease;
}

.pricing-card:hover {
    z-index: 2;
    transform: translateY(-8px);
}

.pricing-card--teal:hover {
    box-shadow:
        0 18px 40px rgba(0, 0, 0, 0.45),
        -10px -8px 28px rgba(89, 193, 202, 0.22),
        10px 8px 28px rgba(89, 193, 202, 0.16);
}

.pricing-card--gold:hover {
    box-shadow:
        0 18px 40px rgba(0, 0, 0, 0.45),
        -10px -8px 28px rgba(253, 210, 79, 0.2),
        10px 8px 28px rgba(253, 210, 79, 0.16);
}

.pricing-card--featured:hover {
    box-shadow:
        0 18px 40px rgba(0, 0, 0, 0.45),
        -14px -12px 32px rgba(89, 193, 202, 0.32),
        14px 12px 32px rgba(253, 210, 79, 0.28);
}

.pricing-card__glow {
    pointer-events: none;
    position: absolute;
    z-index: 2;
    width: 164px;
    height: 194px;
    max-width: none;
    object-fit: fill;
    transition: filter 280ms ease;
}

.pricing-card:hover .pricing-card__glow {
    filter: brightness(1.2) saturate(1.1);
}

.pricing-card__glow--mask {
    display: block;
}

.pricing-card__glow--tl {
    top: 0;
    left: 0;
}

.pricing-card__glow--br {
    right: 0;
    bottom: 0;
    transform: rotate(180deg);
}

.pricing-card__icon {
    border-style: solid;
    border-width: 0.5px;
    transition:
        border-color 280ms ease,
        box-shadow 280ms ease;
}

.pricing-card__icon--muted {
    border-color: #1f1e1e;
}

.pricing-card--teal:hover .pricing-card__icon--muted {
    border-color: color-mix(in srgb, #59c1ca 92%, transparent);
    box-shadow: 0 0 1.375rem 3px color-mix(in srgb, #59c1ca 28%, transparent);
}

.pricing-card__icon--gold {
    border-color: #fdd24f;
}

.pricing-card--gold:hover .pricing-card__icon--gold,
.pricing-card--featured:hover .pricing-card__icon--gold {
    box-shadow: 0 0 1.375rem 3px color-mix(in srgb, #fdd24f 28%, transparent);
}

.pricing-card__rule {
    position: relative;
    display: block;
    height: 1px;
    min-width: 0;
    flex: 1 1 0;
    background: linear-gradient(to right, rgb(15 15 15 / 0.65), #59c1ca);
}

.pricing-card__rule--gold {
    background: linear-gradient(to right, rgb(15 15 15 / 0.65), #fdd24f);
}

.pricing-card__rule--rtl {
    transform: scaleX(-1);
}

.pricing-card__rule::after {
    position: absolute;
    top: 50%;
    right: 0;
    width: 4.27px;
    height: 4.27px;
    border-radius: 9999px;
    background: #59c1ca;
    content: '';
    transform: translateY(-50%);
}

.pricing-card__rule--gold::after {
    background: #fdd24f;
}

.pricing-card__cta {
    position: relative;
    display: flex;
    height: 2.5rem;
    width: 100%;
    align-items: center;
    justify-content: center;
    border-radius: 0.3125rem;
    border-style: solid;
    border-width: 1px;
    background: transparent;
    font-size: 0.875rem;
    font-weight: 500;
    line-height: normal;
    text-align: center;
    transition:
        background-color 180ms ease,
        border-color 180ms ease,
        color 180ms ease;
}

.pricing-card__cta--teal {
    border-color: #59c1ca;
    color: #59c1ca;
}

.pricing-card__cta--gold {
    border-color: #fdd24f;
    color: #fdd24f;
}

.pricing-card__cta:hover {
    background: #163f46;
}

.pricing-card__cta--gold:hover,
.pricing-card--gold:hover .pricing-card__cta--gold {
    background: rgb(253 210 79 / 0.12);
}

.pricing-card--teal:hover .pricing-card__cta--teal,
.pricing-card--featured:hover .pricing-card__cta--teal {
    background: #163f46;
}

@media (prefers-reduced-motion: reduce) {
    .pricing-card,
    .pricing-card__glow,
    .pricing-card__icon,
    .pricing-card__cta {
        transition: none;
    }

    .pricing-card:hover {
        transform: none;
    }
}
</style>
