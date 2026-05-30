<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import {
    BriefcaseBusiness,
    CheckCircle2,
    MapPin,
    PlusCircle,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
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
import { create, store } from '@/routes/artisan/onboarding';
import type {
    ArtisanProfileSummary,
    CountryOption,
    StateOption,
    Team,
} from '@/types';

type Props = {
    currentTeam: Team;
    existingProfiles: ArtisanProfileSummary[];
    geography: {
        countries: CountryOption[];
    };
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            {
                title: 'Artisan onboarding',
                href: create(props.currentTeam.slug),
            },
        ],
    }),
});

const optionValue = (option?: { id: number } | null) =>
    option ? String(option.id) : '';

const firstCountry = () => props.geography.countries[0] ?? null;
const firstState = (country?: CountryOption | null) =>
    country?.states.find(
        (state) => state.slug === 'federal-capital-territory',
    ) ??
    country?.states[0] ??
    null;
const firstLocalGovernment = (state?: StateOption | null) =>
    state?.localGovernments.find(
        (localGovernment) =>
            localGovernment.slug === 'abuja-municipal-area-council',
    ) ??
    state?.localGovernments[0] ??
    null;

const selectedCountryId = ref(optionValue(firstCountry()));
const selectedStateId = ref(optionValue(firstState(firstCountry())));
const selectedLocalGovernmentId = ref(
    optionValue(firstLocalGovernment(firstState(firstCountry()))),
);
const selectedTerritoryId = ref('none');

const selectedCountry = computed(
    () =>
        props.geography.countries.find(
            (country) => String(country.id) === selectedCountryId.value,
        ) ?? null,
);

const stateOptions = computed(() => selectedCountry.value?.states ?? []);

const selectedState = computed(
    () =>
        stateOptions.value.find(
            (state) => String(state.id) === selectedStateId.value,
        ) ?? null,
);

const localGovernmentOptions = computed(
    () => selectedState.value?.localGovernments ?? [],
);

const selectedLocalGovernment = computed(
    () =>
        localGovernmentOptions.value.find(
            (localGovernment) =>
                String(localGovernment.id) === selectedLocalGovernmentId.value,
        ) ?? null,
);

const territoryOptions = computed(
    () => selectedLocalGovernment.value?.territories ?? [],
);

const territoryFormValue = computed(() =>
    selectedTerritoryId.value === 'none' ? '' : selectedTerritoryId.value,
);

watch(selectedCountryId, () => {
    selectedStateId.value = optionValue(firstState(selectedCountry.value));
});

watch(selectedStateId, () => {
    selectedLocalGovernmentId.value = optionValue(
        firstLocalGovernment(selectedState.value),
    );
});

watch(selectedLocalGovernmentId, () => {
    selectedTerritoryId.value = 'none';
});
</script>

<template>
    <Head title="Artisan onboarding" />

    <h1 class="sr-only">Artisan onboarding</h1>

    <div class="flex flex-col gap-8">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                variant="small"
                title="Artisan onboarding"
                description="Create an artisan business workspace with a verified operating location."
            />

            <Badge variant="secondary" class="w-fit gap-1.5">
                <BriefcaseBusiness class="size-3.5" />
                Business workspace
            </Badge>
        </div>

        <section
            class="grid gap-5 rounded-lg border p-5 md:grid-cols-[1fr_1.35fr]"
        >
            <div class="space-y-3">
                <div
                    class="flex size-10 items-center justify-center rounded-md bg-muted text-muted-foreground"
                >
                    <MapPin class="size-5" />
                </div>
                <Heading
                    variant="small"
                    title="Business profile"
                    description="Set the business name and its operating area."
                />
            </div>

            <Form
                v-bind="store.form(props.currentTeam.slug)"
                class="grid gap-5"
                v-slot="{ errors, processing }"
            >
                <input
                    type="hidden"
                    name="country_id"
                    :value="selectedCountryId"
                />
                <input type="hidden" name="state_id" :value="selectedStateId" />
                <input
                    type="hidden"
                    name="local_government_id"
                    :value="selectedLocalGovernmentId"
                />
                <input
                    type="hidden"
                    name="territory_id"
                    :value="territoryFormValue"
                />

                <div class="grid gap-2">
                    <Label for="business_name">Business name</Label>
                    <Input
                        id="business_name"
                        name="business_name"
                        data-test="artisan-business-name"
                        required
                        maxlength="255"
                        placeholder="Bright Sparks Electrical"
                    />
                    <InputError :message="errors.business_name" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="country">Country</Label>
                        <Select
                            v-model="selectedCountryId"
                            data-test="artisan-country"
                        >
                            <SelectTrigger id="country" class="w-full">
                                <SelectValue placeholder="Select country" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="country in geography.countries"
                                    :key="country.id"
                                    :value="String(country.id)"
                                >
                                    {{ country.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.country_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="state">State</Label>
                        <Select
                            v-model="selectedStateId"
                            data-test="artisan-state"
                            :disabled="stateOptions.length === 0"
                        >
                            <SelectTrigger id="state" class="w-full">
                                <SelectValue placeholder="Select state" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="state in stateOptions"
                                    :key="state.id"
                                    :value="String(state.id)"
                                >
                                    {{ state.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.state_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="local_government">LGA</Label>
                        <Select
                            v-model="selectedLocalGovernmentId"
                            data-test="artisan-lga"
                            :disabled="localGovernmentOptions.length === 0"
                        >
                            <SelectTrigger id="local_government" class="w-full">
                                <SelectValue placeholder="Select LGA" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="localGovernment in localGovernmentOptions"
                                    :key="localGovernment.id"
                                    :value="String(localGovernment.id)"
                                >
                                    {{ localGovernment.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.local_government_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="territory">Territory</Label>
                        <Select
                            v-model="selectedTerritoryId"
                            data-test="artisan-territory"
                            :disabled="territoryOptions.length === 0"
                        >
                            <SelectTrigger id="territory" class="w-full">
                                <SelectValue placeholder="Optional" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none"
                                    >No territory</SelectItem
                                >
                                <SelectItem
                                    v-for="territory in territoryOptions"
                                    :key="territory.id"
                                    :value="String(territory.id)"
                                >
                                    {{ territory.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.territory_id" />
                    </div>
                </div>

                <div class="flex items-center justify-end">
                    <Button
                        type="submit"
                        data-test="artisan-onboarding-submit"
                        :disabled="processing || !selectedLocalGovernmentId"
                    >
                        <PlusCircle />
                        Create business
                    </Button>
                </div>
            </Form>
        </section>

        <section class="space-y-4">
            <Heading
                variant="small"
                title="Existing artisan businesses"
                description="Business workspaces already attached to this account."
            />

            <div v-if="existingProfiles.length > 0" class="grid gap-3">
                <div
                    v-for="profile in existingProfiles"
                    :key="profile.id"
                    data-test="artisan-profile-row"
                    class="grid gap-3 rounded-lg border p-4 sm:grid-cols-[1fr_auto] sm:items-center"
                >
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-medium">
                                {{ profile.businessName }}
                            </h2>
                            <Badge variant="outline">
                                {{ profile.verificationStatus }}
                            </Badge>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ profile.location || profile.team.name }}
                        </p>
                    </div>

                    <div
                        class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                    >
                        <CheckCircle2 class="size-4" />
                        <span>{{ profile.subscriptionStatus }}</span>
                        <span>{{ profile.availabilityStatus }}</span>
                    </div>
                </div>
            </div>

            <p
                v-else
                class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                No artisan businesses yet.
            </p>
        </section>
    </div>
</template>
