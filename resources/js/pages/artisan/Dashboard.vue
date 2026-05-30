<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BriefcaseBusiness,
    ClipboardCheck,
    Images,
    ShieldCheck,
    Wrench,
} from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard as artisanDashboard } from '@/routes/artisan';
import { show as kycShow } from '@/routes/artisan/kyc';
import { edit as profileEdit } from '@/routes/artisan/profile';
import { index as servicesIndex } from '@/routes/artisan/services';
import type {
    ArtisanDashboardMetrics,
    ArtisanDashboardProfile,
    ArtisanDashboardService,
    ArtisanKycSummary,
    Team,
} from '@/types';

type Props = {
    currentTeam: Team;
    profile: ArtisanDashboardProfile;
    metrics: ArtisanDashboardMetrics;
    latestKyc: ArtisanKycSummary | null;
    recentServices: ArtisanDashboardService[];
};

defineProps<Props>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            {
                title: 'Artisan',
                href: artisanDashboard(props.currentTeam.slug).url,
            },
        ],
    }),
});
</script>

<template>
    <Head :title="profile.businessName" />

    <h1 class="sr-only">{{ profile.businessName }}</h1>

    <div class="flex flex-col gap-8">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                variant="small"
                :title="profile.businessName"
                description="Artisan business workspace"
            />

            <div class="flex flex-wrap gap-2">
                <Badge variant="secondary" class="gap-1.5">
                    <ShieldCheck class="size-3.5" />
                    {{ profile.verificationStatus }}
                </Badge>
                <Badge variant="outline">
                    {{ profile.availabilityStatus }}
                </Badge>
            </div>
        </div>

        <section class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-lg border p-4">
                <div
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <Wrench class="size-4" />
                    Services
                </div>
                <p class="mt-3 text-2xl font-semibold">
                    {{ metrics.services }}
                </p>
            </div>
            <div class="rounded-lg border p-4">
                <div
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <ClipboardCheck class="size-4" />
                    KYC submissions
                </div>
                <p class="mt-3 text-2xl font-semibold">
                    {{ metrics.kycSubmissions }}
                </p>
            </div>
            <div class="rounded-lg border p-4">
                <div
                    class="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <BriefcaseBusiness class="size-4" />
                    Field visits
                </div>
                <p class="mt-3 text-2xl font-semibold">
                    {{ metrics.fieldVisits }}
                </p>
            </div>
        </section>

        <section class="grid gap-5 lg:grid-cols-[1fr_1fr]">
            <div class="space-y-4 rounded-lg border p-5">
                <div class="flex items-center justify-between gap-3">
                    <Heading variant="small" title="Public listing" />
                    <Button as-child variant="outline" size="sm">
                        <Link :href="profileEdit(currentTeam.slug).url">
                            <Images />
                            Edit
                        </Link>
                    </Button>
                </div>
                <div class="flex items-center justify-between gap-4 text-sm">
                    <span class="text-muted-foreground">Listing status</span>
                    <Badge
                        :variant="profile.isPublic ? 'default' : 'secondary'"
                    >
                        {{ profile.isPublic ? 'public' : 'private' }}
                    </Badge>
                </div>
            </div>

            <div class="space-y-4 rounded-lg border p-5">
                <div class="flex items-center justify-between gap-3">
                    <Heading variant="small" title="Verification" />
                    <Button as-child variant="outline" size="sm">
                        <Link :href="kycShow(currentTeam.slug).url">
                            <ClipboardCheck />
                            KYC
                        </Link>
                    </Button>
                </div>
                <div class="text-sm text-muted-foreground">
                    <span v-if="latestKyc">
                        {{ latestKyc.status }}
                    </span>
                    <span v-else>No KYC submission</span>
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <Heading variant="small" title="Services" />
                <Button as-child variant="outline" size="sm">
                    <Link :href="servicesIndex(currentTeam.slug).url">
                        <Wrench />
                        Manage
                    </Link>
                </Button>
            </div>

            <div v-if="recentServices.length > 0" class="grid gap-3">
                <div
                    v-for="service in recentServices"
                    :key="service.id"
                    class="grid gap-2 rounded-lg border p-4 sm:grid-cols-[1fr_auto] sm:items-center"
                >
                    <div>
                        <h2 class="font-medium">{{ service.title }}</h2>
                        <p class="text-sm text-muted-foreground">
                            {{ service.category }}
                        </p>
                    </div>
                    <Badge variant="outline">{{ service.status }}</Badge>
                </div>
            </div>

            <p
                v-else
                class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                No services yet.
            </p>
        </section>
    </div>
</template>
