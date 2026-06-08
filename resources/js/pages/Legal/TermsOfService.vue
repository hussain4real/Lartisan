<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import { index as marketplaceIndex } from '@/routes/marketplace';
</script>

<template>
    <Head title="Terms of Service" />

    <main class="min-h-screen bg-background text-foreground">
        <header class="border-b">
            <div
                class="mx-auto flex w-full max-w-4xl items-center justify-between gap-4 px-4 py-4 sm:px-6"
            >
                <Link :href="marketplaceIndex().url" aria-label="Lartisan">
                    <AppLogo class="h-9" />
                </Link>
                <Button as-child variant="outline" size="sm">
                    <Link :href="marketplaceIndex().url">Marketplace</Link>
                </Button>
            </div>
        </header>

        <article class="mx-auto grid w-full max-w-4xl gap-8 px-4 py-10 sm:px-6">
            <div class="space-y-3">
                <p class="text-sm font-medium text-muted-foreground">
                    Lartisan
                </p>
                <h1 class="text-3xl font-semibold">Terms of Service</h1>
                <p class="max-w-2xl text-sm text-muted-foreground">
                    These terms summarize expected use of Lartisan marketplace,
                    artisan onboarding, bookings, payments, reviews, support,
                    and dispute workflows.
                </p>
            </div>

            <div class="grid gap-6 text-sm leading-6 text-muted-foreground">
                <section class="space-y-2">
                    <h2 class="text-base font-semibold text-foreground">
                        Marketplace Use
                    </h2>
                    <p>
                        Customers and artisans should provide accurate
                        information, use the platform respectfully, and avoid
                        fraudulent, misleading, unlawful, or unsafe activity.
                    </p>
                </section>

                <section class="space-y-2">
                    <h2 class="text-base font-semibold text-foreground">
                        Artisan Listings
                    </h2>
                    <p>
                        Artisan marketplace visibility depends on approved
                        verification, an active subscription, public profile
                        settings, and active services that meet Lartisan quality
                        expectations.
                    </p>
                </section>

                <section class="space-y-2">
                    <h2 class="text-base font-semibold text-foreground">
                        Bookings And Payments
                    </h2>
                    <p>
                        Booking, payment, completion, payout, review, and
                        dispute flows should be handled through Lartisan so both
                        customers and artisans have a clear record of the work.
                    </p>
                </section>

                <section class="space-y-2">
                    <h2 class="text-base font-semibold text-foreground">
                        Updates
                    </h2>
                    <p>
                        These staging terms may be updated before production
                        launch as marketplace operations, payment handling, and
                        support processes are finalized.
                    </p>
                </section>
            </div>
        </article>
    </main>
</template>
