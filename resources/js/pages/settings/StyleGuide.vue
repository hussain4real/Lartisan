<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCircle2,
    Clock3,
    CreditCard,
    Hammer,
    LayoutPanelLeft,
    MapPin,
    MessageSquare,
    Palette,
    ShieldCheck,
    Type,
    XCircle,
} from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { edit as editStyleGuide } from '@/routes/style-guide';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Style guide',
                href: editStyleGuide(),
            },
        ],
    },
});

const palette = [
    {
        name: 'Leadprenuer blue',
        tier: 'Primary',
        role: 'Primary action, navigation, trust anchor',
        value: '#001c72',
        class: 'bg-[#001c72]',
    },
    {
        name: 'Lartisan orange',
        tier: 'Secondary',
        role: 'Secondary actions, review states, attention',
        value: '#f59e0b',
        class: 'bg-[#f59e0b]',
    },
    {
        name: 'Operational gray',
        tier: 'Tertiary',
        role: 'Tertiary actions, subdued labels',
        value: '#6c757d',
        class: 'bg-[#6c757d]',
    },
    {
        name: 'Ink',
        tier: 'Tertiary',
        role: 'Text emphasis and dense data',
        value: '#1d1d1d',
        class: 'bg-[#1d1d1d]',
    },
    {
        name: 'Risk red',
        tier: 'Tertiary',
        role: 'Disputes, failed payments, destructive actions',
        value: '#dc2626',
        class: 'bg-[#dc2626]',
    },
    {
        name: 'Surface',
        tier: 'Tertiary',
        role: 'Quiet app background and work surfaces',
        value: '#f8fafc',
        class: 'bg-[#f8fafc]',
    },
];

const journeyCards = [
    {
        title: 'Customer discovery',
        description: 'Search, compare, book, pay, confirm, review.',
        icon: MapPin,
    },
    {
        title: 'Artisan operations',
        description: 'Profile, KYC, catalog, availability, wallet, payouts.',
        icon: Hammer,
    },
    {
        title: 'Field verification',
        description: 'Agent visits, evidence capture, LGA review, escalation.',
        icon: ShieldCheck,
    },
    {
        title: 'Finance flow',
        description: 'Checkout, commission, ledger entries, payout review.',
        icon: CreditCard,
    },
];

const statusPatterns = [
    {
        label: 'Approved',
        icon: CheckCircle2,
        class: 'border-[#001c72]/40 bg-[#001c72]/10 text-[#001c72] dark:border-blue-300/50 dark:bg-blue-300/15 dark:text-blue-100',
    },
    {
        label: 'Pending',
        icon: Clock3,
        class: 'border-[#f59e0b]/50 bg-[#f59e0b]/15 text-[#7c3f00] dark:border-orange-300/50 dark:bg-orange-300/15 dark:text-orange-100',
    },
    {
        label: 'Escalated',
        icon: AlertTriangle,
        class: 'border-orange-300 bg-orange-50 text-orange-800 dark:border-orange-700 dark:bg-orange-950/40 dark:text-orange-200',
    },
    {
        label: 'Rejected',
        icon: XCircle,
        class: 'border-red-300 bg-red-50 text-red-800 dark:border-red-700 dark:bg-red-950/40 dark:text-red-200',
    },
];
</script>

<template>
    <div class="space-y-8">
        <Head title="Style guide" />

        <h1 class="sr-only">Lartisan style guide</h1>

        <section class="space-y-3">
            <Badge variant="outline" class="w-fit"
                >Lartisan design system</Badge
            >
            <Heading
                variant="small"
                title="Premium local-service operations"
                description="A practical visual system for customers, artisans, field agents, LGA admins, and state operations."
            />
        </section>

        <section class="grid gap-4 lg:grid-cols-[1.15fr_0.85fr]">
            <div
                class="rounded-lg border bg-card p-5 text-card-foreground shadow-xs"
            >
                <div class="mb-4 flex items-center gap-2">
                    <Palette class="size-5 text-primary" />
                    <h2 class="text-base font-semibold">Color roles</h2>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="color in palette"
                        :key="color.name"
                        class="overflow-hidden rounded-lg border bg-background"
                    >
                        <div :class="['h-16', color.class]" />
                        <div class="space-y-1 p-3">
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <div>
                                    <p class="text-sm font-medium">
                                        {{ color.name }}
                                    </p>
                                    <p
                                        class="text-[0.6875rem] font-medium text-muted-foreground uppercase"
                                    >
                                        {{ color.tier }}
                                    </p>
                                </div>
                                <code class="text-xs text-muted-foreground">
                                    {{ color.value }}
                                </code>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                {{ color.role }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="rounded-lg border bg-primary p-5 text-primary-foreground shadow-xs"
            >
                <div class="mb-5 flex items-center gap-2">
                    <Type class="size-5" />
                    <h2 class="text-base font-semibold">Voice and type</h2>
                </div>

                <div class="space-y-4">
                    <p class="text-3xl leading-tight font-semibold">
                        Built for trust at the point of service.
                    </p>
                    <p class="text-sm/6 text-primary-foreground/80">
                        Labels stay direct, actions use active verbs, and dense
                        operations screens prioritize scannable status, owner,
                        location, and next-step information.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <Badge class="bg-white/15 text-white">Verified</Badge>
                        <Badge class="bg-white/15 text-white">Local</Badge>
                        <Badge class="bg-white/15 text-white">Auditable</Badge>
                    </div>
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <div class="flex items-center gap-2">
                <LayoutPanelLeft class="size-5 text-primary" />
                <h2 class="text-base font-semibold">Interface patterns</h2>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="item in journeyCards"
                    :key="item.title"
                    class="rounded-lg border bg-card p-4 shadow-xs"
                >
                    <component
                        :is="item.icon"
                        class="mb-4 size-5 text-primary"
                    />
                    <h3 class="text-sm font-semibold">{{ item.title }}</h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ item.description }}
                    </p>
                </div>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-lg border bg-card p-5 shadow-xs">
                <div class="mb-4 flex items-center gap-2">
                    <MessageSquare class="size-5 text-primary" />
                    <h2 class="text-base font-semibold">Status language</h2>
                </div>

                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="status in statusPatterns"
                        :key="status.label"
                        :class="[
                            'inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-sm font-medium',
                            status.class,
                        ]"
                    >
                        <component :is="status.icon" class="size-4" />
                        {{ status.label }}
                    </span>
                </div>
            </div>

            <div class="rounded-lg border bg-card p-5 shadow-xs">
                <h2 class="mb-4 text-base font-semibold">Action hierarchy</h2>

                <div class="flex flex-wrap gap-3">
                    <Button>Approve profile</Button>
                    <Button variant="secondary">Assign agent</Button>
                    <Button variant="outline">View evidence</Button>
                    <Button variant="destructive">Suspend</Button>
                </div>
            </div>
        </section>
    </div>
</template>
