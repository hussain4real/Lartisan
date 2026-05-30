<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BriefcaseBusiness,
    CalendarPlus,
    MapPin,
} from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index as marketplaceIndex } from '@/routes/marketplace';
import { create as createBooking } from '@/routes/marketplace/bookings';
import type { MarketplaceArtisanDetail } from '@/types';

defineProps<{
    artisan: MarketplaceArtisanDetail;
}>();
</script>

<template>
    <Head :title="artisan.businessName" />

    <main class="min-h-screen bg-background text-foreground">
        <div class="mx-auto grid w-full max-w-5xl gap-8 px-4 py-6 sm:px-6">
            <nav class="flex items-center justify-between gap-4">
                <Button as-child variant="ghost" size="sm">
                    <Link :href="marketplaceIndex().url">
                        <ArrowLeft />
                        Marketplace
                    </Link>
                </Button>
                <Button as-child size="sm">
                    <Link :href="createBooking(artisan.id).url">
                        <CalendarPlus />
                        Book artisan
                    </Link>
                </Button>
            </nav>

            <section class="grid gap-5">
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge variant="secondary" class="gap-1.5">
                            <BriefcaseBusiness class="size-3.5" />
                            verified
                        </Badge>
                        <Badge variant="outline">
                            {{ artisan.availabilityStatus }}
                        </Badge>
                    </div>
                    <h1 class="text-3xl font-semibold">
                        {{ artisan.businessName }}
                    </h1>
                    <p
                        v-if="artisan.publicSummary"
                        class="max-w-3xl text-muted-foreground"
                    >
                        {{ artisan.publicSummary }}
                    </p>
                    <p
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <MapPin class="size-4" />
                        {{ artisan.location || 'Location pending' }}
                    </p>
                </div>

                <dl class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg border p-4">
                        <dt class="text-sm text-muted-foreground">
                            Experience
                        </dt>
                        <dd class="font-medium">
                            {{
                                artisan.yearsExperience === null
                                    ? 'Pending'
                                    : `${artisan.yearsExperience} years`
                            }}
                        </dd>
                    </div>
                    <div class="rounded-lg border p-4">
                        <dt class="text-sm text-muted-foreground">
                            Service radius
                        </dt>
                        <dd class="font-medium">
                            {{
                                artisan.serviceRadiusKm === null
                                    ? 'Pending'
                                    : `${artisan.serviceRadiusKm} km`
                            }}
                        </dd>
                    </div>
                    <div class="rounded-lg border p-4">
                        <dt class="text-sm text-muted-foreground">
                            Active services
                        </dt>
                        <dd class="font-medium">
                            {{ artisan.services.length }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="grid gap-4">
                <h2 class="text-lg font-semibold">Services</h2>
                <div class="grid gap-3">
                    <article
                        v-for="service in artisan.services"
                        :key="service.id"
                        class="grid gap-3 rounded-lg border p-4 sm:grid-cols-[1fr_auto] sm:items-center"
                    >
                        <div class="space-y-1">
                            <h3 class="font-medium">{{ service.title }}</h3>
                            <p class="text-sm text-muted-foreground">
                                {{ service.category.name }}
                            </p>
                            <p v-if="service.description" class="text-sm">
                                {{ service.description }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-sm font-medium">
                                <template v-if="service.startingPrice">
                                    {{ service.currencyCode }}
                                    {{ service.startingPrice }}
                                </template>
                            </span>
                            <Button as-child size="sm">
                                <Link :href="createBooking(artisan.id).url">
                                    Book
                                </Link>
                            </Button>
                        </div>
                    </article>
                </div>
            </section>

            <section v-if="artisan.portfolio.length > 0" class="grid gap-4">
                <h2 class="text-lg font-semibold">Portfolio</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <img
                        v-for="item in artisan.portfolio"
                        :key="item.id"
                        :src="item.url"
                        :alt="item.name"
                        class="aspect-[4/3] rounded-lg border object-cover"
                    />
                </div>
            </section>
        </div>
    </main>
</template>
