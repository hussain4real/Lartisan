<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    BadgeCheck,
    CheckCircle2,
    CircleMinus,
    LayoutGrid,
    ShieldCheck,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard, home, login } from '@/routes';
import { index as marketplaceIndex } from '@/routes/marketplace';

type PricingPlan = {
    id: number;
    name: string;
    slug: string;
    badge: string | null;
    description: string;
    priceAmount: number;
    price: string;
    currencyCode: string;
    interval: string;
    durationDays: number;
    features: string[];
    includesTeamManagement: boolean;
    highlighted: boolean;
};

type ComparisonRow = {
    label: string;
    values: Record<string, string>;
};

type Props = {
    plans: PricingPlan[];
    comparisonRows: ComparisonRow[];
    cta: {
        label: string;
        href: string;
    };
};

const props = defineProps<Props>();
const page = usePage();
const dashboardUrl = computed(() =>
    page.props.currentTeam ? dashboard(page.props.currentTeam.slug).url : '/',
);

const intervalLabel = (plan: PricingPlan) => {
    if (plan.interval === 'monthly') {
        return 'per month';
    }

    if (plan.interval === 'quarterly') {
        return 'per quarter';
    }

    if (plan.interval === 'annual') {
        return 'per year';
    }

    return plan.interval;
};

const comparisonValue = (row: ComparisonRow, plan: PricingPlan) =>
    row.values[plan.slug] ?? 'Not included';

const isIncluded = (value: string) => value !== 'Not included';
</script>

<template>
    <Head title="Pricing" />

    <main class="min-h-screen bg-background text-foreground">
        <header
            class="mx-auto flex w-full max-w-7xl items-center justify-between gap-4 px-5 py-5 sm:px-8"
        >
            <Link :href="home().url" class="flex items-center gap-3" prefetch>
                <span class="rounded-lg bg-card px-3 py-2 shadow-sm">
                    <AppLogo class="h-8" />
                </span>
            </Link>

            <nav
                class="flex items-center gap-1 rounded-full border bg-card p-1 text-sm shadow-sm"
                aria-label="Primary"
            >
                <Link
                    :href="marketplaceIndex().url"
                    class="hidden rounded-full px-4 py-2 text-muted-foreground transition hover:bg-accent hover:text-accent-foreground sm:inline-flex"
                    prefetch
                >
                    Marketplace
                </Link>
                <ThemeSwitcher
                    class="text-muted-foreground hover:bg-accent hover:text-accent-foreground"
                />
                <Link
                    v-if="page.props.auth.user"
                    :href="dashboardUrl"
                    class="rounded-full px-4 py-2 text-muted-foreground transition hover:bg-accent hover:text-accent-foreground"
                >
                    Dashboard
                </Link>
                <Link
                    v-else
                    :href="login().url"
                    class="rounded-full px-4 py-2 text-muted-foreground transition hover:bg-accent hover:text-accent-foreground"
                >
                    Log in
                </Link>
            </nav>
        </header>

        <section class="border-y bg-card/50">
            <div
                class="mx-auto grid max-w-7xl gap-10 px-5 py-14 sm:px-8 lg:grid-cols-[0.92fr_1.08fr] lg:items-center lg:py-18"
            >
                <div>
                    <Badge variant="outline" class="gap-1.5">
                        <ShieldCheck class="size-3.5" />
                        Artisan subscriptions
                    </Badge>
                    <h1
                        class="mt-5 max-w-3xl text-4xl leading-tight font-semibold sm:text-5xl"
                    >
                        Choose the visibility and team depth your artisan
                        business needs.
                    </h1>
                    <p class="mt-5 max-w-2xl leading-7 text-muted-foreground">
                        Every plan keeps your marketplace presence active. Pro
                        and Premium add team management for businesses that need
                        admins, collaborators, and controlled membership.
                    </p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <Button as-child size="lg" class="h-12 rounded-full">
                            <Link :href="props.cta.href">
                                {{ props.cta.label }}
                                <ArrowRight class="size-4" />
                            </Link>
                        </Button>
                        <Button
                            as-child
                            size="lg"
                            variant="outline"
                            class="h-12 rounded-full"
                        >
                            <Link :href="marketplaceIndex().url" prefetch>
                                Browse marketplace
                            </Link>
                        </Button>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="rounded-lg border bg-background p-5">
                        <LayoutGrid class="size-6 text-primary" />
                        <p class="mt-4 font-medium">Listing stays active</p>
                        <p class="mt-2 text-sm leading-6 text-muted-foreground">
                            Public discovery, lead access, and profile controls
                            stay attached to subscription health.
                        </p>
                    </div>
                    <div class="rounded-lg border bg-background p-5">
                        <Users class="size-6 text-primary" />
                        <p class="mt-4 font-medium">Teams start at Pro</p>
                        <p class="mt-2 text-sm leading-6 text-muted-foreground">
                            Invite and manage collaborators only after a Pro or
                            Premium subscription is active.
                        </p>
                    </div>
                    <div class="rounded-lg border bg-background p-5">
                        <BadgeCheck class="size-6 text-primary" />
                        <p class="mt-4 font-medium">Built for trust</p>
                        <p class="mt-2 text-sm leading-6 text-muted-foreground">
                            Verification, visibility, and subscription state
                            stay aligned across the marketplace.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-5 py-14 sm:px-8 lg:py-18">
            <div class="grid gap-5 lg:grid-cols-3">
                <article
                    v-for="plan in plans"
                    :key="plan.id"
                    class="flex min-h-[34rem] flex-col rounded-lg border bg-card p-6 text-card-foreground shadow-sm"
                    :class="
                        plan.highlighted
                            ? 'border-primary shadow-lg shadow-primary/10'
                            : ''
                    "
                >
                    <div class="flex min-h-24 flex-col gap-3">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="text-2xl font-semibold">
                                    {{ plan.name }}
                                </h2>
                                <p class="mt-2 text-sm text-muted-foreground">
                                    {{ plan.durationDays }} days access
                                </p>
                            </div>
                            <Badge v-if="plan.badge" class="shrink-0">
                                {{ plan.badge }}
                            </Badge>
                        </div>
                        <p class="leading-7 text-muted-foreground">
                            {{ plan.description }}
                        </p>
                    </div>

                    <div class="mt-7">
                        <span class="text-4xl font-semibold">
                            {{ plan.currencyCode }} {{ plan.price }}
                        </span>
                        <span class="ml-2 text-sm text-muted-foreground">
                            {{ intervalLabel(plan) }}
                        </span>
                    </div>

                    <div
                        class="mt-6 rounded-lg border bg-background p-4 text-sm"
                    >
                        <div class="flex items-center gap-2">
                            <component
                                :is="
                                    plan.includesTeamManagement
                                        ? CheckCircle2
                                        : CircleMinus
                                "
                                class="size-4"
                                :class="
                                    plan.includesTeamManagement
                                        ? 'text-primary'
                                        : 'text-muted-foreground'
                                "
                            />
                            <span>
                                {{
                                    plan.includesTeamManagement
                                        ? 'Team management included'
                                        : 'Team management starts at Pro'
                                }}
                            </span>
                        </div>
                    </div>

                    <ul class="mt-6 grid gap-3 text-sm">
                        <li
                            v-for="feature in plan.features"
                            :key="feature"
                            class="flex gap-2"
                        >
                            <CheckCircle2
                                class="mt-0.5 size-4 shrink-0 text-primary"
                            />
                            <span>{{ feature }}</span>
                        </li>
                    </ul>

                    <Button
                        as-child
                        class="mt-auto h-11 rounded-full"
                        :variant="plan.highlighted ? 'default' : 'outline'"
                    >
                        <Link :href="props.cta.href">
                            {{ props.cta.label }}
                        </Link>
                    </Button>
                </article>
            </div>
        </section>

        <section class="border-t bg-card/50">
            <div class="mx-auto max-w-7xl px-5 py-14 sm:px-8 lg:py-18">
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
                >
                    <div>
                        <p class="text-sm font-medium text-primary">
                            Compare plans
                        </p>
                        <h2 class="mt-2 text-3xl font-semibold">Plan matrix</h2>
                    </div>
                    <p class="max-w-2xl leading-7 text-muted-foreground">
                        The matrix reflects the current Basic, Pro, and Premium
                        mapping while preserving the existing plan slugs behind
                        the scenes.
                    </p>
                </div>

                <div
                    class="mt-8 overflow-x-auto rounded-lg border bg-background"
                >
                    <table class="w-full min-w-[760px] text-sm">
                        <thead>
                            <tr class="border-b bg-muted/40">
                                <th
                                    class="w-56 px-4 py-4 text-left font-medium"
                                >
                                    Feature
                                </th>
                                <th
                                    v-for="plan in plans"
                                    :key="plan.slug"
                                    class="px-4 py-4 text-left font-medium"
                                >
                                    {{ plan.name }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in comparisonRows"
                                :key="row.label"
                                class="border-b last:border-b-0"
                            >
                                <th
                                    class="px-4 py-4 text-left font-medium text-foreground"
                                >
                                    {{ row.label }}
                                </th>
                                <td
                                    v-for="plan in plans"
                                    :key="`${row.label}-${plan.slug}`"
                                    class="px-4 py-4 text-muted-foreground"
                                >
                                    <span
                                        class="inline-flex items-center gap-2"
                                    >
                                        <component
                                            :is="
                                                isIncluded(
                                                    comparisonValue(row, plan),
                                                )
                                                    ? CheckCircle2
                                                    : CircleMinus
                                            "
                                            class="size-4"
                                            :class="
                                                isIncluded(
                                                    comparisonValue(row, plan),
                                                )
                                                    ? 'text-primary'
                                                    : 'text-muted-foreground'
                                            "
                                        />
                                        {{ comparisonValue(row, plan) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </main>
</template>
