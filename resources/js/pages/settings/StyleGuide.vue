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

const colorRoles = [
    {
        name: 'Brand surface',
        token: 'brand',
        role: 'Logo blocks, brand bands, trust-heavy hero surfaces.',
        light: 'hsl(225 100% 22.4%)',
        dark: 'hsl(225 72% 18%)',
    },
    {
        name: 'Primary action',
        token: 'primary',
        role: 'Main commitments, links, focus rings, active navigation.',
        light: 'hsl(225 100% 22.4%)',
        dark: 'hsl(225 100% 66%)',
    },
    {
        name: 'Secondary / warning',
        token: 'secondary, warning',
        role: 'Attention, review, pending, retry, and secondary actions.',
        light: 'hsl(38 92.1% 50.2%)',
        dark: 'hsl(38 92.1% 62%)',
    },
    {
        name: 'Success',
        token: 'success',
        role: 'Confirmed saves, copied codes, verified completions.',
        light: 'hsl(160 84% 31%)',
        dark: 'hsl(152 76% 48%)',
    },
    {
        name: 'Destructive',
        token: 'destructive',
        role: 'Failed payments, rejected KYC, deletion, suspension.',
        light: 'hsl(0 84.2% 60.2%)',
        dark: 'hsl(0 84% 60%)',
    },
    {
        name: 'App surface',
        token: 'background',
        role: 'Default page background across public and app shells.',
        light: 'hsl(210 24% 98%)',
        dark: 'hsl(225 48% 7%)',
    },
    {
        name: 'Card surface',
        token: 'card',
        role: 'Cards, panels, forms, popovers, and repeated records.',
        light: 'hsl(0 0% 100%)',
        dark: 'hsl(225 42% 10%)',
    },
    {
        name: 'Text',
        token: 'foreground',
        role: 'Primary body copy, headings, dense operational values.',
        light: 'hsl(0 0% 11.4%)',
        dark: 'hsl(210 24% 96%)',
    },
    {
        name: 'Muted text',
        token: 'muted-foreground',
        role: 'Descriptions, metadata, helper copy, secondary labels.',
        light: 'hsl(210 7.3% 37%)',
        dark: 'hsl(214 20% 70%)',
    },
    {
        name: 'Border',
        token: 'border',
        role: 'Dividers, cards, tables, input boundaries.',
        light: 'hsl(214 28% 88%)',
        dark: 'hsl(225 24% 20%)',
    },
];

const usageRules = [
    'Use deep brand blue for light-mode brand panels and primary CTAs.',
    'Use lighter primary in dark mode for links, actions, rings, and active states.',
    'Use brand in dark mode for larger surfaces, not dense text or small icons.',
    'Use warning for pending/review/retry, success for confirmation, and destructive only for risk.',
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
        class: 'border-primary/30 bg-primary/10 text-primary',
    },
    {
        label: 'Pending',
        icon: Clock3,
        class: 'border-warning/40 bg-warning/15 text-warning',
    },
    {
        label: 'Escalated',
        icon: AlertTriangle,
        class: 'border-warning/40 bg-warning/10 text-warning',
    },
    {
        label: 'Rejected',
        icon: XCircle,
        class: 'border-destructive/30 bg-destructive/10 text-destructive',
    },
];
</script>

<template>
    <div class="space-y-8">
        <Head title="Style guide" />

        <h1 class="sr-only">Lartisan style guide</h1>

        <section class="space-y-3">
            <Badge variant="outline" class="w-fit">
                Lartisan design system
            </Badge>
            <Heading
                variant="small"
                title="Premium local-service operations"
                description="A practical visual system for customers, artisans, field agents, LGA admins, and state operations."
            />
        </section>

        <section class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
            <div
                class="rounded-lg border bg-card p-5 text-card-foreground shadow-xs"
            >
                <div class="mb-4 flex items-center gap-2">
                    <Palette class="size-5 text-primary" />
                    <h2 class="text-base font-semibold">
                        Light and dark color roles
                    </h2>
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                    <div
                        v-for="color in colorRoles"
                        :key="color.name"
                        class="rounded-lg border bg-background p-3"
                    >
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <div
                                    class="h-12 rounded-md border"
                                    :style="{ backgroundColor: color.light }"
                                />
                                <p
                                    class="mt-1 text-[0.6875rem] font-medium text-muted-foreground uppercase"
                                >
                                    Light
                                </p>
                                <code class="text-xs text-muted-foreground">
                                    {{ color.light }}
                                </code>
                            </div>
                            <div>
                                <div
                                    class="h-12 rounded-md border"
                                    :style="{ backgroundColor: color.dark }"
                                />
                                <p
                                    class="mt-1 text-[0.6875rem] font-medium text-muted-foreground uppercase"
                                >
                                    Dark
                                </p>
                                <code class="text-xs text-muted-foreground">
                                    {{ color.dark }}
                                </code>
                            </div>
                        </div>
                        <div class="mt-3 space-y-1">
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-sm font-medium">
                                    {{ color.name }}
                                </p>
                                <code class="text-xs text-muted-foreground">
                                    {{ color.token }}
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
                class="rounded-lg border bg-brand p-5 text-brand-foreground shadow-xs"
            >
                <div class="mb-5 flex items-center gap-2">
                    <Type class="size-5" />
                    <h2 class="text-base font-semibold">Theme rules</h2>
                </div>

                <div class="space-y-4">
                    <p class="text-3xl leading-tight font-semibold">
                        Built for trust at the point of service.
                    </p>
                    <ul class="space-y-3 text-sm/6 text-brand-foreground/80">
                        <li v-for="rule in usageRules" :key="rule">
                            {{ rule }}
                        </li>
                    </ul>
                    <div class="flex flex-wrap gap-2">
                        <Badge
                            class="bg-brand-foreground/15 text-brand-foreground"
                        >
                            Verified
                        </Badge>
                        <Badge
                            class="bg-brand-foreground/15 text-brand-foreground"
                        >
                            Local
                        </Badge>
                        <Badge
                            class="bg-brand-foreground/15 text-brand-foreground"
                        >
                            Auditable
                        </Badge>
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
