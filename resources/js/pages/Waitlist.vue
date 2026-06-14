<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { CheckCircle2, MapPin, Send, Sparkles, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { store } from '@/actions/App/Http/Controllers/WaitlistController';
import AppLogo from '@/components/AppLogo.vue';
import InputError from '@/components/InputError.vue';
import ThemeSwitcher from '@/components/ThemeSwitcher.vue';
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
import { Spinner } from '@/components/ui/spinner';
import type {
    CountryOption,
    ServiceCategoryOption,
    StateOption,
} from '@/types';

type AudienceTypeOption = {
    value: string;
    label: string;
};

type Props = {
    audienceTypes: AudienceTypeOption[];
    geography: {
        countries: CountryOption[];
    };
    serviceCategories: ServiceCategoryOption[];
    joined: boolean;
    joinedEmail: string | null;
};

const props = defineProps<Props>();

const nigeriaIsoCode = 'NG';
const outsideNigeriaIsoCode = 'ZZ';

const optionValue = (option?: { id: number } | null) =>
    option ? String(option.id) : '';

const firstCountry = () =>
    props.geography.countries.find(
        (country) => country.isoCode === nigeriaIsoCode,
    ) ??
    props.geography.countries[0] ??
    null;
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

const selectedAudienceType = ref(props.audienceTypes[0]?.value ?? 'customer');
const selectedCountryId = ref(optionValue(firstCountry()));
const selectedStateId = ref(optionValue(firstState(firstCountry())));
const selectedLocalGovernmentId = ref(
    optionValue(firstLocalGovernment(firstState(firstCountry()))),
);
const selectedTerritoryId = ref('none');
const selectedServiceCategoryId = ref('none');

const selectedCountry = computed(
    () =>
        props.geography.countries.find(
            (country) => String(country.id) === selectedCountryId.value,
        ) ?? null,
);

const outsideNigeriaSelected = computed(
    () => selectedCountry.value?.isoCode === outsideNigeriaIsoCode,
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

const serviceCategoryFormValue = computed(() =>
    selectedServiceCategoryId.value === 'none'
        ? ''
        : selectedServiceCategoryId.value,
);

watch(selectedCountryId, () => {
    if (outsideNigeriaSelected.value) {
        selectedStateId.value = '';
        selectedLocalGovernmentId.value = '';
        selectedTerritoryId.value = 'none';

        return;
    }

    selectedStateId.value = optionValue(firstState(selectedCountry.value));
});

watch(selectedStateId, () => {
    if (outsideNigeriaSelected.value) {
        selectedLocalGovernmentId.value = '';

        return;
    }

    selectedLocalGovernmentId.value = optionValue(
        firstLocalGovernment(selectedState.value),
    );
});

watch(selectedLocalGovernmentId, () => {
    selectedTerritoryId.value = 'none';
});
</script>

<template>
    <Head title="Join the waitlist" />

    <main
        class="min-h-screen bg-background text-foreground selection:bg-primary selection:text-primary-foreground"
    >
        <section class="relative isolate min-h-screen overflow-hidden">
            <div class="absolute inset-0 bg-background" aria-hidden="true" />
            <div
                class="absolute inset-y-0 right-0 hidden w-1/2 bg-brand lg:block"
                aria-hidden="true"
            />

            <header
                class="relative z-10 mx-auto flex w-full max-w-[92rem] items-center justify-between gap-4 px-5 py-5 sm:px-8 xl:px-10"
            >
                <div class="flex items-center gap-3" aria-label="Lartisan">
                    <span
                        class="rounded-lg bg-white/95 px-3 py-2 shadow-lg shadow-black/10"
                    >
                        <AppLogo class="h-8" />
                    </span>
                </div>

                <ThemeSwitcher
                    class="border border-border bg-card/80 text-card-foreground shadow-sm backdrop-blur"
                />
            </header>

            <div
                class="relative z-10 mx-auto grid min-h-[calc(100vh-5.5rem)] w-full max-w-[92rem] gap-10 px-5 pb-12 sm:px-8 lg:grid-cols-[0.92fr_1.08fr] lg:items-center xl:px-10"
            >
                <div class="max-w-2xl py-8 lg:py-16">
                    <div
                        class="inline-flex items-center gap-2 rounded-full border border-border bg-card/80 px-3 py-1.5 text-sm text-muted-foreground shadow-sm backdrop-blur"
                    >
                        <Sparkles class="size-4 text-primary" />
                        Launch access is opening in phases
                    </div>

                    <h1
                        class="mt-8 text-5xl leading-[1.02] font-semibold sm:text-6xl lg:text-7xl"
                    >
                        Join the Lartisan waitlist.
                    </h1>

                    <p
                        class="mt-6 max-w-xl text-xl leading-8 text-muted-foreground"
                    >
                        Be first in line for a verified artisan marketplace
                        built around real local coverage, trusted service
                        discovery, and accountable operations.
                    </p>

                    <div class="mt-10 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-lg border bg-card/80 p-4 shadow-sm">
                            <Users class="size-5 text-primary" />
                            <p class="mt-3 text-sm font-medium">Customers</p>
                            <p
                                class="mt-1 text-sm leading-5 text-muted-foreground"
                            >
                                Find verified service providers.
                            </p>
                        </div>
                        <div class="rounded-lg border bg-card/80 p-4 shadow-sm">
                            <CheckCircle2 class="size-5 text-primary" />
                            <p class="mt-3 text-sm font-medium">Artisans</p>
                            <p
                                class="mt-1 text-sm leading-5 text-muted-foreground"
                            >
                                Prepare your business profile.
                            </p>
                        </div>
                        <div class="rounded-lg border bg-card/80 p-4 shadow-sm">
                            <MapPin class="size-5 text-primary" />
                            <p class="mt-3 text-sm font-medium">Operations</p>
                            <p
                                class="mt-1 text-sm leading-5 text-muted-foreground"
                            >
                                Track demand by location.
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-lg border bg-card p-5 text-card-foreground shadow-2xl shadow-black/12 sm:p-6 lg:ml-auto lg:w-full lg:max-w-2xl"
                >
                    <div
                        v-if="joined"
                        class="mb-6 rounded-lg border border-primary/25 bg-primary/10 p-4 text-sm"
                    >
                        <div class="flex gap-3">
                            <CheckCircle2 class="mt-0.5 size-5 text-primary" />
                            <div>
                                <p class="font-medium text-foreground">
                                    You're on the waitlist.
                                </p>
                                <p class="mt-1 leading-6 text-muted-foreground">
                                    We recorded your interest<span
                                        v-if="joinedEmail"
                                    >
                                        at {{ joinedEmail }}</span
                                    >. We'll contact you as access opens.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="mb-6">
                        <p class="text-sm font-medium text-primary">
                            Waitlist details
                        </p>
                        <h2 class="mt-2 text-2xl font-semibold">
                            Tell us where you fit.
                        </h2>
                        <p class="mt-2 text-sm leading-6 text-muted-foreground">
                            This helps us prioritize launch coverage by role,
                            service category, and operating area.
                        </p>
                    </div>

                    <Form
                        v-bind="store.form()"
                        :reset-on-success="[
                            'name',
                            'email',
                            'phone',
                            'business_name',
                            'note',
                        ]"
                        v-slot="{ errors, processing }"
                        class="grid gap-5"
                    >
                        <input
                            type="hidden"
                            name="audience_type"
                            :value="selectedAudienceType"
                        />
                        <input
                            type="hidden"
                            name="country_id"
                            :value="selectedCountryId"
                        />
                        <input
                            type="hidden"
                            name="state_id"
                            :value="selectedStateId"
                        />
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
                        <input
                            type="hidden"
                            name="service_category_id"
                            :value="serviceCategoryFormValue"
                        />

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="name">Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    maxlength="255"
                                    autocomplete="name"
                                    placeholder="Amina Bello"
                                />
                                <InputError :message="errors.name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="audience_type"
                                    >I am joining as</Label
                                >
                                <Select v-model="selectedAudienceType">
                                    <SelectTrigger
                                        id="audience_type"
                                        class="w-full"
                                    >
                                        <SelectValue
                                            placeholder="Select role"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="audienceType in audienceTypes"
                                            :key="audienceType.value"
                                            :value="audienceType.value"
                                        >
                                            {{ audienceType.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError :message="errors.audience_type" />
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    maxlength="255"
                                    autocomplete="email"
                                    placeholder="you@example.com"
                                />
                                <InputError :message="errors.email" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="phone">Phone</Label>
                                <Input
                                    id="phone"
                                    type="tel"
                                    name="phone"
                                    required
                                    maxlength="64"
                                    autocomplete="tel"
                                    placeholder="+234 800 000 0000"
                                />
                                <InputError :message="errors.phone" />
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="business_name">Business name</Label>
                                <Input
                                    id="business_name"
                                    name="business_name"
                                    maxlength="255"
                                    autocomplete="organization"
                                    placeholder="Optional"
                                />
                                <InputError :message="errors.business_name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="service_category">
                                    Service category
                                </Label>
                                <Select v-model="selectedServiceCategoryId">
                                    <SelectTrigger
                                        id="service_category"
                                        class="w-full"
                                    >
                                        <SelectValue placeholder="Optional" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">
                                            Not sure yet
                                        </SelectItem>
                                        <SelectItem
                                            v-for="category in serviceCategories"
                                            :key="category.id"
                                            :value="String(category.id)"
                                        >
                                            {{ category.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="errors.service_category_id"
                                />
                            </div>
                        </div>

                        <div
                            class="grid gap-4"
                            :class="
                                outsideNigeriaSelected
                                    ? 'sm:grid-cols-1'
                                    : 'sm:grid-cols-2'
                            "
                        >
                            <div class="grid gap-2">
                                <Label for="country">Country</Label>
                                <Select v-model="selectedCountryId">
                                    <SelectTrigger id="country" class="w-full">
                                        <SelectValue
                                            placeholder="Select country"
                                        />
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

                            <div
                                v-if="!outsideNigeriaSelected"
                                class="grid gap-2"
                            >
                                <Label for="state">State</Label>
                                <Select
                                    v-model="selectedStateId"
                                    :disabled="stateOptions.length === 0"
                                >
                                    <SelectTrigger id="state" class="w-full">
                                        <SelectValue
                                            placeholder="Select state"
                                        />
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
                        </div>

                        <div
                            v-if="!outsideNigeriaSelected"
                            class="grid gap-4 sm:grid-cols-2"
                        >
                            <div class="grid gap-2">
                                <Label for="local_government">
                                    Local government
                                </Label>
                                <Select
                                    v-model="selectedLocalGovernmentId"
                                    :disabled="
                                        localGovernmentOptions.length === 0
                                    "
                                >
                                    <SelectTrigger
                                        id="local_government"
                                        class="w-full"
                                    >
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
                                <InputError
                                    :message="errors.local_government_id"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="territory">Territory</Label>
                                <Select
                                    v-model="selectedTerritoryId"
                                    :disabled="territoryOptions.length === 0"
                                >
                                    <SelectTrigger
                                        id="territory"
                                        class="w-full"
                                    >
                                        <SelectValue placeholder="Optional" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">
                                            No specific territory
                                        </SelectItem>
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

                        <div class="grid gap-2">
                            <Label for="note">Anything we should know?</Label>
                            <textarea
                                id="note"
                                name="note"
                                maxlength="1000"
                                rows="4"
                                class="min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40"
                                placeholder="Share your service need, business type, or launch area."
                            />
                            <InputError :message="errors.note" />
                        </div>

                        <div class="grid gap-2">
                            <label
                                for="contact_consent"
                                class="flex items-start gap-3 rounded-lg border bg-muted/40 p-3 text-sm leading-6"
                            >
                                <input
                                    id="contact_consent"
                                    type="checkbox"
                                    name="contact_consent"
                                    value="1"
                                    required
                                    class="mt-1 size-4 rounded border-input text-primary focus-visible:ring-ring"
                                />
                                <span>
                                    I agree that Lartisan may contact me about
                                    launch access and waitlist updates.
                                </span>
                            </label>
                            <InputError :message="errors.contact_consent" />
                        </div>

                        <Button
                            type="submit"
                            size="lg"
                            class="h-12 rounded-full"
                            :disabled="processing"
                        >
                            <Spinner v-if="processing" />
                            <Send v-else class="size-4" />
                            Join the waitlist
                        </Button>
                    </Form>
                </div>
            </div>
        </section>
    </main>
</template>
