<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarCheck, Store } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { show as showBooking } from '@/routes/customer/bookings';
import { index as marketplaceIndex } from '@/routes/marketplace';
import type { BookingDetail } from '@/types';

defineProps<{
    bookings: BookingDetail[];
}>();
</script>

<template>
    <Head title="My bookings" />

    <main class="min-h-screen bg-background text-foreground">
        <div class="mx-auto grid w-full max-w-5xl gap-8 px-4 py-6 sm:px-6">
            <nav class="flex items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold">My bookings</h1>
                <Button as-child variant="outline" size="sm">
                    <Link :href="marketplaceIndex().url">
                        <Store />
                        Marketplace
                    </Link>
                </Button>
            </nav>

            <section class="grid gap-4">
                <article
                    v-for="booking in bookings"
                    :key="booking.id"
                    class="grid gap-4 rounded-lg border p-4 sm:grid-cols-[1fr_auto] sm:items-center"
                >
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-medium">
                                {{ booking.artisan.businessName }}
                            </h2>
                            <Badge variant="secondary">{{
                                booking.status
                            }}</Badge>
                        </div>
                        <p v-if="booking.service" class="text-sm">
                            {{ booking.service.title }} -
                            {{ booking.service.category }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ booking.scheduledAt ?? 'Flexible schedule' }}
                        </p>
                    </div>
                    <Button as-child size="sm">
                        <Link :href="showBooking(booking.id).url">
                            <CalendarCheck />
                            Open
                        </Link>
                    </Button>
                </article>

                <p
                    v-if="bookings.length === 0"
                    class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                >
                    No bookings yet.
                </p>
            </section>
        </div>
    </main>
</template>
