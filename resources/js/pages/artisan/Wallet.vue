<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Landmark, WalletCards } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { dashboard as artisanDashboard } from '@/routes/artisan';
import type {
    ArtisanWalletSummary,
    PayoutAccountItem,
    Team,
    WalletLedgerEntryItem,
} from '@/types';

type Props = {
    currentTeam: Team;
    profile: {
        id: number;
        businessName: string;
    };
    wallet: ArtisanWalletSummary;
    ledgerEntries: WalletLedgerEntryItem[];
    payoutAccounts: PayoutAccountItem[];
};

defineProps<Props>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            {
                title: 'Artisan',
                href: artisanDashboard(props.currentTeam.slug).url,
            },
            {
                title: 'Wallet',
                href: '#',
            },
        ],
    }),
});
</script>

<template>
    <Head title="Artisan wallet" />

    <h1 class="sr-only">Artisan wallet</h1>

    <div class="flex flex-col gap-8 p-4 sm:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                variant="small"
                title="Wallet"
                :description="profile.businessName"
            />
            <Badge variant="secondary" class="w-fit gap-1.5">
                <WalletCards class="size-3.5" />
                {{ wallet.currencyCode }}
            </Badge>
        </div>

        <section class="grid gap-4 md:grid-cols-2">
            <div class="rounded-lg border p-5">
                <p class="text-sm text-muted-foreground">Available</p>
                <p class="mt-3 text-3xl font-semibold">
                    {{ wallet.currencyCode }} {{ wallet.availableDisplay }}
                </p>
            </div>
            <div class="rounded-lg border p-5">
                <p class="text-sm text-muted-foreground">Pending</p>
                <p class="mt-3 text-3xl font-semibold">
                    {{ wallet.currencyCode }} {{ wallet.pendingDisplay }}
                </p>
            </div>
        </section>

        <section class="space-y-4">
            <Heading variant="small" title="Ledger" />

            <div v-if="ledgerEntries.length > 0" class="grid gap-3">
                <div
                    v-for="entry in ledgerEntries"
                    :key="entry.id"
                    class="grid gap-3 rounded-lg border p-4 sm:grid-cols-[1fr_auto] sm:items-center"
                >
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-medium">{{ entry.type }}</h2>
                            <Badge variant="outline">{{
                                entry.direction
                            }}</Badge>
                        </div>
                        <p
                            v-if="entry.description"
                            class="text-sm text-muted-foreground"
                        >
                            {{ entry.description }}
                        </p>
                        <p class="text-xs break-all text-muted-foreground">
                            {{ entry.immutableReference }}
                        </p>
                    </div>
                    <div class="text-sm font-medium">
                        {{ wallet.currencyCode }} {{ entry.amountDisplay }}
                    </div>
                </div>
            </div>

            <p
                v-else
                class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                No ledger entries yet.
            </p>
        </section>

        <section class="space-y-4">
            <Heading variant="small" title="Payout accounts" />

            <div v-if="payoutAccounts.length > 0" class="grid gap-3">
                <div
                    v-for="account in payoutAccounts"
                    :key="account.id"
                    class="grid gap-3 rounded-lg border p-4 sm:grid-cols-[1fr_auto] sm:items-center"
                >
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <Landmark class="size-4" />
                            <h2 class="font-medium">{{ account.bankName }}</h2>
                            <Badge variant="outline">{{
                                account.status
                            }}</Badge>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ account.accountName }}
                        </p>
                    </div>
                    <div class="text-sm text-muted-foreground">
                        {{ account.provider }}
                    </div>
                </div>
            </div>

            <p
                v-else
                class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                No payout accounts yet.
            </p>
        </section>
    </div>
</template>
