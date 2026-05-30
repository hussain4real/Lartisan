<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { CheckCircle2, Circle, ClipboardCheck, Store } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { confirm as confirmTracker } from '@/routes/booking-tracker';
import { index as marketplaceIndex } from '@/routes/marketplace';
import type { BookingDetail } from '@/types';

const props = defineProps<{
    booking: BookingDetail;
    token: string;
}>();

const statuses = [
    'requested',
    'accepted',
    'in_progress',
    'finished',
    'confirmed',
];

const currentIndex = computed(() => statuses.indexOf(props.booking.status));
</script>

<template>
    <Head :title="`Booking ${booking.trackerCode}`" />

    <main class="min-h-screen bg-background text-foreground">
        <div class="mx-auto grid w-full max-w-4xl gap-8 px-4 py-6 sm:px-6">
            <nav class="flex items-center justify-between gap-4">
                <Button as-child variant="ghost" size="sm">
                    <Link :href="marketplaceIndex().url">
                        <Store />
                        Marketplace
                    </Link>
                </Button>
                <Badge variant="secondary">{{ booking.status }}</Badge>
            </nav>

            <section class="grid gap-5 rounded-lg border p-5">
                <div class="space-y-2">
                    <p class="text-sm text-muted-foreground">
                        {{ booking.trackerCode }}
                    </p>
                    <h1 class="text-2xl font-semibold">
                        {{ booking.artisan.businessName }}
                    </h1>
                    <p v-if="booking.service" class="text-sm">
                        {{ booking.service.title }} -
                        {{ booking.service.category }}
                    </p>
                </div>

                <ol class="grid gap-3 sm:grid-cols-5">
                    <li
                        v-for="(status, index) in statuses"
                        :key="status"
                        class="flex items-center gap-2 rounded-md border p-3 text-sm"
                    >
                        <CheckCircle2
                            v-if="index <= currentIndex"
                            class="size-4 text-primary"
                        />
                        <Circle v-else class="size-4 text-muted-foreground" />
                        {{ status.replace('_', ' ') }}
                    </li>
                </ol>

                <dl class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm text-muted-foreground">Customer</dt>
                        <dd class="font-medium">{{ booking.customerName }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted-foreground">Schedule</dt>
                        <dd class="font-medium">
                            {{ booking.scheduledAt ?? 'Flexible' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted-foreground">Quote</dt>
                        <dd class="font-medium">
                            <template v-if="booking.quotedAmountDisplay">
                                {{ booking.currencyCode }}
                                {{ booking.quotedAmountDisplay }}
                            </template>
                            <template v-else>Pending</template>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted-foreground">Address</dt>
                        <dd class="font-medium">
                            {{ booking.address?.line_1 ?? 'Address captured' }}
                        </dd>
                    </div>
                </dl>

                <Form
                    v-if="booking.status === 'finished'"
                    v-bind="confirmTracker.form(booking.trackerCode)"
                    class="flex justify-end"
                >
                    <input type="hidden" name="token" :value="token" />
                    <Button type="submit">
                        <ClipboardCheck />
                        Confirm completion
                    </Button>
                </Form>
            </section>

            <section class="grid gap-3">
                <h2 class="text-lg font-semibold">Status history</h2>
                <div class="grid gap-3">
                    <article
                        v-for="history in booking.histories ?? []"
                        :key="history.id"
                        class="rounded-lg border p-4"
                    >
                        <div class="flex flex-wrap justify-between gap-2">
                            <p class="font-medium">
                                {{ history.toStatus.replace('_', ' ') }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ history.createdAt }}
                            </p>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ history.notes }}
                        </p>
                    </article>
                </div>
            </section>
        </div>
    </main>
</template>
