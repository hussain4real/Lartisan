<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import { index as marketplaceIndex } from '@/routes/marketplace';
</script>

<template>
    <Head title="Privacy Policy" />

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
                <h1 class="text-3xl font-semibold">Privacy Policy</h1>
                <p class="max-w-2xl text-sm text-muted-foreground">
                    This page summarizes how Lartisan handles information for
                    accounts, marketplace discovery, bookings, verification,
                    payments, support, and dispute workflows.
                </p>
            </div>

            <div class="grid gap-6 text-sm leading-6 text-muted-foreground">
                <section class="space-y-2">
                    <h2 class="text-base font-semibold text-foreground">
                        Information We Collect
                    </h2>
                    <p>
                        We collect profile details, contact information,
                        marketplace listing content, booking details,
                        verification evidence, payment references, support
                        messages, and usage data needed to operate Lartisan.
                    </p>
                </section>

                <section class="space-y-2">
                    <h2 class="text-base font-semibold text-foreground">
                        How We Use Information
                    </h2>
                    <p>
                        We use information to verify users, publish approved
                        artisan listings, process bookings and payments, prevent
                        misuse, support customers and artisans, and improve
                        marketplace reliability.
                    </p>
                </section>

                <section class="space-y-2">
                    <h2 class="text-base font-semibold text-foreground">
                        Storage And Sharing
                    </h2>
                    <p>
                        Access to private verification, booking, payment, and
                        dispute information is limited to authorized workflows.
                        Public marketplace profile details are visible to
                        visitors when an artisan chooses an approved public
                        listing.
                    </p>
                </section>

                <section class="space-y-2">
                    <h2 class="text-base font-semibold text-foreground">
                        Updates
                    </h2>
                    <p>
                        This staging policy may be updated before production
                        launch as operating, payment, and support processes are
                        finalized.
                    </p>
                </section>
            </div>
        </article>
    </main>
</template>
