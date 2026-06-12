<script setup lang="ts">
import { Head, InfiniteScroll, Link, router } from '@inertiajs/vue3';
import { Search, ShieldCheck, SlidersHorizontal } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as marketplaceIndex } from '@/routes/marketplace';
import { show as showArtisan } from '@/routes/marketplace/artisans';
import { create as createBooking } from '@/routes/marketplace/bookings';
import type {
    MarketplaceArtisanPaginator,
    MarketplaceFilters,
    MarketplaceStateOption,
    ServiceCategoryOption,
} from '@/types';
import type { QueryParams } from '@/wayfinder';

type Props = {
    filters: MarketplaceFilters;
    categories: ServiceCategoryOption[];
    states: MarketplaceStateOption[];
    artisans: MarketplaceArtisanPaginator;
};

type FilterForm = {
    query: string;
    service_category_id: string;
    state_id: string;
    local_government_id: string;
    territory_id: string;
};

const props = defineProps<Props>();
const isFiltering = ref(false);
const filterForm = reactive<FilterForm>({
    query: props.filters.query ?? '',
    service_category_id: props.filters.serviceCategoryId?.toString() ?? '',
    state_id: props.filters.stateId?.toString() ?? '',
    local_government_id: props.filters.localGovernmentId?.toString() ?? '',
    territory_id: props.filters.territoryId?.toString() ?? '',
});

const localGovernments = computed(() => {
    return (
        props.states.find((state) => String(state.id) === filterForm.state_id)
            ?.localGovernments ?? []
    );
});

const territories = computed(() => {
    return (
        localGovernments.value.find(
            (localGovernment) =>
                String(localGovernment.id) === filterForm.local_government_id,
        )?.territories ?? []
    );
});

const loadedResultCount = computed(() => props.artisans.data.length);
const resultCountLabel = computed(() => {
    const total = props.artisans.total;
    const noun = total === 1 ? 'result' : 'results';

    if (total === loadedResultCount.value) {
        return `${loadedResultCount.value} ${noun}`;
    }

    return `${loadedResultCount.value} of ${total} ${noun}`;
});

watch(
    () => props.filters,
    (filters) => {
        filterForm.query = filters.query ?? '';
        filterForm.service_category_id =
            filters.serviceCategoryId?.toString() ?? '';
        filterForm.state_id = filters.stateId?.toString() ?? '';
        filterForm.local_government_id =
            filters.localGovernmentId?.toString() ?? '';
        filterForm.territory_id = filters.territoryId?.toString() ?? '';
    },
    { deep: true },
);

const filledValue = (value: string): string | undefined => {
    const trimmed = value.trim();

    return trimmed === '' ? undefined : trimmed;
};

const filterQuery = (): QueryParams => ({
    query: filledValue(filterForm.query),
    service_category_id: filledValue(filterForm.service_category_id),
    state_id: filledValue(filterForm.state_id),
    local_government_id: filledValue(filterForm.local_government_id),
    territory_id: filledValue(filterForm.territory_id),
});

const visitMarketplace = (replace: boolean): void => {
    router.visit(marketplaceIndex.url({ query: filterQuery() }), {
        method: 'get',
        only: ['filters', 'artisans'],
        preserveScroll: true,
        replace,
        reset: ['artisans'],
        onStart: () => {
            isFiltering.value = true;
        },
        onFinish: () => {
            isFiltering.value = false;
        },
    });
};

const applyDynamicFilters = (): void => {
    visitMarketplace(true);
};

const applyStateFilter = (): void => {
    filterForm.local_government_id = '';
    filterForm.territory_id = '';
    applyDynamicFilters();
};

const applyLocalGovernmentFilter = (): void => {
    filterForm.territory_id = '';
    applyDynamicFilters();
};

const submitSearch = (): void => {
    visitMarketplace(false);
};
</script>

<template>
    <Head title="Find artisans" />

    <main class="min-h-screen bg-background text-foreground">
        <header class="border-b">
            <div
                class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6"
            >
                <Link :href="marketplaceIndex().url" aria-label="Lartisan">
                    <AppLogo class="h-9" />
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

                <div class="grid gap-4 rounded-lg border p-4">
                    <form
                        class="grid gap-3 md:grid-cols-[1fr_auto]"
                        @submit.prevent="submitSearch"
                    >
                        <div class="grid gap-2">
                            <Label for="query">Search</Label>
                            <Input
                                id="query"
                                v-model="filterForm.query"
                                name="query"
                                placeholder="Electrical, plumbing, cleaning"
                            />
                        </div>

                        <div class="flex items-end">
                            <Button type="submit" class="w-full md:w-auto">
                                <Search />
                                Search
                            </Button>
                        </div>
                    </form>

                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class="grid gap-2">
                            <Label for="service_category_id">Category</Label>
                            <select
                                id="service_category_id"
                                v-model="filterForm.service_category_id"
                                name="service_category_id"
                                class="h-9 rounded-md border bg-transparent px-3 text-sm"
                                @change="applyDynamicFilters"
                            >
                                <option value="">Any category</option>
                                <option
                                    v-for="category in categories"
                                    :key="category.id"
                                    :value="String(category.id)"
                                >
                                    {{ category.name }}
                                </option>
                            </select>
                        </div>

                        <div class="grid gap-2">
                            <Label for="state_id">State</Label>
                            <select
                                id="state_id"
                                v-model="filterForm.state_id"
                                name="state_id"
                                class="h-9 rounded-md border bg-transparent px-3 text-sm"
                                @change="applyStateFilter"
                            >
                                <option value="">Any state</option>
                                <option
                                    v-for="state in states"
                                    :key="state.id"
                                    :value="String(state.id)"
                                >
                                    {{ state.name }}
                                </option>
                            </select>
                        </div>

                        <div class="grid gap-2">
                            <Label for="local_government_id">LGA</Label>
                            <select
                                id="local_government_id"
                                v-model="filterForm.local_government_id"
                                name="local_government_id"
                                class="h-9 rounded-md border bg-transparent px-3 text-sm disabled:opacity-60"
                                :disabled="localGovernments.length === 0"
                                @change="applyLocalGovernmentFilter"
                            >
                                <option value="">Any LGA</option>
                                <option
                                    v-for="localGovernment in localGovernments"
                                    :key="localGovernment.id"
                                    :value="String(localGovernment.id)"
                                >
                                    {{ localGovernment.name }}
                                </option>
                            </select>
                        </div>

                        <div class="grid gap-2">
                            <Label for="territory_id">Territory</Label>
                            <select
                                id="territory_id"
                                v-model="filterForm.territory_id"
                                name="territory_id"
                                class="h-9 rounded-md border bg-transparent px-3 text-sm disabled:opacity-60"
                                :disabled="territories.length === 0"
                                @change="applyDynamicFilters"
                            >
                                <option value="">Any territory</option>
                                <option
                                    v-for="territory in territories"
                                    :key="territory.id"
                                    :value="String(territory.id)"
                                >
                                    {{ territory.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div
                        class="flex items-center text-sm text-muted-foreground"
                    >
                        <SlidersHorizontal class="mr-2 size-4" />
                        {{ resultCountLabel }}
                        <span v-if="isFiltering" class="ml-2">
                            Updating...
                        </span>
                    </div>
                </div>
            </section>

            <section class="grid gap-4">
                <InfiniteScroll
                    v-if="loadedResultCount > 0"
                    data="artisans"
                    only-next
                    :buffer="600"
                    items-element="#marketplace-artisan-grid"
                >
                    <TransitionGroup
                        id="marketplace-artisan-grid"
                        name="artisan-card"
                        tag="div"
                        class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
                    >
                        <article
                            v-for="artisan in artisans.data"
                            :key="artisan.id"
                            class="grid gap-4 rounded-lg border p-5"
                        >
                            <div class="space-y-2">
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
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
                    </TransitionGroup>

                    <template #loading="{ loading }">
                        <div
                            v-if="loading"
                            class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
                            aria-live="polite"
                        >
                            <article
                                v-for="placeholder in 3"
                                :key="placeholder"
                                class="grid gap-4 rounded-lg border p-5"
                            >
                                <div class="space-y-3">
                                    <div
                                        class="h-5 w-3/4 animate-pulse rounded bg-muted"
                                    ></div>
                                    <div
                                        class="h-4 w-1/2 animate-pulse rounded bg-muted"
                                    ></div>
                                    <div
                                        class="h-4 w-2/3 animate-pulse rounded bg-muted"
                                    ></div>
                                </div>
                                <div class="flex gap-2">
                                    <div
                                        class="h-8 w-24 animate-pulse rounded bg-muted"
                                    ></div>
                                    <div
                                        class="h-8 w-16 animate-pulse rounded bg-muted"
                                    ></div>
                                </div>
                            </article>
                        </div>
                    </template>
                </InfiniteScroll>

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

<style scoped>
.artisan-card-enter-active,
.artisan-card-leave-active,
.artisan-card-move {
    transition:
        opacity 180ms ease,
        transform 180ms ease;
}

.artisan-card-enter-from,
.artisan-card-leave-to {
    opacity: 0;
    transform: translateY(10px);
}

@media (prefers-reduced-motion: reduce) {
    .artisan-card-enter-active,
    .artisan-card-leave-active,
    .artisan-card-move {
        transition: none;
    }

    .artisan-card-enter-from,
    .artisan-card-leave-to {
        opacity: 1;
        transform: none;
    }
}
</style>
