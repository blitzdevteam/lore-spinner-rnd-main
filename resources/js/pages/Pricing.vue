<script setup lang="ts">
import arrowRight from '@/assets/pricing/arrow-right.svg';
import faqBackground from '@/assets/pricing/faq-background.png';
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
import HomeLayout from '@/layouts/HomeLayout.vue';
import register from '@/wayfinder/routes/user/authentication/register';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

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

const page = usePage();
const tryFreeHref = computed(() => (page.props.auth ? '/' : register.create().url));
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
                        <div class="flex flex-col gap-1">
                            <h1
                                class="font-marcellus-sc text-[2.5rem] leading-[3.75rem] text-white uppercase text-shadow-[0px_0px_21.2px_black]"
                            >
                                choose your path
                            </h1>
                            <p class="text-xl leading-[26px] text-[#ffbe58]">Try 20 actions and 10 minutes of voice free</p>
                            <p class="max-w-[22rem] text-base leading-[26px] text-[#8f8f8f]">One-time allowance for new accounts.</p>
                        </div>

                        <Link :href="tryFreeHref" class="pricing-trial__cta">
                            <span>Try Free</span>
                            <img :src="arrowRight" alt="" width="18" height="18" />
                        </Link>

                        <p v-if="props.checkoutStatus === 'success'" class="text-base text-primary">
                            Payment received. Your subscription will appear in a moment.
                        </p>
                        <p v-else-if="props.checkoutStatus === 'cancel'" class="text-base text-[#8f8f8f]">
                            Checkout canceled. Choose a plan whenever you are ready.
                        </p>
                    </div>
                </div>
            </section>

            <section class="container pt-10 pb-12 md:pb-16">

                <div
                    v-if="props.plans.length"
                    class="mt-2 grid w-full grid-cols-1 items-stretch gap-2.5 overflow-visible pt-8 md:grid-cols-2 xl:grid-cols-4"
                >
                    <PricingPlanCard
                        v-for="plan in props.plans"
                        :key="plan.checkoutUrl"
                        :name="plan.name"
                        :tagline="plan.tagline"
                        :price="plan.price"
                        :bonus="plan.bonus"
                        :description="plan.description"
                        :action-allowance="plan.actionAllowance"
                        :action-hint="plan.actionHint"
                        :voice-allowance="plan.voiceAllowance"
                        :features="plan.features"
                        :cta="plan.cta"
                        :href="plan.checkoutUrl"
                        :icon="iconFor(plan.iconKey)"
                        :featured="plan.featured"
                        :badge="plan.badge"
                    />
                </div>
                <p v-else class="container-content mt-12 text-center text-base text-[#838383]">
                    Subscription plans are being prepared. Please check back shortly.
                </p>
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
    transition: background-color 180ms ease;
}

.pricing-trial__cta:hover {
    background: #163f46;
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
