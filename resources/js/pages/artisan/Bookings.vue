<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CalendarCheck,
    Check,
    CirclePlay,
    Flag,
    MessageSquare,
    X,
} from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard as artisanDashboard } from '@/routes/artisan';
import { accept, finish, reject, start } from '@/routes/artisan/bookings';
import { show as showChat } from '@/routes/artisan/bookings/chat';
import { create as createDispute } from '@/routes/artisan/bookings/disputes';
import { store as storeReviewResponse } from '@/routes/artisan/reviews/response';
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
                        <p
                            v-if="booking.payment"
                            class="text-sm text-muted-foreground"
                        >
                            Payment {{ booking.payment.status }}
                            <template v-if="booking.payment.netAmountDisplay">
                                · net {{ booking.currencyCode }}
                                {{ booking.payment.netAmountDisplay }}
                            </template>
                        </p>
                        <div
                            v-if="booking.review"
                            class="mt-3 grid gap-3 rounded-md border bg-muted/30 p-3 text-sm"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium">
                                    Review {{ booking.review.rating }} / 5
                                </span>
                                <Badge variant="outline">
                                    {{ booking.review.status }}
                                </Badge>
                            </div>
                            <p
                                v-if="booking.review.comment"
                                class="text-muted-foreground"
                            >
                                {{ booking.review.comment }}
                            </p>
                            <div
                                v-if="booking.review.artisanResponse"
                                class="rounded-md border bg-background p-3"
                            >
                                <p
                                    class="text-xs text-muted-foreground uppercase"
                                >
                                    Response
                                </p>
                                <p>{{ booking.review.artisanResponse }}</p>
                            </div>
                            <Form
                                v-else-if="booking.review.status !== 'hidden'"
                                v-bind="
                                    storeReviewResponse.form({
                                        current_team: props.currentTeam.slug,
                                        review: booking.review.id,
                                    })
                                "
                                class="grid gap-2"
                                #default="{ errors, processing }"
                            >
                                <textarea
                                    name="response"
                                    rows="3"
                                    maxlength="1200"
                                    class="rounded-md border bg-background p-3"
                                />
                                <p
                                    v-if="errors.response"
                                    class="text-sm text-destructive"
                                >
                                    {{ errors.response }}
                                </p>
                                <div class="flex justify-end">
                                    <Button
                                        type="submit"
                                        size="sm"
                                        :disabled="processing"
                                    >
                                        Respond
                                    </Button>
                                </div>
                            </Form>
                        </div>
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
                            v-if="booking.status === 'escrowed'"
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
                        <Button
                            v-if="booking.canChat"
                            as-child
                            variant="outline"
                            size="sm"
                        >
                            <Link
                                :href="
                                    showChat({
                                        current_team: props.currentTeam.slug,
                                        booking: booking.id,
                                    }).url
                                "
                            >
                                <MessageSquare />
                                Chat
                            </Link>
                        </Button>
                        <Button as-child variant="outline" size="sm">
                            <Link
                                :href="
                                    createDispute({
                                        current_team: props.currentTeam.slug,
                                        booking: booking.id,
                                    }).url
                                "
                            >
                                <AlertTriangle />
                                Dispute
                            </Link>
                        </Button>
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
