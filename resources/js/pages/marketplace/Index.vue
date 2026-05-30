<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Search, ShieldCheck, SlidersHorizontal } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as marketplaceIndex } from '@/routes/marketplace';
import { show as showArtisan } from '@/routes/marketplace/artisans';
import { create as createBooking } from '@/routes/marketplace/bookings';
import type {
    MarketplaceArtisanCard,
    MarketplaceFilters,
    MarketplaceStateOption,
    ServiceCategoryOption,
} from '@/types';

type Props = {
    filters: MarketplaceFilters;
    categories: ServiceCategoryOption[];
    states: MarketplaceStateOption[];
    artisans: MarketplaceArtisanCard[];
};

const props = defineProps<Props>();
const selectedStateId = ref(props.filters.stateId?.toString() ?? '');
const selectedLocalGovernmentId = ref(
    props.filters.localGovernmentId?.toString() ?? '',
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
    <Head title="Find artisans" />

    <main class="min-h-screen bg-background text-foreground">
        <header class="border-b">
            <div
                class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6"
            >
                <Link :href="marketplaceIndex().url" class="font-semibold">
                    Lartisan
                </Link>
                <Badge variant="secondary" class="gap-1.5">
                    <ShieldCheck class="size-3.5" />
                    Verified marketplace
                </Badge>
            </div>
        </header>

        <div class="mx-auto grid w-full max-w-6xl gap-8 px-4 py-6 sm:px-6">
            <section class="grid gap-5">
                <div class="max-w-2xl space-y-2">
                    <h1 class="text-2xl font-semibold">
                        Find verified artisans
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Search by service and location, then send a booking
                        request directly to a verified subscribed artisan.
                    </p>
                </div>

                <Form
                    v-bind="marketplaceIndex.form()"
                    class="grid gap-4 rounded-lg border p-4 md:grid-cols-[1.4fr_1fr_1fr_auto]"
                >
                    <div class="grid gap-2">
                        <Label for="query">Search</Label>
                        <Input
                            id="query"
                            name="query"
                            :default-value="filters.query ?? ''"
                            placeholder="Electrical, plumbing, cleaning"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="service_category_id">Category</Label>
                        <select
                            id="service_category_id"
                            name="service_category_id"
                            class="h-9 rounded-md border bg-transparent px-3 text-sm"
                        >
                            <option value="">Any category</option>
                            <option
                                v-for="category in categories"
                                :key="category.id"
                                :value="category.id"
                                :selected="
                                    filters.serviceCategoryId === category.id
                                "
                            >
                                {{ category.name }}
                            </option>
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="state_id">State</Label>
                        <select
                            id="state_id"
                            v-model="selectedStateId"
                            name="state_id"
                            class="h-9 rounded-md border bg-transparent px-3 text-sm"
                        >
                            <option value="">Any state</option>
                            <option
                                v-for="state in states"
                                :key="state.id"
                                :value="state.id"
                            >
                                {{ state.name }}
                            </option>
                        </select>
                    </div>

                    <div class="flex items-end">
                        <Button type="submit" class="w-full">
                            <Search />
                            Search
                        </Button>
                    </div>

                    <div class="grid gap-2 md:col-start-2">
                        <Label for="local_government_id">LGA</Label>
                        <select
                            id="local_government_id"
                            v-model="selectedLocalGovernmentId"
                            name="local_government_id"
                            class="h-9 rounded-md border bg-transparent px-3 text-sm"
                        >
                            <option value="">Any LGA</option>
                            <option
                                v-for="localGovernment in localGovernments"
                                :key="localGovernment.id"
                                :value="localGovernment.id"
                            >
                                {{ localGovernment.name }}
                            </option>
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="territory_id">Territory</Label>
                        <select
                            id="territory_id"
                            name="territory_id"
                            class="h-9 rounded-md border bg-transparent px-3 text-sm"
                        >
                            <option value="">Any territory</option>
                            <option
                                v-for="territory in territories"
                                :key="territory.id"
                                :value="territory.id"
                                :selected="filters.territoryId === territory.id"
                            >
                                {{ territory.name }}
                            </option>
                        </select>
                    </div>

                    <div
                        class="hidden items-end text-sm text-muted-foreground md:flex"
                    >
                        <SlidersHorizontal class="mr-2 size-4" />
                        {{ artisans.length }} result{{
                            artisans.length === 1 ? '' : 's'
                        }}
                    </div>
                </Form>
            </section>

            <section class="grid gap-4">
                <div
                    v-if="artisans.length > 0"
                    class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
                >
                    <article
                        v-for="artisan in artisans"
                        :key="artisan.id"
                        class="grid gap-4 rounded-lg border p-5"
                    >
                        <div class="space-y-2">
                            <div class="flex items-start justify-between gap-3">
                                <h2 class="font-semibold">
                                    {{ artisan.businessName }}
                                </h2>
                                <Badge variant="outline">{{
                                    artisan.availabilityStatus
                                }}</Badge>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ artisan.location || 'Location pending' }}
                            </p>
                            <p class="text-sm">
                                {{ artisan.servicesCount }} active service{{
                                    artisan.servicesCount === 1 ? '' : 's'
                                }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Button as-child variant="outline" size="sm">
                                <Link :href="showArtisan(artisan.id).url">
                                    View profile
                                </Link>
                            </Button>
                            <Button as-child size="sm">
                                <Link :href="createBooking(artisan.id).url">
                                    Book
                                </Link>
                            </Button>
                        </div>
                    </article>
                </div>

                <p
                    v-else
                    class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                >
                    No verified subscribed artisans match these filters yet.
                </p>
            </section>
        </div>
    </main>
</template>
