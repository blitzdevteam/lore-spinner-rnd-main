<script setup lang="ts">
import arrowRight from '@/assets/pricing/arrow-right.svg';
import footprints from '@/assets/pricing/footprints.svg';
import iconBook from '@/assets/pricing/icon-book.png';
import iconBooksPlus from '@/assets/pricing/icon-books-plus.png';
import iconCrown from '@/assets/pricing/icon-crown.png';
import iconLibrary from '@/assets/pricing/icon-library.png';
import iconPeople from '@/assets/pricing/icon-people.png';
import iconStar from '@/assets/pricing/icon-star.png';
import mountains from '@/assets/pricing/mountains.svg';
import trialHero from '@/assets/pricing/trial-hero.jpg';
import PricingPlanCard from '@/components/PricingPlanCard.vue';
import PricingUsageCard from '@/components/PricingUsageCard.vue';
import HomeLayout from '@/layouts/HomeLayout.vue';
import type { SubscriptionUsageSummary } from '@/types';
import register from '@/wayfinder/routes/user/authentication/register';
import { store as claimFreeTrial } from '@/wayfinder/routes/user/free-trial';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

type Plan = {
    name: string;
    tagline: string;
    price: string;
    bonus: string | null;
    description: string;
    actionAllowance: string;
    actionHint: string | null;
    voiceAllowance: string;
    features: string[];
    cta: string;
    checkoutUrl: string;
    iconKey: string;
    featured: boolean;
    badge: string | null;
    current: boolean;
};

const props = withDefaults(
    defineProps<{
        checkoutStatus?: 'success' | 'cancel' | null;
        returnTo?: string | null;
        billingPortalUrl?: string | null;
        plans?: Plan[];
    }>(),
    {
        checkoutStatus: null,
        returnTo: null,
        billingPortalUrl: null,
        plans: () => [],
    },
);

const planIcons: Record<string, string> = {
    book: iconBook,
    'books-plus': iconBooksPlus,
    library: iconLibrary,
    crown: iconCrown,
    star: iconStar,
    people: iconPeople,
    footprints,
    mountains,
};

const iconFor = (iconKey: string) => planIcons[iconKey] ?? iconBook;

const plansTrack = ref<HTMLElement | null>(null);
const activePlanIndex = ref(0);
let plansLayoutMedia: MediaQueryList | null = null;

const initialPlanSlide = computed(() => {
    const index = props.plans.findIndex((plan) => plan.current);

    return index >= 0 ? index : 0;
});

const prefersReducedMotion = (): boolean =>
    typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const showPlan = (index: number): void => {
    const track = plansTrack.value;

    if (!track) {
        return;
    }

    const slide = track.querySelectorAll<HTMLElement>('.pricing-plans-slide')[index];

    if (!slide) {
        return;
    }

    track.scrollTo({
        left: slide.offsetLeft,
        behavior: prefersReducedMotion() ? 'auto' : 'smooth',
    });
};

const onPlansScroll = (): void => {
    const track = plansTrack.value;

    if (!track) {
        return;
    }

    const slides = [...track.querySelectorAll<HTMLElement>('.pricing-plans-slide')];
    let closest = 0;
    let closestDistance = Number.POSITIVE_INFINITY;

    slides.forEach((slide, index) => {
        const distance = Math.abs(slide.offsetLeft - track.scrollLeft);

        if (distance < closestDistance) {
            closestDistance = distance;
            closest = index;
        }
    });

    activePlanIndex.value = closest;
};

const onPlansLayoutChange = (): void => {
    const track = plansTrack.value;

    if (!track || plansLayoutMedia?.matches !== false) {
        return;
    }

    track.scrollLeft = 0;
};

onMounted(() => {
    plansLayoutMedia = window.matchMedia('(max-width: 1279px)');
    plansLayoutMedia.addEventListener('change', onPlansLayoutChange);

    if (!plansLayoutMedia.matches || initialPlanSlide.value === 0) {
        return;
    }

    const track = plansTrack.value;
    const slide = track?.querySelectorAll<HTMLElement>('.pricing-plans-slide')[initialPlanSlide.value];

    if (!track || !slide) {
        return;
    }

    track.scrollTo({ left: slide.offsetLeft, behavior: 'auto' });
    activePlanIndex.value = initialPlanSlide.value;
});

onUnmounted(() => {
    plansLayoutMedia?.removeEventListener('change', onPlansLayoutChange);
});

const page = usePage();
const isAuthenticated = computed(() => page.props.auth !== null);
const subscriptionUsage = computed<SubscriptionUsageSummary | null>(() => page.props.auth?.subscription_usage ?? null);
const registerHref = computed(() => {
    const url = register.create().url;

    if (!props.returnTo) {
        return url;
    }

    const joiner = url.includes('?') ? '&' : '?';

    return `${url}${joiner}return_to=${encodeURIComponent(props.returnTo)}`;
});
</script>

<template>
    <HomeLayout>
        <Head title="Pricing" />

        <main class="pricing-page bg-black text-white">
            <section class="pricing-hero relative overflow-hidden">
                <img :src="trialHero" alt="" class="absolute inset-0 size-full object-cover object-center" />
                <div class="pricing-hero__shade pointer-events-none absolute inset-0" aria-hidden="true" />

                <div class="relative z-10 container flex min-h-[22.5rem] items-center py-8">
                    <div class="flex w-full max-w-[25.5rem] flex-col gap-6">
                        <PricingUsageCard
                            v-if="subscriptionUsage"
                            :usage="subscriptionUsage"
                            :icon="iconFor(subscriptionUsage.plan.icon_key ?? 'footprints')"
                            :billing-portal-url="props.billingPortalUrl"
                        />
                        <template v-else>
                            <div class="flex flex-col gap-1">
                                <h1
                                    class="font-marcellus-sc text-[2.5rem] leading-[3.75rem] text-white uppercase text-shadow-[0px_0px_21.2px_black]"
                                >
                                    choose your path
                                </h1>
                                <p class="text-xl leading-[26px] text-[#ffbe58]">Try 20 actions and 10 minutes of voice free</p>
                                <p class="max-w-[22rem] text-base leading-[26px] text-[#8f8f8f]">One-time allowance for new accounts.</p>
                            </div>

                            <Link
                                v-if="isAuthenticated"
                                :href="claimFreeTrial.url()"
                                method="post"
                                as="button"
                                type="button"
                                :data="props.returnTo ? { return_to: props.returnTo } : {}"
                                class="pricing-trial__cta"
                            >
                                <span>Try Free</span>
                                <img :src="arrowRight" alt="" width="18" height="18" />
                            </Link>
                            <Link v-else :href="registerHref" class="pricing-trial__cta">
                                <span>Try Free</span>
                                <img :src="arrowRight" alt="" width="18" height="18" />
                            </Link>
                        </template>

                        <p v-for="message in page.props.flash?.success ?? []" :key="message" class="text-base text-primary">
                            {{ message }}
                        </p>
                        <p v-for="message in page.props.flash?.error ?? []" :key="message" class="text-base text-[#ff8f8f]">
                            {{ message }}
                        </p>
                        <p v-if="props.checkoutStatus === 'success'" class="text-base text-primary">
                            Payment received. Your subscription will appear in a moment.
                        </p>
                        <p v-else-if="props.checkoutStatus === 'cancel'" class="text-base text-[#8f8f8f]">
                            Checkout canceled. Choose a plan whenever you are ready.
                        </p>
                    </div>
                </div>
            </section>

            <section id="pricing-plans" class="container pt-10 pb-12 md:pb-16">

                <div v-if="props.plans.length" class="pricing-plans mt-2">
                    <div
                        ref="plansTrack"
                        class="pricing-plans-track relative flex w-full items-stretch gap-0 overflow-x-auto pt-12 xl:grid xl:min-w-0 xl:grid-cols-4 xl:gap-2.5 xl:overflow-visible"
                        role="region"
                        aria-roledescription="carousel"
                        aria-label="Subscription plans"
                        @scroll.passive="onPlansScroll"
                    >
                        <div v-for="plan in props.plans" :key="plan.checkoutUrl" class="pricing-plans-slide xl:min-w-0">
                            <PricingPlanCard
                                :name="plan.name"
                                :tagline="plan.tagline"
                                :price="plan.price"
                                :bonus="plan.bonus"
                                :description="plan.description"
                                :action-allowance="plan.actionAllowance"
                                :voice-allowance="plan.voiceAllowance"
                                :features="plan.features"
                                :cta="plan.cta"
                                :href="plan.checkoutUrl"
                                :icon="iconFor(plan.iconKey)"
                                :featured="plan.featured"
                                :badge="plan.badge"
                                :current="plan.current"
                            />
                        </div>
                    </div>

                    <div v-if="props.plans.length > 1" class="pricing-plans-controls xl:hidden">
                        <button
                            type="button"
                            class="pricing-plans-arrow"
                            aria-label="Previous plan"
                            :disabled="activePlanIndex === 0"
                            @click="showPlan(activePlanIndex - 1)"
                        >
                            <img :src="arrowRight" alt="" class="rotate-180" width="16" height="16" />
                        </button>
                        <div class="flex items-center gap-1.5" role="group" aria-label="Choose a plan">
                            <button
                                v-for="(plan, index) in props.plans"
                                :key="`${plan.name}-${index}`"
                                type="button"
                                class="pricing-plans-dot"
                                :class="{ 'pricing-plans-dot--active': index === activePlanIndex }"
                                :aria-label="`Show ${plan.name}`"
                                :aria-current="index === activePlanIndex ? 'true' : undefined"
                                @click="showPlan(index)"
                            />
                        </div>
                        <button
                            type="button"
                            class="pricing-plans-arrow"
                            aria-label="Next plan"
                            :disabled="activePlanIndex >= props.plans.length - 1"
                            @click="showPlan(activePlanIndex + 1)"
                        >
                            <img :src="arrowRight" alt="" width="16" height="16" />
                        </button>
                    </div>
                    <p class="sr-only xl:hidden" aria-live="polite">{{ props.plans[activePlanIndex]?.name }}</p>
                </div>
                <div
                    v-if="props.plans.length"
                    class="mt-[3.75rem] text-center font-inter text-xs leading-4 font-light tracking-[0.5px] text-[#838383]"
                >
                    <p>* Percentages are not exact.</p>
                    <p>Prices are subject to change at any time.</p>
                    <p>Plans are subject to change at any time.</p>
                </div>
                <p v-else class="container-content mt-12 text-center text-base text-[#838383]">
                    Subscription plans are being prepared. Please check back shortly.
                </p>
            </section>
        </main>
    </HomeLayout>
</template>

<style scoped>
.pricing-hero__shade {
    background:
        linear-gradient(90deg, rgb(0, 0, 0) 23.021%, rgba(0, 0, 0, 0) 48.646%),
        linear-gradient(265.6deg, rgb(0, 0, 0) 12.296%, rgba(0, 0, 0, 0) 32.838%),
        linear-gradient(180deg, rgb(0, 0, 0) 12.656%, rgba(0, 0, 0, 0) 44.831%, rgb(0, 0, 0) 102.32%);
}

.pricing-trial__cta {
    display: inline-flex;
    height: 46px;
    width: 150px;
    align-items: center;
    justify-content: center;
    gap: 10px;
    border: 0.5px solid #59c1ca;
    border-radius: 10px;
    background: #0e1f22;
    color: #fff;
    font-size: 16px;
    line-height: normal;
    cursor: pointer;
    transition: background-color 180ms ease;
}

.pricing-trial__cta:hover {
    background: #163f46;
}

.pricing-plans-track {
    scroll-snap-type: x mandatory;
    scrollbar-width: none;
    -ms-overflow-style: none;
    overscroll-behavior-x: contain;
}

.pricing-plans-track::-webkit-scrollbar {
    display: none;
}

.pricing-plans-slide {
    display: flex;
    width: 100%;
    flex: 0 0 100%;
    justify-content: center;
    scroll-snap-align: start;
    scroll-snap-stop: always;
}

.pricing-plans-slide :deep(.pricing-card) {
    width: min(40rem, 100%);
}

.pricing-plans-controls {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1.25rem;
    margin-top: 1.5rem;
}

.pricing-plans-arrow {
    display: inline-flex;
    width: 2.25rem;
    height: 2.25rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border: 0.5px solid #59c1ca;
    border-radius: 999px;
    background: #0e1f22;
    cursor: pointer;
}

.pricing-plans-arrow:disabled {
    cursor: default;
    opacity: 0.35;
}

.pricing-plans-arrow:not(:disabled):hover {
    background: #163f46;
}

.pricing-plans-dot {
    width: 1.75rem;
    height: 0.75rem;
    padding: 0;
    border: 0;
    background: transparent;
    cursor: pointer;
}

.pricing-plans-dot::before {
    content: '';
    display: block;
    width: 100%;
    height: 3px;
    border-radius: 999px;
    background: rgb(255 255 255 / 0.35);
}

.pricing-plans-dot--active {
    width: 2.5rem;
}

.pricing-plans-dot--active::before {
    background: #59c1ca;
}

@media (min-width: 80rem) {
    .pricing-plans-controls {
        display: none;
    }

    .pricing-plans-track {
        scroll-snap-type: none;
    }

    .pricing-plans-slide {
        width: auto;
        flex: unset;
        scroll-snap-align: none;
        scroll-snap-stop: normal;
    }

    .pricing-plans-slide :deep(.pricing-card) {
        width: 100%;
    }
}

@media (prefers-reduced-motion: reduce) {
    .pricing-plans-track {
        scroll-behavior: auto;
    }
}
</style>
