<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { PlusCircle, Wrench } from 'lucide-vue-next';
import { computed, ref } from 'vue';
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
import { store } from '@/routes/artisan/services';
import type { ArtisanServiceItem, ServiceCategoryOption, Team } from '@/types';

type Props = {
    currentTeam: Team;
    services: ArtisanServiceItem[];
    categories: ServiceCategoryOption[];
    statuses: string[];
};

const props = defineProps<Props>();
const selectedCategoryId = ref(
    props.categories.length > 0 ? String(props.categories[0].id) : '',
);
const selectedStatus = ref('active');
const canCreate = computed(() => selectedCategoryId.value !== '');

defineOptions({
    layout: (props: { currentTeam: Team }) => ({
        breadcrumbs: [
            {
                title: 'Artisan',
                href: artisanDashboard(props.currentTeam.slug).url,
            },
            {
                title: 'Services',
                href: '#',
            },
        ],
    }),
});
</script>

<template>
    <Head title="Artisan services" />

    <h1 class="sr-only">Artisan services</h1>

    <div class="flex flex-col gap-8 p-4 sm:p-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <Heading
                variant="small"
                title="Services"
                description="Catalog entries for this artisan business"
            />
            <Badge variant="secondary" class="w-fit gap-1.5">
                <Wrench class="size-3.5" />
                {{ services.length }}
            </Badge>
        </div>

        <section
            class="grid gap-5 rounded-lg border p-5 lg:grid-cols-[1fr_1.4fr]"
        >
            <Heading variant="small" title="Add service" />

            <Form
                v-bind="store.form(props.currentTeam.slug)"
                class="grid gap-5"
                v-slot="{ errors, processing }"
            >
                <input
                    type="hidden"
                    name="service_category_id"
                    :value="selectedCategoryId"
                />
                <input type="hidden" name="status" :value="selectedStatus" />

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="service_category">Category</Label>
                        <Select v-model="selectedCategoryId">
                            <SelectTrigger id="service_category" class="w-full">
                                <SelectValue placeholder="Select category" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="category in categories"
                                    :key="category.id"
                                    :value="String(category.id)"
                                >
                                    {{ category.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.service_category_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="status">Status</Label>
                        <Select v-model="selectedStatus">
                            <SelectTrigger id="status" class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="status in statuses"
                                    :key="status"
                                    :value="status"
                                >
                                    {{ status }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.status" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="title">Title</Label>
                    <Input id="title" name="title" required maxlength="255" />
                    <InputError :message="errors.title" />
                </div>

                <div class="grid gap-2">
                    <Label for="description">Description</Label>
                    <textarea
                        id="description"
                        name="description"
                        rows="3"
                        maxlength="1000"
                        class="min-h-20 rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    />
                    <InputError :message="errors.description" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="starting_price">Starting price</Label>
                        <Input
                            id="starting_price"
                            name="starting_price"
                            type="number"
                            min="0"
                            step="0.01"
                        />
                        <InputError :message="errors.starting_price" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="currency_code">Currency</Label>
                        <Input
                            id="currency_code"
                            name="currency_code"
                            default-value="NGN"
                            maxlength="3"
                            required
                        />
                        <InputError :message="errors.currency_code" />
                    </div>
                </div>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="processing || !canCreate">
                        <PlusCircle />
                        Add
                    </Button>
                </div>
            </Form>
        </section>

        <section class="space-y-4">
            <Heading variant="small" title="Catalog" />

            <div v-if="services.length > 0" class="grid gap-3">
                <div
                    v-for="service in services"
                    :key="service.id"
                    class="grid gap-3 rounded-lg border p-4 sm:grid-cols-[1fr_auto] sm:items-center"
                >
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-medium">{{ service.title }}</h2>
                            <Badge variant="outline">{{
                                service.status
                            }}</Badge>
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ service.category.name }}
                        </p>
                        <p v-if="service.description" class="text-sm">
                            {{ service.description }}
                        </p>
                    </div>
                    <div class="text-sm font-medium">
                        <span v-if="service.startingPrice">
                            {{ service.currencyCode }}
                            {{ service.startingPrice }}
                        </span>
                    </div>
                </div>
            </div>

            <p
                v-else
                class="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground"
            >
                No services yet.
            </p>
        </section>
    </div>
</template>
