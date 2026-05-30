<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ImagePlus, Save } from 'lucide-vue-next';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard as artisanDashboard } from '@/routes/artisan';
import { update } from '@/routes/artisan/profile';
import { store as storePortfolio } from '@/routes/artisan/profile/portfolio';
import type { ArtisanProfileDetail, Team } from '@/types';

type Props = {
    currentTeam: Team;
    profile: ArtisanProfileDetail;
    availabilityStatuses: string[];
};

const props = defineProps<Props>();
const availabilityStatus = ref(props.profile.availabilityStatus);

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            {
                title: 'Artisan',
                href: artisanDashboard(props.currentTeam.slug).url,
            },
            {
                title: 'Profile',
                href: '#',
            },
        ],
    }),
});
</script>

<template>
    <Head title="Artisan profile" />

    <h1 class="sr-only">Artisan profile</h1>

    <div class="flex flex-col gap-8">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                variant="small"
                title="Public profile"
                :description="profile.businessName"
            />
            <div class="flex flex-wrap gap-2">
                <Badge variant="secondary">{{
                    profile.verificationStatus
                }}</Badge>
                <Badge variant="outline">{{
                    profile.subscriptionStatus
                }}</Badge>
            </div>
        </div>

        <section
            class="grid gap-5 rounded-lg border p-5 lg:grid-cols-[1fr_1.4fr]"
        >
            <Heading variant="small" title="Listing details" />

            <Form
                v-bind="update.form(props.currentTeam.slug)"
                class="grid gap-5"
                v-slot="{ errors, processing }"
            >
                <input
                    type="hidden"
                    name="availability_status"
                    :value="availabilityStatus"
                />
                <input type="hidden" name="is_public" value="0" />

                <div class="grid gap-2">
                    <Label for="business_name">Business name</Label>
                    <Input
                        id="business_name"
                        name="business_name"
                        :default-value="profile.businessName"
                        required
                        maxlength="255"
                    />
                    <InputError :message="errors.business_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="public_summary">Summary</Label>
                    <textarea
                        id="public_summary"
                        name="public_summary"
                        rows="4"
                        maxlength="1000"
                        class="min-h-24 rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        :value="profile.publicSummary ?? ''"
                    />
                    <InputError :message="errors.public_summary" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="years_experience">Years</Label>
                        <Input
                            id="years_experience"
                            name="years_experience"
                            type="number"
                            min="0"
                            max="80"
                            :default-value="profile.yearsExperience ?? ''"
                        />
                        <InputError :message="errors.years_experience" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="service_radius_km">Radius km</Label>
                        <Input
                            id="service_radius_km"
                            name="service_radius_km"
                            type="number"
                            min="1"
                            max="500"
                            :default-value="profile.serviceRadiusKm ?? ''"
                        />
                        <InputError :message="errors.service_radius_km" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="public_phone">Public phone</Label>
                        <Input
                            id="public_phone"
                            name="public_phone"
                            :default-value="profile.publicPhone ?? ''"
                            maxlength="32"
                        />
                        <InputError :message="errors.public_phone" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="public_email">Public email</Label>
                        <Input
                            id="public_email"
                            name="public_email"
                            type="email"
                            :default-value="profile.publicEmail ?? ''"
                        />
                        <InputError :message="errors.public_email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="availability_status">Availability</Label>
                        <Select v-model="availabilityStatus">
                            <SelectTrigger
                                id="availability_status"
                                class="w-full"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="status in availabilityStatuses"
                                    :key="status"
                                    :value="status"
                                >
                                    {{ status }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.availability_status" />
                    </div>

                    <label class="flex items-center gap-2 self-end text-sm">
                        <input
                            type="checkbox"
                            name="is_public"
                            value="1"
                            class="size-4 rounded border"
                            :checked="profile.isPublic"
                        />
                        Public listing
                    </label>
                </div>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="processing">
                        <Save />
                        Save
                    </Button>
                </div>
            </Form>
        </section>

        <section
            class="grid gap-5 rounded-lg border p-5 lg:grid-cols-[1fr_1.4fr]"
        >
            <Heading variant="small" title="Portfolio" />

            <div class="grid gap-5">
                <Form
                    v-bind="storePortfolio.form(props.currentTeam.slug)"
                    class="grid gap-3"
                    v-slot="{ errors, processing, progress }"
                >
                    <div class="grid gap-2">
                        <Label for="portfolio">Image</Label>
                        <Input
                            id="portfolio"
                            name="portfolio"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                        />
                        <InputError :message="errors.portfolio" />
                    </div>
                    <progress
                        v-if="progress"
                        class="h-2 w-full"
                        :value="progress.percentage"
                        max="100"
                    />
                    <div class="flex justify-end">
                        <Button
                            type="submit"
                            variant="outline"
                            :disabled="processing"
                        >
                            <ImagePlus />
                            Upload
                        </Button>
                    </div>
                </Form>

                <div
                    v-if="profile.portfolio.length > 0"
                    class="grid grid-cols-2 gap-3 sm:grid-cols-3"
                >
                    <img
                        v-for="media in profile.portfolio"
                        :key="media.id"
                        :src="media.url"
                        :alt="media.name"
                        class="aspect-square rounded-md border object-cover"
                    />
                </div>
            </div>
        </section>
    </div>
</template>
