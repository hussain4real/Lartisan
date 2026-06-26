<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { show as customerBooking } from '@/routes/customer/bookings';
import { store as storeDispute } from '@/routes/customer/bookings/disputes';

type Booking = {
    id: number;
    trackerCode: string;
    status: string;
    artisan: {
        businessName: string;
    };
};

defineProps<{
    booking: Booking;
    review: {
        id: number;
        rating: number;
        comment: string | null;
    } | null;
    payment: {
        id: number;
        reference: string;
    } | null;
}>();

const target = ref('booking');
</script>

<template>
    <Head :title="`Open dispute ${booking.trackerCode}`" />

    <main class="min-h-screen bg-background text-foreground">
        <div class="mx-auto grid w-full max-w-3xl gap-8 px-4 py-6 sm:px-6">
            <Button as-child variant="ghost" size="sm" class="w-fit">
                <Link :href="customerBooking(booking.id).url">
                    <ArrowLeft />
                    Booking
                </Link>
            </Button>

            <section class="grid gap-5 rounded-lg border p-5">
                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <AlertTriangle class="size-5 text-amber-500" />
                        <h1 class="text-2xl font-semibold">Open dispute</h1>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ booking.artisan.businessName }} -
                        {{ booking.trackerCode }}
                    </p>
                </div>

                <Form
                    v-bind="storeDispute.form(booking.id)"
                    class="grid gap-4"
                    enctype="multipart/form-data"
                    #default="{ errors, processing }"
                >
                    <input
                        v-if="review && target === 'review'"
                        type="hidden"
                        name="review_id"
                        :value="review.id"
                    />
                    <input
                        v-if="payment && target === 'payment'"
                        type="hidden"
                        name="payment_id"
                        :value="payment.id"
                    />
                    <label class="grid gap-2 text-sm">
                        Target
                        <select
                            v-model="target"
                            name="target"
                            class="h-10 rounded-md border bg-background px-3"
                            required
                        >
                            <option value="booking">Booking</option>
                            <option value="profile">Artisan profile</option>
                            <option v-if="payment" value="payment">
                                Payment {{ payment.reference }}
                            </option>
                            <option v-if="review" value="review">Review</option>
                        </select>
                        <span v-if="errors.target" class="text-destructive">
                            {{ errors.target }}
                        </span>
                    </label>
                    <label class="grid gap-2 text-sm">
                        Subject
                        <input
                            name="subject"
                            class="h-10 rounded-md border bg-background px-3"
                            required
                        />
                        <span v-if="errors.subject" class="text-destructive">
                            {{ errors.subject }}
                        </span>
                    </label>
                    <label class="grid gap-2 text-sm">
                        Severity
                        <select
                            name="severity"
                            class="h-10 rounded-md border bg-background px-3"
                            required
                        >
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </label>
                    <label class="grid gap-2 text-sm">
                        Details
                        <textarea
                            name="description"
                            rows="5"
                            class="rounded-md border bg-background p-3"
                        />
                    </label>
                    <label class="grid gap-2 text-sm">
                        Evidence
                        <input
                            name="evidence[]"
                            type="file"
                            multiple
                            class="rounded-md border bg-background px-3 py-2"
                        />
                    </label>
                    <div class="flex justify-end">
                        <Button type="submit" :disabled="processing">
                            Open dispute
                        </Button>
                    </div>
                </Form>
            </section>
        </div>
    </main>
</template>
