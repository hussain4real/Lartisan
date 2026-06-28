<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Landmark, Send, WalletCards } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard as artisanDashboard } from '@/routes/artisan';
import { store as requestPayout } from '@/routes/artisan/wallet/payouts';
import type {
    ArtisanWalletSummary,
    PayoutAccountItem,
    PayoutItem,
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
    payouts: PayoutItem[];
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
            <Heading variant="small" title="Request payout" />

            <Form
                v-if="
                    payoutAccounts.some(
                        (account) => account.status === 'verified',
                    )
                "
                v-bind="requestPayout.form(currentTeam.slug)"
                class="grid gap-4 rounded-lg border p-5"
                #default="{ errors, processing }"
            >
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="grid gap-2 text-sm">
                        Account
                        <select
                            name="payout_account_id"
                            class="h-10 rounded-md border bg-background px-3"
                            required
                        >
                            <option
                                v-for="account in payoutAccounts.filter(
                                    (item) => item.status === 'verified',
                                )"
                                :key="account.id"
                                :value="account.id"
                            >
                                {{ account.bankName }} -
                                {{ account.accountName }}
                            </option>
                        </select>
                        <span
                            v-if="errors.payout_account_id"
                            class="text-destructive"
                        >
                            {{ errors.payout_account_id }}
                        </span>
                    </label>
                    <label class="grid gap-2 text-sm">
                        Amount
                        <input
                            name="amount"
                            type="number"
                            min="1"
                            step="0.01"
                            class="h-10 rounded-md border bg-background px-3"
                            required
                        />
                        <span v-if="errors.amount" class="text-destructive">
                            {{ errors.amount }}
                        </span>
                    </label>
                </div>
                <label class="grid gap-2 text-sm">
                    Notes
                    <textarea
                        name="notes"
                        rows="3"
                        class="rounded-md border bg-background p-3"
                    />
                </label>
                <div class="flex justify-end">
                    <Button type="submit" :disabled="processing">
                        <Send />
                        Request payout
                    </Button>
                </div>
            </Form>

            <p
                v-else
                class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                Add a verified payout account before requesting a payout.
            </p>
        </section>

        <section class="space-y-4">
            <Heading variant="small" title="Recent payouts" />

            <div v-if="payouts.length > 0" class="grid gap-3">
                <div
                    v-for="payout in payouts"
                    :key="payout.id"
                    class="grid gap-3 rounded-lg border p-4 sm:grid-cols-[1fr_auto] sm:items-center"
                >
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-medium">
                                {{ payout.currencyCode }}
                                {{ payout.amountDisplay }}
                            </h2>
                            <Badge variant="outline">{{ payout.status }}</Badge>
                        </div>
                        <p
                            v-if="payout.providerStatus"
                            class="text-sm text-muted-foreground"
                        >
                            Provider: {{ payout.providerStatus }}
                        </p>
                        <p
                            v-if="payout.trackingReference"
                            class="text-xs break-all text-muted-foreground"
                        >
                            {{ payout.trackingReference }}
                        </p>
                        <p
                            v-if="payout.failureReason"
                            class="text-sm text-muted-foreground"
                        >
                            {{ payout.failureReason }}
                        </p>
                    </div>
                    <div class="space-y-1 text-sm text-muted-foreground">
                        <div>
                            Requested {{ payout.requestedAt ?? 'Pending' }}
                        </div>
                        <div v-if="payout.processingAt">
                            Processing {{ payout.processingAt }}
                        </div>
                        <div v-if="payout.paidAt">Paid {{ payout.paidAt }}</div>
                        <div v-if="payout.nextRetryAt">
                            Retry {{ payout.nextRetryAt }}
                        </div>
                    </div>
                </div>
            </div>

            <p
                v-else
                class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                No payout requests yet.
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
