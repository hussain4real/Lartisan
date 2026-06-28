<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    ClipboardCheck,
    CreditCard,
    MessageSquare,
    Star,
} from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { confirm, index as customerBookings } from '@/routes/customer/bookings';
import { show as showChat } from '@/routes/customer/bookings/chat';
import { create as createDispute } from '@/routes/customer/bookings/disputes';
import { store as payForBooking } from '@/routes/customer/bookings/payments';
import { store as storeReview } from '@/routes/customer/bookings/reviews';
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

                <div
                    v-if="booking.payment"
                    class="grid gap-2 rounded-md border bg-muted/30 p-3 text-sm"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">Payment</span>
                        <Badge variant="outline">
                            {{ booking.payment.status }}
                        </Badge>
                    </div>
                    <p class="text-muted-foreground">
                        {{ booking.currencyCode }}
                        {{ booking.payment.amountDisplay }}
                        <template v-if="booking.payment.netAmountDisplay">
                            · net settlement {{ booking.currencyCode }}
                            {{ booking.payment.netAmountDisplay }}
                        </template>
                    </p>
                </div>

                <Form
                    v-if="booking.canPay"
                    v-bind="payForBooking.form(booking.id)"
                    class="flex justify-end"
                >
                    <Button type="submit">
                        <CreditCard />
                        Pay securely
                    </Button>
                </Form>

                <div v-if="booking.canChat" class="flex justify-end">
                    <Button as-child variant="outline">
                        <Link :href="showChat(booking.id).url">
                            <MessageSquare />
                            Open chat
                        </Link>
                    </Button>
                </div>

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

            <section
                v-if="booking.canReview || booking.review"
                class="grid gap-4 rounded-lg border p-5"
            >
                <div class="flex items-center gap-2">
                    <Star class="size-4 text-amber-500" />
                    <h2 class="font-medium">Review</h2>
                    <Badge v-if="booking.review" variant="outline">
                        {{ booking.review.status }}
                    </Badge>
                </div>

                <div v-if="booking.review" class="space-y-2 text-sm">
                    <p class="font-medium">{{ booking.review.rating }} / 5</p>
                    <p
                        v-if="booking.review.proofCount"
                        class="text-muted-foreground"
                    >
                        {{ booking.review.proofCount }} private proof file{{
                            booking.review.proofCount === 1 ? '' : 's'
                        }}
                        attached
                    </p>
                    <p
                        v-if="booking.review.comment"
                        class="text-muted-foreground"
                    >
                        {{ booking.review.comment }}
                    </p>
                    <div
                        v-if="booking.review.artisanResponse"
                        class="rounded-md border bg-muted/30 p-3"
                    >
                        <p class="text-xs text-muted-foreground uppercase">
                            Artisan response
                        </p>
                        <p>{{ booking.review.artisanResponse }}</p>
                    </div>
                </div>

                <Form
                    v-else
                    v-bind="storeReview.form(booking.id)"
                    class="grid gap-4"
                    enctype="multipart/form-data"
                    #default="{ errors, processing }"
                >
                    <label class="grid gap-2 text-sm">
                        Rating
                        <select
                            name="rating"
                            class="h-10 rounded-md border bg-background px-3"
                            required
                        >
                            <option value="5">5 - Excellent</option>
                            <option value="4">4 - Good</option>
                            <option value="3">3 - Okay</option>
                            <option value="2">2 - Poor</option>
                            <option value="1">1 - Bad</option>
                        </select>
                        <span v-if="errors.rating" class="text-destructive">
                            {{ errors.rating }}
                        </span>
                    </label>
                    <label class="grid gap-2 text-sm">
                        Comment
                        <textarea
                            name="comment"
                            rows="4"
                            class="rounded-md border bg-background p-3"
                        />
                        <span v-if="errors.comment" class="text-destructive">
                            {{ errors.comment }}
                        </span>
                    </label>
                    <label class="grid gap-2 text-sm">
                        Proof of work
                        <input
                            name="proof[]"
                            type="file"
                            multiple
                            class="rounded-md border bg-background px-3 py-2"
                        />
                        <span v-if="errors.proof" class="text-destructive">
                            {{ errors.proof }}
                        </span>
                    </label>
                    <div class="flex justify-end">
                        <Button type="submit" :disabled="processing">
                            <Star />
                            Submit review
                        </Button>
                    </div>
                </Form>
            </section>

            <section class="grid gap-4 rounded-lg border p-5">
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-center gap-2">
                        <AlertTriangle class="size-4 text-amber-500" />
                        <h2 class="font-medium">Disputes</h2>
                    </div>
                    <Button as-child variant="outline" size="sm">
                        <Link :href="createDispute(booking.id).url">
                            Open dispute
                        </Link>
                    </Button>
                </div>

                <div
                    v-if="booking.disputes && booking.disputes.length > 0"
                    class="grid gap-3"
                >
                    <div
                        v-for="dispute in booking.disputes"
                        :key="dispute.id"
                        class="rounded-md border p-3"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium">{{ dispute.subject }}</p>
                            <Badge variant="secondary">{{
                                dispute.status
                            }}</Badge>
                            <Badge variant="outline">{{
                                dispute.severity
                            }}</Badge>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    No disputes opened for this booking.
                </p>
            </section>
        </div>
    </main>
</template>
