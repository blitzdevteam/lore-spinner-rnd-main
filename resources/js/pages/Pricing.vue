<script setup lang="ts">
import arrowRight from '@/assets/pricing/arrow-right.svg';
import crown from '@/assets/pricing/crown.svg';
import faqBackground from '@/assets/pricing/faq-background.png';
import footprints from '@/assets/pricing/footprints.svg';
import heroLineLeft from '@/assets/pricing/hero-line-left.svg';
import heroLineRight from '@/assets/pricing/hero-line-right.svg';
import heroStar from '@/assets/pricing/hero-star.svg';
import mountains from '@/assets/pricing/mountains.svg';
import PricingPlanCard from '@/components/PricingPlanCard.vue';
import HomeLayout from '@/layouts/HomeLayout.vue';
import { Head } from '@inertiajs/vue3';

type Plan = {
    name: string;
    tagline: string;
    price: string;
    description: string;
    features: string[];
    cta: string;
    checkoutUrl: string;
    iconKey: string;
    accent: 'teal' | 'gold';
    featured: boolean;
};

const props = withDefaults(
    defineProps<{
        checkoutStatus?: 'success' | 'cancel' | null;
        plans?: Plan[];
    }>(),
    {
        checkoutStatus: null,
        plans: () => [],
    },
);

const planIcons: Record<string, string> = {
    footprints,
    mountains,
    crown,
};

const iconFor = (iconKey: string) => planIcons[iconKey] ?? footprints;
</script>

<template>
    <HomeLayout>
        <Head title="Pricing" />

        <main class="pricing-page bg-black text-white">
            <section class="container pt-10 pb-12 md:pt-[3.6875rem] md:pb-16">
                <div class="container-content flex flex-col items-center">
                    <header class="flex w-full max-w-[33.375rem] flex-col items-center gap-3 text-center">
                        <h1
                            class="font-marcellus-sc text-[2.25rem] leading-[1.25] text-white uppercase text-shadow-[0px_0px_21.2px_black] md:text-[3rem] md:leading-[3.75rem]"
                        >
                            choose your <span class="text-primary">path</span>
                        </h1>

                        <div class="flex w-full items-center justify-center gap-1">
                            <img :src="heroLineLeft" alt="" class="h-px min-w-0 flex-1 object-cover" width="226" height="1" aria-hidden="true" />
                            <span class="pricing-hero-star relative size-[11px] shrink-0 opacity-50">
                                <img
                                    :src="heroStar"
                                    alt=""
                                    class="absolute top-1/2 left-1/2 size-[4.46rem] max-w-none -translate-x-1/2 -translate-y-1/2"
                                    width="71"
                                    height="71"
                                />
                            </span>
                            <img :src="heroLineRight" alt="" class="h-px min-w-0 flex-1 object-cover" width="226" height="1" aria-hidden="true" />
                        </div>

                        <p class="w-full text-base leading-[1.625rem] text-[#838383]">
                            Every story is a choice. Every choice shapes your world.<br />
                            Unlock deeper experiences and support the storytellers you love.
                        </p>
                    </header>

                    <p v-if="props.checkoutStatus === 'success'" class="mt-8 max-w-[33.375rem] text-center text-base text-primary">
                        Payment received. Your subscription will appear in a moment.
                    </p>
                    <p v-else-if="props.checkoutStatus === 'cancel'" class="mt-8 max-w-[33.375rem] text-center text-base text-[#838383]">
                        Checkout canceled. Choose a plan whenever you are ready.
                    </p>

                    <div v-if="props.plans.length" class="mt-12 grid w-full grid-cols-1 items-start gap-5 overflow-visible py-2 md:mt-[3.75rem] md:grid-cols-3">
                        <PricingPlanCard
                            v-for="plan in props.plans"
                            :key="plan.checkoutUrl"
                            :name="plan.name"
                            :tagline="plan.tagline"
                            :price="plan.price"
                            :description="plan.description"
                            :features="plan.features"
                            :cta="plan.cta"
                            :href="plan.checkoutUrl"
                            :icon="iconFor(plan.iconKey)"
                            :accent="plan.accent"
                            :featured="plan.featured"
                        />
                    </div>
                    <p v-else class="mt-12 text-center text-base text-[#838383]">
                        Subscription plans are being prepared. Please check back shortly.
                    </p>
                </div>
            </section>

            <section class="pricing-faq relative overflow-hidden">
                <img :src="faqBackground" alt="" class="absolute inset-0 size-full object-cover object-center" />
                <div class="pricing-faq__shade pointer-events-none absolute inset-0" aria-hidden="true" />

                <div class="relative z-10 container">
                    <div class="container-content flex min-h-[22.5rem] items-center py-10 md:py-[1.875rem]">
                        <div class="flex w-full max-w-[25.5rem] flex-col gap-6">
                            <div>
                                <h2
                                    class="font-marcellus-sc text-[1.75rem] leading-[1.35] text-white uppercase text-shadow-[0px_0px_21.2px_black] md:text-[2rem] md:leading-[3.75rem]"
                                >
                                    not sure which path<br />
                                    is right for you?
                                </h2>
                                <p class="mt-0 max-w-[22rem] text-lg leading-[1.625rem] text-[#8f8f8f]">
                                    Our FAQs and support team are here to help you find your way
                                </p>
                            </div>

                            <a href="/#faq" class="pricing-faq__cta inline-flex h-[3.125rem] w-[9.375rem] items-center justify-center gap-2.5">
                                <span class="text-base leading-normal text-white">View FAQs</span>
                                <span class="relative size-[18px] shrink-0 overflow-hidden">
                                    <img :src="arrowRight" alt="" class="absolute inset-0 size-full object-contain" width="18" height="18" />
                                </span>
                            </a>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </HomeLayout>
</template>

<style scoped>
.pricing-hero-star {
    overflow: visible;
}

.pricing-faq {
    min-height: 22.5rem;
}

.pricing-faq__shade {
    background:
        linear-gradient(90deg, rgb(0, 0, 0) 14.201%, rgba(0, 0, 0, 0) 43.507%),
        linear-gradient(265.57deg, rgb(0, 0, 0) 2.685%, rgba(0, 0, 0, 0) 18.843%),
        linear-gradient(180deg, rgb(0, 0, 0) 12.656%, rgba(0, 0, 0, 0) 44.831%, rgb(0, 0, 0) 102.32%);
}

.pricing-faq__cta {
    border: 0.5px solid #fdd24f;
    border-radius: 0.625rem;
    background: #0f0f0f;
    transition:
        background-color 180ms ease,
        border-color 180ms ease,
        transform 180ms ease;
}

.pricing-faq__cta:hover {
    background: #163f46;
    transform: translateY(-1px);
}

@media (max-width: 47.999rem) {
    .pricing-faq__shade {
        background:
            linear-gradient(180deg, rgb(0, 0, 0) 8%, rgba(0, 0, 0, 0.35) 42%, rgb(0, 0, 0) 100%),
            linear-gradient(90deg, rgb(0, 0, 0) 0%, rgba(0, 0, 0, 0.45) 55%, rgba(0, 0, 0, 0.2) 100%);
    }
}
</style>
