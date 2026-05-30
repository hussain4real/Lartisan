<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { CalendarCheck, Check, CirclePlay, Flag, X } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard as artisanDashboard } from '@/routes/artisan';
import { accept, finish, reject, start } from '@/routes/artisan/bookings';
import type { ArtisanBookingItem, Team } from '@/types';

type Props = {
    currentTeam: Team;
    bookings: ArtisanBookingItem[];
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
                title: 'Bookings',
                href: '#',
            },
        ],
    }),
});
</script>

<template>
    <Head title="Artisan bookings" />

    <h1 class="sr-only">Artisan bookings</h1>

    <div class="flex flex-col gap-8 p-4 sm:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                variant="small"
                title="Bookings"
                description="Customer requests and active jobs"
            />
            <Badge variant="secondary" class="w-fit gap-1.5">
                <CalendarCheck class="size-3.5" />
                {{ bookings.length }}
            </Badge>
        </div>

        <section class="grid gap-4">
            <article
                v-for="booking in bookings"
                :key="booking.id"
                class="grid gap-4 rounded-lg border p-4"
            >
                <div class="grid gap-3 lg:grid-cols-[1fr_auto] lg:items-start">
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-medium">
                                {{ booking.customerName }}
                            </h2>
                            <Badge variant="outline">{{
                                booking.status
                            }}</Badge>
                        </div>
                        <p v-if="booking.service" class="text-sm">
                            {{ booking.service.title }} -
                            {{ booking.service.category }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ booking.customerPhone }}
                            <span v-if="booking.customerEmail">
                                - {{ booking.customerEmail }}
                            </span>
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ booking.address.line_1 ?? 'Address captured' }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Form
                            v-if="booking.status === 'requested'"
                            v-bind="
                                accept.form({
                                    current_team: props.currentTeam.slug,
                                    booking: booking.id,
                                })
                            "
                        >
                            <Button type="submit" size="sm">
                                <Check />
                                Accept
                            </Button>
                        </Form>
                        <Form
                            v-if="booking.status === 'requested'"
                            v-bind="
                                reject.form({
                                    current_team: props.currentTeam.slug,
                                    booking: booking.id,
                                })
                            "
                        >
                            <Button type="submit" variant="outline" size="sm">
                                <X />
                                Reject
                            </Button>
                        </Form>
                        <Form
                            v-if="booking.status === 'accepted'"
                            v-bind="
                                start.form({
                                    current_team: props.currentTeam.slug,
                                    booking: booking.id,
                                })
                            "
                        >
                            <Button type="submit" size="sm">
                                <CirclePlay />
                                Start
                            </Button>
                        </Form>
                        <Form
                            v-if="booking.status === 'in_progress'"
                            v-bind="
                                finish.form({
                                    current_team: props.currentTeam.slug,
                                    booking: booking.id,
                                })
                            "
                        >
                            <Button type="submit" size="sm">
                                <Flag />
                                Finish
                            </Button>
                        </Form>
                    </div>
                </div>
            </article>

            <p
                v-if="bookings.length === 0"
                class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                No bookings yet.
            </p>
        </section>
    </div>
</template>
