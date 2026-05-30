<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CalendarPlus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { show as showArtisan } from '@/routes/marketplace/artisans';
import { store as storeBooking } from '@/routes/marketplace/bookings';
import type { MarketplaceArtisanDetail, MarketplaceStateOption } from '@/types';

const props = defineProps<{
    artisan: MarketplaceArtisanDetail;
    states: MarketplaceStateOption[];
}>();

const selectedStateId = ref('');
const selectedLocalGovernmentId = ref('');
const selectedServiceId = ref(
    props.artisan.services.length > 0
        ? String(props.artisan.services[0].id)
        : '',
);

const localGovernments = computed(() => {
    return (
        props.states.find((state) => String(state.id) === selectedStateId.value)
            ?.localGovernments ?? []
    );
});

const territories = computed(() => {
    return (
        localGovernments.value.find(
            (localGovernment) =>
                String(localGovernment.id) === selectedLocalGovernmentId.value,
        )?.territories ?? []
    );
});
</script>

<template>
    <Head :title="`Book ${artisan.businessName}`" />

    <main class="min-h-screen bg-background text-foreground">
        <div class="mx-auto grid w-full max-w-5xl gap-8 px-4 py-6 sm:px-6">
            <nav class="flex items-center justify-between gap-4">
                <Button as-child variant="ghost" size="sm">
                    <Link :href="showArtisan(artisan.id).url">
                        <ArrowLeft />
                        Artisan profile
                    </Link>
                </Button>
                <Badge variant="secondary">{{
                    artisan.availabilityStatus
                }}</Badge>
            </nav>

            <section class="space-y-2">
                <h1 class="text-2xl font-semibold">
                    Book {{ artisan.businessName }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    Send a request with service, address, schedule, and optional
                    photos or PDFs. You will receive a secure tracker link after
                    submission.
                </p>
            </section>

            <Form
                v-bind="storeBooking.form(artisan.id)"
                class="grid gap-6 rounded-lg border p-5"
                v-slot="{ errors, processing }"
            >
                <section class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2 md:col-span-2">
                        <Label for="artisan_service_id">Service</Label>
                        <select
                            id="artisan_service_id"
                            v-model="selectedServiceId"
                            name="artisan_service_id"
                            required
                            class="h-9 rounded-md border bg-transparent px-3 text-sm"
                        >
                            <option
                                v-for="service in artisan.services"
                                :key="service.id"
                                :value="service.id"
                            >
                                {{ service.title }} -
                                {{ service.category.name }}
                            </option>
                        </select>
                        <InputError :message="errors.artisan_service_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="customer_name">Name</Label>
                        <Input
                            id="customer_name"
                            name="customer_name"
                            required
                        />
                        <InputError :message="errors.customer_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="customer_phone">Phone</Label>
                        <Input
                            id="customer_phone"
                            name="customer_phone"
                            required
                        />
                        <InputError :message="errors.customer_phone" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="customer_email">Email</Label>
                        <Input
                            id="customer_email"
                            name="customer_email"
                            type="email"
                        />
                        <InputError :message="errors.customer_email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="scheduled_at">Preferred date</Label>
                        <Input
                            id="scheduled_at"
                            name="scheduled_at"
                            type="date"
                        />
                        <InputError :message="errors.scheduled_at" />
                    </div>
                </section>

                <section class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2 md:col-span-2">
                        <Label for="line_1">Address</Label>
                        <Input id="line_1" name="line_1" required />
                        <InputError :message="errors.line_1" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="line_2">Address line 2</Label>
                        <Input id="line_2" name="line_2" />
                        <InputError :message="errors.line_2" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="landmark">Landmark</Label>
                        <Input id="landmark" name="landmark" />
                        <InputError :message="errors.landmark" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="state_id">State</Label>
                        <select
                            id="state_id"
                            v-model="selectedStateId"
                            name="state_id"
                            class="h-9 rounded-md border bg-transparent px-3 text-sm"
                        >
                            <option value="">Select state</option>
                            <option
                                v-for="state in states"
                                :key="state.id"
                                :value="state.id"
                            >
                                {{ state.name }}
                            </option>
                        </select>
                        <InputError :message="errors.state_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="local_government_id">LGA</Label>
                        <select
                            id="local_government_id"
                            v-model="selectedLocalGovernmentId"
                            name="local_government_id"
                            class="h-9 rounded-md border bg-transparent px-3 text-sm"
                        >
                            <option value="">Select LGA</option>
                            <option
                                v-for="localGovernment in localGovernments"
                                :key="localGovernment.id"
                                :value="localGovernment.id"
                            >
                                {{ localGovernment.name }}
                            </option>
                        </select>
                        <InputError :message="errors.local_government_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="territory_id">Territory</Label>
                        <select
                            id="territory_id"
                            name="territory_id"
                            class="h-9 rounded-md border bg-transparent px-3 text-sm"
                        >
                            <option value="">Select territory</option>
                            <option
                                v-for="territory in territories"
                                :key="territory.id"
                                :value="territory.id"
                            >
                                {{ territory.name }}
                            </option>
                        </select>
                        <InputError :message="errors.territory_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="attachments">Attachments</Label>
                        <Input
                            id="attachments"
                            name="attachments[]"
                            type="file"
                            multiple
                        />
                        <InputError :message="errors.attachments" />
                    </div>
                </section>

                <section class="grid gap-2">
                    <Label for="description">Notes</Label>
                    <textarea
                        id="description"
                        name="description"
                        rows="4"
                        class="min-h-24 rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError :message="errors.description" />
                </section>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="processing">
                        <CalendarPlus />
                        Send booking request
                    </Button>
                </div>
            </Form>
        </div>
    </main>
</template>
