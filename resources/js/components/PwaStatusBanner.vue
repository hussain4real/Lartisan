<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Download, Wifi, WifiOff } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { usePwa } from '@/composables/usePwa';
import { offline } from '@/routes';

const {
    isOnline,
    reconnectedMessage,
    canInstall,
    installStatus,
    isInstalling,
    promptInstall,
} = usePwa();

const isVisible = computed(
    () =>
        !isOnline.value ||
        reconnectedMessage.value !== null ||
        canInstall.value ||
        installStatus.value !== '',
);
</script>

<template>
    <div
        data-test="pwa-status-banner"
        :hidden="!isVisible"
        class="border-b border-border bg-card px-4 py-2.5 text-sm text-foreground sm:px-6"
    >
        <div
            class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex flex-wrap items-center gap-2">
                <Badge
                    v-if="!isOnline"
                    variant="secondary"
                    class="gap-1.5 bg-warning/15 text-foreground"
                >
                    <WifiOff class="size-3.5" />
                    Offline mode
                </Badge>
                <Badge
                    v-else-if="reconnectedMessage"
                    variant="secondary"
                    class="gap-1.5"
                >
                    <Wifi class="size-3.5" />
                    Online
                </Badge>

                <p
                    v-if="!isOnline"
                    role="status"
                    aria-live="polite"
                    class="text-xs text-muted-foreground"
                >
                    You are offline. Field-visit and artisan form drafts can
                    still be saved on this device.
                    <Link
                        :href="offline().url"
                        data-test="pwa-offline-page-link"
                        class="ml-1 font-medium text-primary underline underline-offset-4"
                    >
                        Offline guide
                    </Link>
                </p>

                <p
                    v-else-if="reconnectedMessage"
                    role="status"
                    aria-live="polite"
                    class="text-xs text-muted-foreground"
                >
                    {{ reconnectedMessage }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    id="install-app"
                    type="button"
                    data-test="pwa-install-button"
                    :hidden="!canInstall"
                    :aria-disabled="isInstalling ? 'true' : undefined"
                    class="inline-flex min-h-10 items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-primary-foreground shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    @click="promptInstall"
                >
                    <Download class="size-3.5" />
                    Install app
                </button>
                <p
                    id="install-status"
                    role="status"
                    aria-live="polite"
                    data-test="pwa-install-status"
                    class="text-xs text-muted-foreground"
                >
                    {{ installStatus }}
                </p>
            </div>
        </div>
    </div>
</template>
