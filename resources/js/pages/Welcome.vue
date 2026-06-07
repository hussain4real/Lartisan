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
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
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

    <main class="min-h-screen bg-background text-foreground">
        <section
            class="relative isolate min-h-svh overflow-hidden bg-background lg:min-h-[820px]"
        >
            <div class="absolute inset-0 hidden lg:block" aria-hidden="true">
                <div
                    class="scene-shell absolute inset-y-0 right-0 w-[56%] overflow-hidden"
                >
                    <div class="artisan-scene absolute inset-0 text-white">
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
                                    class="rounded-full bg-brand px-3 py-1 text-xs font-medium text-brand-foreground"
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
                                    class="grid size-14 place-items-center rounded-lg bg-brand text-brand-foreground"
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
                                        class="h-full w-2/3 rounded-full bg-primary"
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
                <div class="absolute inset-0 bg-background/30" />
            </div>

            <header
                class="relative z-10 mx-auto flex w-full max-w-[92rem] items-center justify-between gap-4 px-5 py-5 sm:px-8 xl:px-10"
            >
                <Link
                    :href="marketplaceIndex().url"
                    class="flex items-center gap-3"
                    prefetch
                >
                    <span
                        class="grid size-10 place-items-center rounded-lg bg-brand text-brand-foreground shadow-lg shadow-black/20"
                    >
                        <AppLogoIcon class="size-5 fill-current" />
                    </span>
                    <span class="text-lg font-semibold">Lartisan</span>
                </Link>

                <nav
                    class="flex items-center gap-1 rounded-full border border-border bg-card/90 p-1 text-sm text-card-foreground shadow-sm shadow-black/10 backdrop-blur-xl dark:border-border/70 dark:bg-card/80"
                >
                    <Link
                        :href="marketplaceIndex().url"
                        class="hidden rounded-full px-4 py-2 text-card-foreground/75 transition hover:bg-accent hover:text-accent-foreground sm:inline-flex"
                        prefetch
                    >
                        Marketplace
                    </Link>
                    <ThemeSwitcher
                        class="text-card-foreground/75 hover:bg-accent hover:text-accent-foreground"
                    />
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboardUrl"
                        class="rounded-full border border-border/80 bg-background/60 px-4 py-2 text-card-foreground transition hover:bg-accent hover:text-accent-foreground"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="login().url"
                            class="rounded-full px-4 py-2 text-card-foreground/75 transition hover:bg-accent hover:text-accent-foreground"
                        >
                            Log in
                        </Link>
                        <Link
                            :href="artisanRegisterUrl"
                            class="rounded-full bg-primary px-4 py-2 font-medium text-primary-foreground shadow-lg shadow-black/15 transition hover:-translate-y-0.5 hover:bg-primary/90 motion-reduce:hover:translate-y-0"
                        >
                            Join
                        </Link>
                    </template>
                </nav>
            </header>

            <div
                class="relative z-10 mx-auto flex min-h-[calc(100svh-5rem)] w-full max-w-[92rem] items-center px-5 pt-10 pb-16 sm:px-8 lg:min-h-[740px] lg:pb-18 xl:px-10"
            >
                <div class="max-w-3xl lg:max-w-[34rem] 2xl:max-w-[38rem]">
                    <div
                        class="inline-flex items-center gap-2 rounded-full border border-border bg-card/70 px-3 py-1.5 text-sm text-muted-foreground backdrop-blur"
                    >
                        <Sparkles class="size-4 text-primary" />
                        Built for verified local service
                    </div>

                    <h1
                        class="mt-8 max-w-3xl text-5xl leading-[1.02] font-semibold sm:text-6xl lg:text-7xl"
                    >
                        Lartisan
                    </h1>

                    <p
                        class="mt-6 max-w-2xl text-xl leading-8 text-muted-foreground lg:max-w-[33rem] 2xl:max-w-xl"
                    >
                        A trusted marketplace where customers find verified
                        artisans, artisans run their business identity, and
                        operations teams keep every local signal accountable.
                    </p>

                    <div class="mt-9 flex flex-col gap-3 sm:flex-row">
                        <Button
                            as-child
                            size="lg"
                            class="h-12 rounded-full bg-primary px-6 text-primary-foreground hover:bg-primary/90"
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
                            class="h-12 rounded-full border-border bg-card/70 px-6 text-foreground hover:bg-accent hover:text-foreground"
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
                            class="rounded-lg border bg-card/80 p-4 shadow-sm backdrop-blur"
                        >
                            <dt class="text-lg font-semibold text-primary">
                                {{ metric.value }}
                            </dt>
                            <dd
                                class="mt-1 text-sm leading-5 text-muted-foreground"
                            >
                                {{ metric.label }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>

        <section class="border-y bg-card/70 py-5">
            <div
                class="mx-auto flex max-w-[92rem] flex-col gap-3 px-5 text-sm text-muted-foreground sm:px-8 lg:flex-row lg:items-center lg:justify-between xl:px-10"
            >
                <p class="font-medium text-foreground">
                    One operating loop for service discovery, verification,
                    bookings, subscriptions, wallets, and reports.
                </p>
                <div class="flex flex-wrap gap-2">
                    <span
                        class="rounded-full bg-muted px-3 py-1 text-muted-foreground"
                        >Customers</span
                    >
                    <span
                        class="rounded-full bg-primary px-3 py-1 text-primary-foreground"
                        >Artisans</span
                    >
                    <span
                        class="rounded-full bg-secondary px-3 py-1 text-secondary-foreground"
                        >Operations</span
                    >
                </div>
            </div>
        </section>

        <section
            class="mx-auto grid max-w-[92rem] gap-8 px-5 py-16 sm:px-8 lg:grid-cols-[0.8fr_1.2fr] lg:py-20 xl:px-10"
        >
            <div>
                <p class="text-sm font-medium text-primary">
                    Trust architecture
                </p>
                <h2
                    class="mt-3 max-w-xl text-3xl leading-tight font-semibold sm:text-4xl"
                >
                    The marketplace is only as good as the verification behind
                    it.
                </h2>
                <p class="mt-5 max-w-xl leading-7 text-muted-foreground">
                    Lartisan connects public discovery with private evidence,
                    scoped operations, and auditable financial movement so the
                    trust loop does not disappear after a booking is made.
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <article
                    v-for="signal in trustSignals"
                    :key="signal.title"
                    class="group rounded-lg border bg-card p-5 text-card-foreground shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl motion-reduce:hover:translate-y-0"
                >
                    <div
                        class="grid size-11 place-items-center rounded-lg bg-primary/10 text-primary transition group-hover:bg-primary group-hover:text-primary-foreground"
                    >
                        <component :is="signal.icon" class="size-5" />
                    </div>
                    <h3 class="mt-5 font-semibold">{{ signal.title }}</h3>
                    <p class="mt-3 text-sm leading-6 text-muted-foreground">
                        {{ signal.text }}
                    </p>
                </article>
            </div>
        </section>

        <section class="bg-brand py-16 text-brand-foreground lg:py-20">
            <div
                class="mx-auto grid max-w-[92rem] gap-10 px-5 sm:px-8 lg:grid-cols-[0.86fr_1.14fr] lg:items-center xl:px-10"
            >
                <div>
                    <p class="text-sm font-medium text-brand-foreground/70">
                        Booking lifecycle
                    </p>
                    <h2
                        class="mt-3 text-3xl leading-tight font-semibold sm:text-4xl"
                    >
                        From search to completion, every handoff has a state.
                    </h2>
                    <p class="mt-5 leading-7 text-brand-foreground/70">
                        Guests can book, customers can track, artisans can act,
                        and the back office can review the trail when something
                        needs attention.
                    </p>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <article
                        v-for="(step, index) in steps"
                        :key="step.title"
                        class="relative rounded-lg border border-brand-foreground/15 bg-brand-foreground/10 p-5 transition duration-300 hover:bg-brand-foreground/15"
                    >
                        <span class="text-sm text-brand-foreground/50"
                            >0{{ index + 1 }}</span
                        >
                        <div
                            class="mt-4 grid size-11 place-items-center rounded-lg bg-brand-foreground text-brand"
                        >
                            <component :is="step.icon" class="size-5" />
                        </div>
                        <h3 class="mt-5 text-lg font-semibold">
                            {{ step.title }}
                        </h3>
                        <p
                            class="mt-3 text-sm leading-6 text-brand-foreground/70"
                        >
                            {{ step.text }}
                        </p>
                    </article>
                </div>
            </div>
        </section>

        <section
            class="mx-auto max-w-[92rem] px-5 py-16 sm:px-8 lg:py-20 xl:px-10"
        >
            <div
                class="flex flex-col gap-5 md:flex-row md:items-end md:justify-between"
            >
                <div>
                    <p class="text-sm font-medium text-primary">
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
                    class="inline-flex items-center gap-2 text-sm font-medium text-primary transition hover:gap-3"
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
                    class="group rounded-lg border bg-card p-6 text-card-foreground shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl motion-reduce:hover:translate-y-0"
                >
                    <h3 class="text-xl font-semibold">{{ path.title }}</h3>
                    <p
                        class="mt-4 min-h-20 text-sm leading-6 text-muted-foreground"
                    >
                        {{ path.text }}
                    </p>
                    <Link
                        :href="path.href"
                        class="mt-8 inline-flex items-center gap-2 text-sm font-medium text-primary transition group-hover:gap-3"
                    >
                        {{ path.action }}
                        <ArrowRight class="size-4" />
                    </Link>
                </article>
            </div>
        </section>

        <section class="px-5 pb-16 sm:px-8 xl:px-10">
            <div
                class="mx-auto max-w-[92rem] overflow-hidden rounded-lg border bg-card text-card-foreground"
            >
                <div
                    class="grid gap-8 p-8 md:grid-cols-[1fr_auto] md:items-center lg:p-12"
                >
                    <div>
                        <div class="flex items-center gap-2 text-primary">
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
                            class="h-12 rounded-full bg-primary px-6 text-primary-foreground hover:bg-primary/90"
                        >
                            <Link :href="marketplaceIndex().url" prefetch>
                                Find service
                            </Link>
                        </Button>
                        <Button
                            as-child
                            size="lg"
                            variant="outline"
                            class="h-12 rounded-full px-6"
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
.scene-shell::before {
    position: absolute;
    inset-block: 0;
    left: -10rem;
    z-index: 2;
    width: 16rem;
    background: linear-gradient(
        90deg,
        var(--background) 0%,
        color-mix(in srgb, var(--background) 82%, transparent) 38%,
        transparent 100%
    );
    content: '';
    pointer-events: none;
}

.artisan-scene {
    background:
        linear-gradient(
            90deg,
            color-mix(in srgb, var(--background) 96%, transparent) 0%,
            color-mix(in srgb, var(--brand) 78%, transparent) 48%,
            color-mix(in srgb, var(--background) 92%, var(--brand)) 100%
        ),
        var(--brand);
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
    border-color: color-mix(in srgb, var(--primary) 58%, transparent);
    background: color-mix(in srgb, var(--brand) 36%, transparent);
}

.process-icon {
    display: grid;
    width: 2.6rem;
    height: 2.6rem;
    place-items: center;
    border-radius: 0.5rem;
    background: color-mix(in srgb, var(--primary) 16%, white);
    color: var(--primary);
}

.process-node-active .process-icon {
    background: var(--brand);
    color: var(--brand-foreground);
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
    background: var(--primary);
    box-shadow: 0 0 22px color-mix(in srgb, var(--primary) 78%, transparent);
    animation: process-travel 3.8s ease-in-out infinite;
}

.process-connector-delayed span {
    animation-delay: 1.35s;
}

.mobile-process-strip {
    display: flex;
    align-items: stretch;
    gap: 0.35rem;
    border: 1px solid color-mix(in srgb, var(--primary) 24%, var(--border));
    border-radius: 0.5rem;
    background: color-mix(in srgb, var(--card) 92%, var(--primary));
    box-shadow: 0 16px 40px color-mix(in srgb, var(--primary) 12%, transparent);
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
    background: color-mix(in srgb, var(--primary) 16%, var(--card));
    color: var(--primary);
}

.mobile-process-title {
    margin-top: 0.45rem;
    color: var(--foreground);
    font-size: 0.75rem;
    font-weight: 600;
}

.mobile-process-text {
    margin-top: 0.15rem;
    color: var(--muted-foreground);
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
    background: color-mix(in srgb, var(--primary) 18%, var(--border));
}

.mobile-process-link span {
    position: absolute;
    inset-block: 0;
    width: 55%;
    border-radius: inherit;
    background: var(--primary);
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
