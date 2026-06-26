<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCircle2,
    Circle,
    ClipboardCheck,
    CreditCard,
    Star,
    Store,
    UserPlus,
} from 'lucide-vue-next';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { confirm as confirmTracker } from '@/routes/booking-tracker';
import { store as upgradeAccount } from '@/routes/booking-tracker/account';
import { store as storeGuestDispute } from '@/routes/booking-tracker/disputes';
import { store as payTracker } from '@/routes/booking-tracker/payments';
import { store as storeGuestReview } from '@/routes/booking-tracker/reviews';
import { index as marketplaceIndex } from '@/routes/marketplace';
import type { BookingDetail } from '@/types';

const props = defineProps<{
    booking: BookingDetail;
    token: string;
}>();

const statuses = [
    'requested',
    'accepted',
    'paid',
    'escrowed',
    'in_progress',
    'finished',
    'confirmed',
    'settled',
    'reviewed',
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

                <ol class="grid gap-3 sm:grid-cols-3 lg:grid-cols-5">
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
                    </p>
                </div>

                <Form
                    v-if="booking.canPay"
                    v-bind="payTracker.form(booking.trackerCode)"
                    class="flex justify-end"
                >
                    <input type="hidden" name="token" :value="token" />
                    <Button type="submit">
                        <CreditCard />
                        Pay securely
                    </Button>
                </Form>

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

            <section
                v-if="booking.canUpgrade"
                class="grid gap-4 rounded-lg border p-5"
            >
                <div class="flex items-center gap-2">
                    <UserPlus class="size-4 text-primary" />
                    <h2 class="font-medium">Create your customer account</h2>
                </div>

                <Form
                    v-bind="upgradeAccount.form(booking.trackerCode)"
                    class="grid gap-4 md:grid-cols-2"
                    #default="{ errors, processing }"
                >
                    <input type="hidden" name="token" :value="token" />
                    <div class="grid gap-2">
                        <Label for="upgrade_name">Name</Label>
                        <Input
                            id="upgrade_name"
                            name="name"
                            :default-value="booking.customerName"
                            required
                        />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="upgrade_email">Email</Label>
                        <Input
                            id="upgrade_email"
                            name="email"
                            type="email"
                            :default-value="booking.customerEmail ?? ''"
                            required
                        />
                        <InputError :message="errors.email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="upgrade_password">Password</Label>
                        <Input
                            id="upgrade_password"
                            name="password"
                            type="password"
                            autocomplete="new-password"
                            required
                        />
                        <InputError :message="errors.password" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="upgrade_password_confirmation">
                            Confirm password
                        </Label>
                        <Input
                            id="upgrade_password_confirmation"
                            name="password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            required
                        />
                    </div>
                    <div class="flex justify-end md:col-span-2">
                        <Button type="submit" :disabled="processing">
                            <UserPlus />
                            Create account
                        </Button>
                    </div>
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
                        v-if="booking.review.comment"
                        class="text-muted-foreground"
                    >
                        {{ booking.review.comment }}
                    </p>
                </div>

                <Form
                    v-else
                    v-bind="storeGuestReview.form(booking.trackerCode)"
                    class="grid gap-4"
                    #default="{ errors, processing }"
                >
                    <input type="hidden" name="token" :value="token" />
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
                        <InputError :message="errors.rating" />
                    </label>
                    <label class="grid gap-2 text-sm">
                        Comment
                        <textarea
                            name="comment"
                            rows="4"
                            class="rounded-md border bg-background p-3"
                        />
                        <InputError :message="errors.comment" />
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
                <div class="flex items-center gap-2">
                    <AlertTriangle class="size-4 text-amber-500" />
                    <h2 class="font-medium">Disputes</h2>
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

                <Form
                    v-if="booking.canDispute"
                    v-bind="storeGuestDispute.form(booking.trackerCode)"
                    class="grid gap-4"
                    #default="{ errors, processing }"
                >
                    <input type="hidden" name="token" :value="token" />
                    <input
                        v-if="booking.review"
                        type="hidden"
                        name="review_id"
                        :value="booking.review.id"
                    />
                    <div class="grid gap-2">
                        <Label for="dispute_subject">Subject</Label>
                        <Input
                            id="dispute_subject"
                            name="subject"
                            maxlength="160"
                            required
                        />
                        <InputError :message="errors.subject" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="dispute_severity">Severity</Label>
                        <select
                            id="dispute_severity"
                            name="severity"
                            class="h-10 rounded-md border bg-background px-3 text-sm"
                            required
                        >
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                        <InputError :message="errors.severity" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="dispute_description">Description</Label>
                        <textarea
                            id="dispute_description"
                            name="description"
                            rows="4"
                            class="rounded-md border bg-background p-3 text-sm"
                        />
                        <InputError :message="errors.description" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="dispute_evidence">Evidence</Label>
                        <Input
                            id="dispute_evidence"
                            name="evidence[]"
                            type="file"
                            multiple
                        />
                        <InputError :message="errors.evidence" />
                    </div>
                    <div class="flex justify-end">
                        <Button type="submit" :disabled="processing">
                            <AlertTriangle />
                            Open dispute
                        </Button>
                    </div>
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
