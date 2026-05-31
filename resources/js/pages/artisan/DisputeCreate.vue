<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { dashboard as artisanDashboard } from '@/routes/artisan';
import { index as artisanBookings } from '@/routes/artisan/bookings';
import { store as storeDispute } from '@/routes/artisan/bookings/disputes';
import type { Team } from '@/types';

type Booking = {
    id: number;
    trackerCode: string;
    status: string;
    customerName: string;
};

defineProps<{
    currentTeam: Team;
    booking: Booking;
}>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            {
                title: 'Artisan',
                href: artisanDashboard(props.currentTeam.slug).url,
            },
            {
                title: 'Bookings',
                href: artisanBookings(props.currentTeam.slug).url,
            },
            {
                title: 'Dispute',
                href: '#',
            },
        ],
    }),
});
</script>

<template>
    <Head :title="`Open dispute ${booking.trackerCode}`" />

    <div class="flex flex-col gap-8 p-4 sm:p-6">
        <Button as-child variant="ghost" size="sm" class="w-fit">
            <Link :href="artisanBookings(currentTeam.slug).url">
                <ArrowLeft />
                Bookings
            </Link>
        </Button>

        <section class="grid gap-5 rounded-lg border p-5">
            <div class="space-y-2">
                <div class="flex items-center gap-2">
                    <AlertTriangle class="size-5 text-amber-500" />
                    <h1 class="text-2xl font-semibold">Open dispute</h1>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ booking.customerName }} - {{ booking.trackerCode }}
                </p>
            </div>

            <Form
                v-bind="
                    storeDispute.form({
                        current_team: currentTeam.slug,
                        booking: booking.id,
                    })
                "
                class="grid gap-4"
                enctype="multipart/form-data"
                #default="{ errors, processing }"
            >
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
</template>
