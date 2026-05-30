<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ClipboardCheck } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { confirm, index as customerBookings } from '@/routes/customer/bookings';
import type { BookingDetail } from '@/types';

defineProps<{
    booking: BookingDetail;
}>();
</script>

<template>
    <Head :title="`Booking ${booking.trackerCode}`" />

    <main class="min-h-screen bg-background text-foreground">
        <div class="mx-auto grid w-full max-w-4xl gap-8 px-4 py-6 sm:px-6">
            <nav class="flex items-center justify-between gap-4">
                <Button as-child variant="ghost" size="sm">
                    <Link :href="customerBookings().url">
                        <ArrowLeft />
                        My bookings
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

                <dl class="grid gap-3 sm:grid-cols-2">
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
                </dl>

                <Form
                    v-if="booking.status === 'finished'"
                    v-bind="confirm.form(booking.id)"
                    class="flex justify-end"
                >
                    <Button type="submit">
                        <ClipboardCheck />
                        Confirm completion
                    </Button>
                </Form>
            </section>
        </div>
    </main>
</template>
