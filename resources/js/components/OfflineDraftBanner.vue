<script setup lang="ts">
import { CloudOff, RotateCcw, Save, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Props = {
    hasDraft: boolean;
    isOnline: boolean;
    queuedForSync: boolean;
    savedAt: string | null;
    statusMessage: string | null;
    label?: string;
};

const props = withDefaults(defineProps<Props>(), {
    label: 'Offline draft support',
});

defineEmits<{
    save: [];
    clear: [];
}>();

const badgeText = computed(() => {
    if (props.queuedForSync) {
        return 'Queued for sync';
    }

    if (props.hasDraft) {
        return 'Draft saved locally';
    }

    if (!props.isOnline) {
        return 'Offline capture';
    }

    return 'Offline ready';
});
</script>

<template>
    <div
        data-test="offline-draft-banner"
        role="status"
        aria-live="polite"
        class="flex flex-col gap-3 rounded-lg border border-border bg-muted/40 p-3.5 text-sm sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="min-w-0 space-y-1">
            <div class="flex flex-wrap items-center gap-2">
                <Badge variant="outline" class="gap-1.5">
                    <CloudOff v-if="!isOnline" class="size-3.5" />
                    <RotateCcw v-else-if="queuedForSync" class="size-3.5" />
                    <Save v-else class="size-3.5" />
                    {{ badgeText }}
                </Badge>
                <span class="font-medium text-foreground">{{ label }}</span>
            </div>

            <p class="text-xs text-muted-foreground">
                {{
                    statusMessage ??
                    'Changes are preserved on this device so intermittent field connections never discard your entry.'
                }}
            </p>
        </div>

        <div class="flex shrink-0 flex-wrap items-center gap-2">
            <Button
                type="button"
                variant="outline"
                size="sm"
                data-test="save-offline-draft"
                class="min-h-10"
                @click="$emit('save')"
            >
                <Save />
                Save draft
            </Button>
            <Button
                v-if="hasDraft"
                type="button"
                variant="ghost"
                size="sm"
                data-test="clear-offline-draft"
                class="min-h-10"
                @click="$emit('clear')"
            >
                <Trash2 />
                Clear draft
            </Button>
        </div>
    </div>
</template>
