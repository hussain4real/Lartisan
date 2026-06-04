<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BadgeCheck,
    BriefcaseBusiness,
    CalendarCheck,
    ChevronRight,
    ClipboardCheck,
    LocateFixed,
    Search,
    ShieldCheck,
    Sparkles,
    Star,
    WalletCards,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';
import { index as marketplaceIndex } from '@/routes/marketplace';

const page = usePage();
const dashboardUrl = computed(() =>
    page.props.currentTeam
        ? dashboard(page.props.currentTeam.slug).url
        : dashboard().url,
);

const artisanRegisterUrl = register({
    query: {
        intent: 'artisan',
    },
}).url;

const metrics = [
    { value: 'Verified', label: 'artisan identity and KYC signals' },
    { value: 'Scoped', label: 'state, LGA, and territory operations' },
    { value: 'Tracked', label: 'booking lifecycle from request to completion' },
];

const trustSignals = [
    {
        title: 'Identity first',
        text: 'Phone verification, claim flows, and public profile controls keep marketplace access deliberate.',
        icon: ShieldCheck,
    },
    {
        title: 'Local verification',
        text: 'Area agents and admins can review KYC, field visits, and territory assignments from scoped panels.',
        icon: LocateFixed,
    },
    {
        title: 'Auditable money movement',
        text: 'Subscriptions, wallets, ledgers, payouts, disputes, and reports are wired for operational oversight.',
        icon: WalletCards,
    },
];

const steps = [
    {
        title: 'Find',
        text: 'Search verified subscribed artisans by service category and real operating location.',
        icon: Search,
    },
    {
        title: 'Book',
        text: 'Send a request with address details, attachments, and a secure tracker for guests or customers.',
        icon: CalendarCheck,
    },
    {
        title: 'Resolve',
        text: 'Move through accept, start, finish, confirm, review, dispute, and payout flows without losing the audit trail.',
        icon: ClipboardCheck,
    },
];

const actorPaths = [
    {
        title: 'Customers',
        text: 'Book reliable service without needing an account first.',
        action: 'Browse marketplace',
        href: marketplaceIndex().url,
    },
    {
        title: 'Artisans',
        text: 'Create a business workspace, build a profile, publish services, and submit verification evidence.',
        action: 'Become an artisan',
        href: artisanRegisterUrl,
    },
    {
        title: 'Operations',
        text: 'Run verification queues, reviews, disputes, payouts, and scoped reports across local territories.',
        action: 'Sign in',
        href: login().url,
    },
];

const processFlow = [
    {
        title: 'Verified profile',
        text: 'Identity and KYC signals cleared',
        icon: BadgeCheck,
    },
    {
        title: 'Booking tracker',
        text: 'Request accepted and work started',
        icon: CalendarCheck,
    },
    {
        title: 'Wallet ledger',
        text: 'Completion creates an audit trail',
        icon: WalletCards,
    },
];

const mobileProcessFlow = [
    {
        title: 'Verified',
        text: 'KYC cleared',
        icon: BadgeCheck,
    },
    {
        title: 'Booked',
        text: 'Work tracked',
        icon: CalendarCheck,
    },
    {
        title: 'Settled',
        text: 'Ledger posted',
        icon: WalletCards,
    },
];
</script>

<template>
    <Head title="Lartisan">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <main
        class="min-h-screen bg-[#f8fafc] text-[#1d1d1d] dark:bg-[#11130f] dark:text-[#f8fafc]"
    >
        <section
            class="relative isolate min-h-svh overflow-hidden bg-[#1d1d1d] text-white"
        >
            <div class="absolute inset-0 hidden lg:block" aria-hidden="true">
                <div class="absolute inset-y-0 right-0 w-[52%] overflow-hidden">
                    <div class="artisan-scene absolute inset-0">
                        <div class="scene-grid" />

                        <div class="scene-panel scene-panel-main motion-layer">
                            <div
                                class="flex items-center justify-between gap-4"
                            >
                                <div>
                                    <p class="text-xs text-white/60">
                                        Marketplace live
                                    </p>
                                    <p class="mt-1 text-lg font-semibold">
                                        Wuse Sparks Electrical
                                    </p>
                                </div>
                                <span
                                    class="rounded-full bg-[#d5f7bd] px-3 py-1 text-xs font-medium text-[#17320f]"
                                >
                                    verified
                                </span>
                            </div>

                            <div class="mt-6 grid grid-cols-[1fr_auto] gap-4">
                                <div>
                                    <p class="text-sm text-white/64">
                                        Service request
                                    </p>
                                    <p class="mt-1 text-2xl font-semibold">
                                        Panel inspection
                                    </p>
                                </div>
                                <div
                                    class="grid size-14 place-items-center rounded-lg bg-[#f59e0b] text-[#1d1d1d]"
                                >
                                    <BriefcaseBusiness class="size-6" />
                                </div>
                            </div>

                            <div class="mt-7 rounded-lg bg-white/8 p-3">
                                <div
                                    class="flex items-center justify-between gap-4"
                                >
                                    <span class="text-sm text-white/60">
                                        Next handoff
                                    </span>
                                    <span
                                        class="rounded-full bg-white/12 px-3 py-1 text-xs text-white/72"
                                    >
                                        ready for booking
                                    </span>
                                </div>
                                <div class="mt-3 h-2 rounded-full bg-white/10">
                                    <div
                                        class="h-full w-2/3 rounded-full bg-[#f59e0b]"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="process-flow" aria-hidden="true">
                            <template
                                v-for="(stage, index) in processFlow"
                                :key="stage.title"
                            >
                                <div
                                    class="process-node"
                                    :class="{
                                        'process-node-active': index === 1,
                                    }"
                                >
                                    <div class="process-icon">
                                        <component
                                            :is="stage.icon"
                                            class="size-5"
                                        />
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold">
                                            {{ stage.title }}
                                        </p>
                                        <p class="mt-1 text-xs text-white/58">
                                            {{ stage.text }}
                                        </p>
                                    </div>
                                    <span class="process-index">
                                        0{{ index + 1 }}
                                    </span>
                                </div>
                                <div
                                    v-if="index < processFlow.length - 1"
                                    class="process-connector"
                                    :class="{
                                        'process-connector-delayed':
                                            index === 1,
                                    }"
                                >
                                    <span />
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
                <div class="absolute inset-0 bg-[#1d1d1d]/30" />
            </div>

            <header
                class="relative z-10 mx-auto flex w-full max-w-7xl items-center justify-between gap-4 px-5 py-5 sm:px-8"
            >
                <Link
                    :href="marketplaceIndex().url"
                    class="flex items-center gap-3"
                    prefetch
                >
                    <span
                        class="grid size-10 place-items-center rounded-lg bg-[#f59e0b] text-[#1d1d1d] shadow-lg shadow-black/20"
                    >
                        <AppLogoIcon class="size-5 fill-current" />
                    </span>
                    <span class="text-lg font-semibold">Lartisan</span>
                </Link>

                <nav class="flex items-center gap-2 text-sm">
                    <Link
                        :href="marketplaceIndex().url"
                        class="hidden rounded-full px-4 py-2 text-white/78 transition hover:bg-white/10 hover:text-white sm:inline-flex"
                        prefetch
                    >
                        Marketplace
                    </Link>
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboardUrl"
                        class="rounded-full border border-white/20 px-4 py-2 text-white transition hover:border-white/40 hover:bg-white/10"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="login().url"
                            class="rounded-full px-4 py-2 text-white/78 transition hover:bg-white/10 hover:text-white"
                        >
                            Log in
                        </Link>
                        <Link
                            :href="artisanRegisterUrl"
                            class="rounded-full bg-white px-4 py-2 font-medium text-[#1d1d1d] shadow-lg shadow-black/15 transition hover:-translate-y-0.5 hover:bg-[#f59e0b] motion-reduce:hover:translate-y-0"
                        >
                            Join
                        </Link>
                    </template>
                </nav>
            </header>

            <div
                class="relative z-10 mx-auto flex min-h-[calc(100svh-5rem)] w-full max-w-7xl items-center px-5 pt-10 pb-16 sm:px-8 lg:pb-24"
            >
                <div class="max-w-3xl lg:max-w-[34rem] 2xl:max-w-[38rem]">
                    <div
                        class="inline-flex items-center gap-2 rounded-full border border-white/16 bg-white/8 px-3 py-1.5 text-sm text-white/78 backdrop-blur"
                    >
                        <Sparkles class="size-4 text-[#f59e0b]" />
                        Built for verified local service
                    </div>

                    <h1
                        class="mt-8 max-w-3xl text-5xl leading-[1.02] font-semibold text-white sm:text-6xl lg:text-7xl"
                    >
                        Lartisan
                    </h1>

                    <p
                        class="mt-6 max-w-2xl text-xl leading-8 text-white/76 lg:max-w-[33rem] 2xl:max-w-xl"
                    >
                        A trusted marketplace where customers find verified
                        artisans, artisans run their business identity, and
                        operations teams keep every local signal accountable.
                    </p>

                    <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                        <Button
                            as-child
                            size="lg"
                            class="h-12 rounded-full bg-[#f59e0b] px-6 text-[#1d1d1d] hover:bg-[#fbbf24]"
                        >
                            <Link :href="marketplaceIndex().url" prefetch>
                                Find artisans
                                <ArrowRight class="size-4" />
                            </Link>
                        </Button>
                        <Button
                            as-child
                            size="lg"
                            variant="outline"
                            class="h-12 rounded-full border-white/24 bg-white/8 px-6 text-white hover:bg-white/14 hover:text-white"
                        >
                            <Link :href="artisanRegisterUrl">
                                Become an artisan
                                <ChevronRight class="size-4" />
                            </Link>
                        </Button>
                    </div>

                    <div
                        class="mobile-process-strip mt-7 lg:hidden"
                        aria-label="Service process"
                    >
                        <div
                            class="mobile-process-track"
                            v-for="(stage, index) in mobileProcessFlow"
                            :key="stage.title"
                        >
                            <div class="mobile-process-step">
                                <span class="mobile-process-icon">
                                    <component
                                        :is="stage.icon"
                                        class="size-4"
                                    />
                                </span>
                                <span class="mobile-process-title">
                                    {{ stage.title }}
                                </span>
                                <span class="mobile-process-text">
                                    {{ stage.text }}
                                </span>
                            </div>
                            <span
                                v-if="index < mobileProcessFlow.length - 1"
                                class="mobile-process-link"
                            >
                                <span />
                            </span>
                        </div>
                    </div>

                    <dl
                        class="mt-12 grid max-w-3xl gap-3 sm:grid-cols-3 lg:max-w-[34rem] 2xl:max-w-[38rem]"
                    >
                        <div
                            v-for="metric in metrics"
                            :key="metric.value"
                            class="rounded-lg border border-white/12 bg-white/8 p-4 backdrop-blur"
                        >
                            <dt class="text-lg font-semibold text-[#f59e0b]">
                                {{ metric.value }}
                            </dt>
                            <dd class="mt-1 text-sm leading-5 text-white/64">
                                {{ metric.label }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>

        <section
            class="border-y border-[#1d1d1d]/10 bg-[#f8fafc] py-5 dark:border-white/10 dark:bg-[#1d1d1d]"
        >
            <div
                class="mx-auto flex max-w-7xl flex-col gap-3 px-5 text-sm text-[#565f4e] sm:px-8 lg:flex-row lg:items-center lg:justify-between dark:text-white/62"
            >
                <p class="font-medium text-[#1d1d1d] dark:text-white">
                    One operating loop for service discovery, verification,
                    bookings, subscriptions, wallets, and reports.
                </p>
                <div class="flex flex-wrap gap-2">
                    <span
                        class="rounded-full bg-[#d5f7bd] px-3 py-1 text-[#17320f]"
                        >Customers</span
                    >
                    <span
                        class="rounded-full bg-[#f59e0b] px-3 py-1 text-[#1d1d1d]"
                        >Artisans</span
                    >
                    <span
                        class="rounded-full bg-[#8cc8ff] px-3 py-1 text-[#08243d]"
                        >Operations</span
                    >
                </div>
            </div>
        </section>

        <section
            class="mx-auto grid max-w-7xl gap-8 px-5 py-20 sm:px-8 lg:grid-cols-[0.85fr_1.15fr] lg:py-28"
        >
            <div>
                <p class="text-sm font-medium text-[#ef705d]">
                    Trust architecture
                </p>
                <h2
                    class="mt-3 max-w-xl text-3xl leading-tight font-semibold sm:text-4xl"
                >
                    The marketplace is only as good as the verification behind
                    it.
                </h2>
                <p
                    class="mt-5 max-w-xl leading-7 text-[#5f6659] dark:text-white/62"
                >
                    Lartisan connects public discovery with private evidence,
                    scoped operations, and auditable financial movement so the
                    trust loop does not disappear after a booking is made.
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <article
                    v-for="signal in trustSignals"
                    :key="signal.title"
                    class="group rounded-lg border border-[#191b18]/10 bg-white p-5 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl motion-reduce:hover:translate-y-0 dark:border-white/10 dark:bg-white/5"
                >
                    <div
                        class="grid size-11 place-items-center rounded-lg bg-[#f8fafc] text-[#1d1d1d] transition group-hover:bg-[#f59e0b] dark:bg-white/10 dark:text-white"
                    >
                        <component :is="signal.icon" class="size-5" />
                    </div>
                    <h3 class="mt-5 font-semibold">{{ signal.title }}</h3>
                    <p
                        class="mt-3 text-sm leading-6 text-[#5f6659] dark:text-white/62"
                    >
                        {{ signal.text }}
                    </p>
                </article>
            </div>
        </section>

        <section class="bg-[#1f2a1a] py-20 text-white lg:py-28">
            <div
                class="mx-auto grid max-w-7xl gap-10 px-5 sm:px-8 lg:grid-cols-[0.9fr_1.1fr] lg:items-center"
            >
                <div>
                    <p class="text-sm font-medium text-[#d5f7bd]">
                        Booking lifecycle
                    </p>
                    <h2
                        class="mt-3 text-3xl leading-tight font-semibold sm:text-4xl"
                    >
                        From search to completion, every handoff has a state.
                    </h2>
                    <p class="mt-5 leading-7 text-white/66">
                        Guests can book, customers can track, artisans can act,
                        and the back office can review the trail when something
                        needs attention.
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <article
                        v-for="(step, index) in steps"
                        :key="step.title"
                        class="relative rounded-lg border border-white/12 bg-white/8 p-5 transition duration-300 hover:bg-white/12"
                    >
                        <span class="text-sm text-white/42"
                            >0{{ index + 1 }}</span
                        >
                        <div
                            class="mt-4 grid size-11 place-items-center rounded-lg bg-[#f59e0b] text-[#1d1d1d]"
                        >
                            <component :is="step.icon" class="size-5" />
                        </div>
                        <h3 class="mt-5 text-lg font-semibold">
                            {{ step.title }}
                        </h3>
                        <p class="mt-3 text-sm leading-6 text-white/66">
                            {{ step.text }}
                        </p>
                    </article>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-5 py-20 sm:px-8 lg:py-28">
            <div
                class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between"
            >
                <div>
                    <p class="text-sm font-medium text-[#ef705d]">
                        Choose your path
                    </p>
                    <h2
                        class="mt-3 max-w-2xl text-3xl leading-tight font-semibold sm:text-4xl"
                    >
                        A focused workspace for each actor in the service
                        economy.
                    </h2>
                </div>
                <Link
                    :href="marketplaceIndex().url"
                    class="inline-flex items-center gap-2 text-sm font-medium text-[#1d1d1d] transition hover:gap-3 dark:text-white"
                    prefetch
                >
                    Explore marketplace
                    <ArrowRight class="size-4" />
                </Link>
            </div>

            <div class="mt-10 grid gap-4 md:grid-cols-3">
                <article
                    v-for="path in actorPaths"
                    :key="path.title"
                    class="group rounded-lg border border-[#191b18]/10 bg-white p-6 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl motion-reduce:hover:translate-y-0 dark:border-white/10 dark:bg-white/5"
                >
                    <h3 class="text-xl font-semibold">{{ path.title }}</h3>
                    <p
                        class="mt-4 min-h-20 text-sm leading-6 text-[#5f6659] dark:text-white/62"
                    >
                        {{ path.text }}
                    </p>
                    <Link
                        :href="path.href"
                        class="mt-8 inline-flex items-center gap-2 text-sm font-medium text-[#ef705d] transition group-hover:gap-3"
                    >
                        {{ path.action }}
                        <ArrowRight class="size-4" />
                    </Link>
                </article>
            </div>
        </section>

        <section class="px-5 pb-20 sm:px-8">
            <div
                class="mx-auto max-w-7xl overflow-hidden rounded-lg bg-[#1d1d1d] text-white"
            >
                <div
                    class="grid gap-8 p-8 md:grid-cols-[1fr_auto] md:items-center lg:p-12"
                >
                    <div>
                        <div class="flex items-center gap-2 text-[#f59e0b]">
                            <Star class="size-4 fill-current" />
                            <span class="text-sm font-medium"
                                >Pilot-ready marketplace</span
                            >
                        </div>
                        <h2
                            class="mt-4 max-w-2xl text-3xl leading-tight font-semibold"
                        >
                            Start with verified artisans, then let the
                            operational layer keep the promise.
                        </h2>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <Button
                            as-child
                            size="lg"
                            class="h-12 rounded-full bg-white px-6 text-[#1d1d1d] hover:bg-[#f59e0b]"
                        >
                            <Link :href="marketplaceIndex().url" prefetch>
                                Find service
                            </Link>
                        </Button>
                        <Button
                            as-child
                            size="lg"
                            variant="outline"
                            class="h-12 rounded-full border-white/24 bg-white/8 px-6 text-white hover:bg-white/14 hover:text-white"
                        >
                            <Link :href="artisanRegisterUrl">
                                List my business
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </section>
    </main>
</template>

<style scoped>
.artisan-scene {
    background:
        linear-gradient(90deg, rgba(29, 29, 29, 0.1), rgba(29, 29, 29, 0.88)),
        #001c72;
}

.scene-grid {
    position: absolute;
    inset: 0;
    opacity: 0.28;
    background-image:
        linear-gradient(rgba(255, 255, 255, 0.08) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.08) 1px, transparent 1px);
    background-size: 76px 76px;
}

.scene-panel {
    position: absolute;
    border: 1px solid rgba(255, 255, 255, 0.14);
    border-radius: 0.5rem;
    background: rgba(255, 255, 255, 0.1);
    box-shadow: 0 24px 80px rgba(0, 0, 0, 0.32);
    backdrop-filter: blur(18px);
}

.scene-panel-main {
    right: 12%;
    top: 17%;
    width: min(430px, 46vw);
    padding: 1.4rem;
}

.process-flow {
    position: absolute;
    right: 12%;
    top: max(47%, 410px);
    width: min(430px, 44vw);
}

.process-node {
    display: grid;
    grid-template-columns: auto 1fr auto;
    align-items: center;
    gap: 0.85rem;
    min-height: 4.85rem;
    border: 1px solid rgba(255, 255, 255, 0.13);
    border-radius: 0.5rem;
    background: rgba(255, 255, 255, 0.09);
    padding: 0.9rem;
    box-shadow: 0 18px 64px rgba(0, 0, 0, 0.28);
    backdrop-filter: blur(18px);
}

.process-node-active {
    border-color: rgba(245, 158, 11, 0.48);
    background: rgba(245, 158, 11, 0.13);
}

.process-icon {
    display: grid;
    width: 2.6rem;
    height: 2.6rem;
    place-items: center;
    border-radius: 0.5rem;
    background: #d5f7bd;
    color: #17320f;
}

.process-node-active .process-icon {
    background: #f59e0b;
    color: #1d1d1d;
}

.process-index {
    color: rgba(255, 255, 255, 0.34);
    font-size: 0.75rem;
    font-weight: 600;
}

.process-connector {
    position: relative;
    width: 2px;
    height: 2.45rem;
    margin-left: 1.95rem;
    overflow: hidden;
    background: rgba(255, 255, 255, 0.16);
}

.process-connector span {
    position: absolute;
    left: 50%;
    width: 0.55rem;
    height: 0.55rem;
    border-radius: 999px;
    background: #f59e0b;
    box-shadow: 0 0 22px rgba(245, 158, 11, 0.85);
    animation: process-travel 3.8s ease-in-out infinite;
}

.process-connector-delayed span {
    animation-delay: 1.35s;
}

.mobile-process-strip {
    display: flex;
    align-items: stretch;
    gap: 0.35rem;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 0.5rem;
    background: rgba(255, 255, 255, 0.08);
    padding: 0.7rem;
    backdrop-filter: blur(16px);
}

.mobile-process-track {
    display: contents;
}

.mobile-process-step {
    display: flex;
    flex: 1 1 0;
    min-width: 0;
    flex-direction: column;
    align-items: center;
    border-radius: 0.5rem;
    padding: 0.25rem 0;
    text-align: center;
}

.mobile-process-icon {
    display: grid;
    width: 2rem;
    height: 2rem;
    place-items: center;
    border-radius: 0.5rem;
    background: rgba(245, 158, 11, 0.18);
    color: #f59e0b;
}

.mobile-process-title {
    margin-top: 0.45rem;
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.75rem;
    font-weight: 600;
}

.mobile-process-text {
    margin-top: 0.15rem;
    color: rgba(255, 255, 255, 0.54);
    font-size: 0.68rem;
    line-height: 1.25;
}

.mobile-process-link {
    position: relative;
    align-self: center;
    flex: 0 0 1.35rem;
    height: 2px;
    overflow: hidden;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.16);
}

.mobile-process-link span {
    position: absolute;
    inset-block: 0;
    width: 55%;
    border-radius: inherit;
    background: #f59e0b;
    animation: mobile-process-travel 3s ease-in-out infinite;
}

@media (min-width: 1024px) {
    .mobile-process-strip {
        display: none;
    }
}

.motion-layer {
    animation: welcome-float 7s ease-in-out infinite;
}

@keyframes welcome-float {
    0% {
        transform: translateY(0);
    }

    50% {
        transform: translateY(-8px);
    }

    100% {
        transform: translateY(0);
    }
}

@keyframes process-travel {
    0% {
        opacity: 0;
        transform: translate(-50%, -0.7rem);
    }

    18%,
    72% {
        opacity: 1;
    }

    100% {
        opacity: 0;
        transform: translate(-50%, 2.8rem);
    }
}

@keyframes mobile-process-travel {
    0% {
        opacity: 0;
        transform: translateX(-120%);
    }

    20%,
    76% {
        opacity: 1;
    }

    100% {
        opacity: 0;
        transform: translateX(220%);
    }
}

@media (prefers-reduced-motion: reduce) {
    .motion-layer,
    .process-connector span,
    .mobile-process-link span {
        animation: none;
    }
}
</style>
