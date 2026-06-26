<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Save } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { index as customerBookings } from '@/routes/customer/bookings';
import { update as updatePreferences } from '@/routes/customer/preferences';

defineProps<{
    preferences: {
        preferredChannel: string;
        scheduleWindow: string;
        defaultNotes: string | null;
    };
    channels: Array<{ value: string; label: string }>;
    scheduleWindows: Array<{ value: string; label: string }>;
}>();
</script>

<template>
    <Head title="Booking preferences" />

    <main class="min-h-screen bg-background text-foreground">
        <div class="mx-auto grid w-full max-w-3xl gap-8 px-4 py-6 sm:px-6">
            <nav>
                <Button as-child variant="ghost" size="sm">
                    <Link :href="customerBookings().url">
                        <ArrowLeft />
                        My bookings
                    </Link>
                </Button>
            </nav>

            <section class="space-y-2">
                <h1 class="text-2xl font-semibold">Booking preferences</h1>
                <p class="text-sm text-muted-foreground">
                    Set defaults for future marketplace booking requests.
                </p>
            </section>

            <Form
                v-bind="updatePreferences.form()"
                class="grid gap-5 rounded-lg border p-5"
                #default="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="preferred_channel">Preferred updates</Label>
                    <select
                        id="preferred_channel"
                        name="preferred_channel"
                        :value="preferences.preferredChannel"
                        class="h-10 rounded-md border bg-background px-3 text-sm"
                        required
                    >
                        <option
                            v-for="channel in channels"
                            :key="channel.value"
                            :value="channel.value"
                        >
                            {{ channel.label }}
                        </option>
                    </select>
                    <InputError :message="errors.preferred_channel" />
                </div>

                <div class="grid gap-2">
                    <Label for="schedule_window">Preferred time</Label>
                    <select
                        id="schedule_window"
                        name="schedule_window"
                        :value="preferences.scheduleWindow"
                        class="h-10 rounded-md border bg-background px-3 text-sm"
                        required
                    >
                        <option
                            v-for="window in scheduleWindows"
                            :key="window.value"
                            :value="window.value"
                        >
                            {{ window.label }}
                        </option>
                    </select>
                    <InputError :message="errors.schedule_window" />
                </div>

                <div class="grid gap-2">
                    <Label for="default_notes">Default booking notes</Label>
                    <textarea
                        id="default_notes"
                        name="default_notes"
                        rows="5"
                        :value="preferences.defaultNotes ?? ''"
                        class="rounded-md border bg-background p-3 text-sm"
                    />
                    <InputError :message="errors.default_notes" />
                </div>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="processing">
                        <Save />
                        Save preferences
                    </Button>
                </div>
            </Form>
        </div>
    </main>
</template>
