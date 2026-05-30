<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { CheckCircle2, CreditCard, ShieldCheck } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard as artisanDashboard } from '@/routes/artisan';
import { store } from '@/routes/artisan/subscription';
import type {
    ArtisanPaymentItem,
    ArtisanSubscriptionDetail,
    SubscriptionPlanOption,
    Team,
} from '@/types';

type Props = {
    currentTeam: Team;
    profile: {
        id: number;
        businessName: string;
        subscriptionStatus: string;
        verificationStatus: string;
    };
    plans: SubscriptionPlanOption[];
    currentSubscription: ArtisanSubscriptionDetail | null;
    recentPayments: ArtisanPaymentItem[];
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            {
                title: 'Artisan',
                href: artisanDashboard(props.currentTeam.slug).url,
            },
            {
                title: 'Subscription',
                href: '#',
            },
        ],
    }),
});
</script>

<template>
    <Head title="Artisan subscription" />

    <h1 class="sr-only">Artisan subscription</h1>

    <div class="flex flex-col gap-8 p-4 sm:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                variant="small"
                title="Subscription"
                :description="profile.businessName"
            />
            <div class="flex flex-wrap gap-2">
                <Badge variant="secondary" class="w-fit gap-1.5">
                    <CreditCard class="size-3.5" />
                    {{ profile.subscriptionStatus }}
                </Badge>
                <Badge variant="outline" class="w-fit gap-1.5">
                    <ShieldCheck class="size-3.5" />
                    {{ profile.verificationStatus }}
                </Badge>
            </div>
        </div>

        <section class="space-y-4">
            <Heading variant="small" title="Current plan" />

            <div
                v-if="currentSubscription"
                class="grid gap-4 rounded-lg border p-5 sm:grid-cols-[1fr_auto] sm:items-center"
            >
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="font-medium">
                            {{ currentSubscription.plan.name }}
                        </h2>
                        <Badge variant="secondary">{{
                            currentSubscription.status
                        }}</Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ currentSubscription.startsAt || 'Pending' }} -
                        {{ currentSubscription.endsAt || 'Open' }}
                    </p>
                    <p
                        v-if="currentSubscription.paymentReference"
                        class="text-sm text-muted-foreground"
                    >
                        {{ currentSubscription.paymentReference }}
                    </p>
                </div>
                <div class="text-sm font-medium">
                    {{ currentSubscription.plan.currencyCode }}
                    {{ currentSubscription.plan.price }}
                </div>
            </div>

            <p
                v-else
                class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                No active subscription.
            </p>
        </section>

        <section class="space-y-4">
            <Heading variant="small" title="Plans" />

            <div class="grid gap-4 lg:grid-cols-3">
                <div
                    v-for="plan in plans"
                    :key="plan.id"
                    class="flex min-h-72 flex-col rounded-lg border p-5"
                >
                    <div class="space-y-2">
                        <h2 class="font-medium">{{ plan.name }}</h2>
                        <p
                            v-if="plan.description"
                            class="text-sm text-muted-foreground"
                        >
                            {{ plan.description }}
                        </p>
                    </div>

                    <div class="mt-5">
                        <span class="text-2xl font-semibold">
                            {{ plan.currencyCode }} {{ plan.price }}
                        </span>
                        <span class="ml-2 text-sm text-muted-foreground">
                            {{ plan.interval }}
                        </span>
                    </div>

                    <ul class="mt-5 grid gap-2 text-sm">
                        <li
                            v-for="feature in plan.features"
                            :key="feature"
                            class="flex items-center gap-2"
                        >
                            <CheckCircle2 class="size-4 text-primary" />
                            <span>{{ feature }}</span>
                        </li>
                    </ul>

                    <Form
                        v-bind="store.form(props.currentTeam.slug)"
                        class="mt-auto pt-5"
                        v-slot="{ errors, processing }"
                    >
                        <input
                            type="hidden"
                            name="subscription_plan_id"
                            :value="plan.id"
                        />
                        <InputError :message="errors.subscription_plan_id" />
                        <Button
                            type="submit"
                            class="mt-3 w-full"
                            :disabled="processing"
                        >
                            <CreditCard />
                            Pay with Paystack
                        </Button>
                    </Form>
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <Heading variant="small" title="Payments" />

            <div v-if="recentPayments.length > 0" class="grid gap-3">
                <div
                    v-for="payment in recentPayments"
                    :key="payment.id"
                    class="grid gap-3 rounded-lg border p-4 sm:grid-cols-[1fr_auto] sm:items-center"
                >
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-medium">
                                {{ payment.planName || 'Subscription' }}
                            </h2>
                            <Badge variant="outline">{{
                                payment.status
                            }}</Badge>
                        </div>
                        <p class="text-sm break-all text-muted-foreground">
                            {{ payment.reference }}
                        </p>
                        <p
                            v-if="payment.failureReason"
                            class="text-sm text-destructive"
                        >
                            {{ payment.failureReason }}
                        </p>
                    </div>
                    <div class="text-sm font-medium">
                        {{ payment.currencyCode }} {{ payment.amountDisplay }}
                    </div>
                </div>
            </div>

            <p
                v-else
                class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                No payments yet.
            </p>
        </section>
    </div>
</template>
