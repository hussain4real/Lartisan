<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { RefreshCw, Search, ShieldCheck, Wifi, WifiOff } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { listSavedOfflineDrafts } from '@/composables/useOfflineDraft';
import type { OfflineDraftSummary } from '@/composables/useOfflineDraft';
import { home } from '@/routes';
import { index as marketplaceIndex } from '@/routes/marketplace';

const isOnline = ref<boolean>(
    typeof navigator === 'undefined' ? true : navigator.onLine,
);
const savedDrafts = ref<OfflineDraftSummary[]>([]);

const refreshOfflineState = (): void => {
    isOnline.value = typeof navigator === 'undefined' ? true : navigator.onLine;
    savedDrafts.value = listSavedOfflineDrafts();
};

const retryConnection = (): void => {
    refreshOfflineState();

    if (typeof window !== 'undefined' && navigator.onLine) {
        window.location.reload();
    }
};

onMounted(() => {
    refreshOfflineState();

    if (typeof window !== 'undefined') {
        window.addEventListener('online', refreshOfflineState);
        window.addEventListener('offline', refreshOfflineState);
    }
});

onBeforeUnmount(() => {
    if (typeof window === 'undefined') {
        return;
    }

    window.removeEventListener('online', refreshOfflineState);
    window.removeEventListener('offline', refreshOfflineState);
});
</script>

<template>
    <Head title="Offline" />

    <main class="min-h-screen bg-background text-foreground">
        <header class="border-b">
            <div
                class="mx-auto flex w-full max-w-4xl items-center justify-between gap-4 px-4 py-4 sm:px-6"
            >
                <Link :href="home().url" aria-label="Lartisan">
                    <AppLogo class="h-9" />
                </Link>
                <Badge
                    :variant="isOnline ? 'secondary' : 'outline'"
                    class="gap-1.5"
                    data-test="offline-connection-badge"
                >
                    <Wifi v-if="isOnline" class="size-3.5" />
                    <WifiOff v-else class="size-3.5" />
                    {{ isOnline ? 'Connection restored' : 'Offline mode' }}
                </Badge>
            </div>
        </header>

        <section class="mx-auto grid w-full max-w-4xl gap-6 px-4 py-12 sm:px-6">
            <div class="space-y-3">
                <h1 class="text-3xl font-semibold">
                    You are currently offline
                </h1>
                <p class="max-w-2xl text-sm leading-6 text-muted-foreground">
                    Lartisan could not reach the server for this page. Any Area
                    Agent field-visit, artisan onboarding, profile, service
                    catalog, or KYC drafts saved on this device stay preserved
                    until your connection returns.
                </p>
            </div>

            <div
                class="grid gap-4 rounded-lg border bg-card p-5 sm:grid-cols-2"
            >
                <div class="space-y-1">
                    <p class="text-xs font-medium text-muted-foreground">
                        Device drafts preserved
                    </p>
                    <p
                        class="text-2xl font-semibold"
                        data-test="offline-saved-drafts-count"
                    >
                        {{ savedDrafts.length }}
                    </p>
                </div>

                <div class="space-y-1">
                    <p class="text-xs font-medium text-muted-foreground">
                        Field operations tip
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Return to the open form tab or reconnect to submit your
                        queued draft with GPS coordinates and notes intact.
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <Button
                    type="button"
                    data-test="offline-retry-button"
                    class="min-h-11"
                    @click="retryConnection"
                >
                    <RefreshCw />
                    Retry connection
                </Button>

                <Button
                    as-child
                    type="button"
                    variant="outline"
                    class="min-h-11"
                >
                    <Link :href="marketplaceIndex().url">
                        <Search />
                        Open marketplace
                    </Link>
                </Button>

                <Button as-child type="button" variant="ghost" class="min-h-11">
                    <Link :href="home().url">
                        <ShieldCheck />
                        Home
                    </Link>
                </Button>
            </div>
        </section>
    </main>
</template>
