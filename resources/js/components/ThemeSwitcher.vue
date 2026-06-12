<script setup lang="ts">
import { Monitor, Moon, Sun } from 'lucide-vue-next';
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { isAppearance, useAppearance } from '@/composables/useAppearance';
import { cn } from '@/lib/utils';
import type { Appearance } from '@/types';

type ThemeSwitcherVariant = 'dropdown' | 'segmented';

type Props = {
    variant?: ThemeSwitcherVariant;
    align?: 'start' | 'center' | 'end';
    side?: 'top' | 'right' | 'bottom' | 'left';
    class?: HTMLAttributes['class'];
};

const props = withDefaults(defineProps<Props>(), {
    variant: 'dropdown',
    align: 'end',
    side: 'bottom',
});

const { appearance, updateAppearance } = useAppearance();

const options = [
    { value: 'light', Icon: Sun, label: 'Light' },
    { value: 'dark', Icon: Moon, label: 'Dark' },
    { value: 'system', Icon: Monitor, label: 'System' },
] as const;

const currentOption = computed(() => {
    return (
        options.find((option) => option.value === appearance.value) ??
        options[2]
    );
});

function selectAppearance(value: Appearance): void {
    updateAppearance(value);
}

function handleDropdownUpdate(value: unknown): void {
    if (isAppearance(value)) {
        updateAppearance(value);
    }
}
</script>

<template>
    <div
        v-if="variant === 'segmented'"
        :class="cn('inline-flex gap-1 rounded-lg bg-muted p-1', props.class)"
    >
        <button
            v-for="{ value, Icon, label } in options"
            :key="value"
            type="button"
            @click="selectAppearance(value)"
            :class="[
                'inline-flex h-8 items-center gap-1.5 rounded-md px-3 text-sm font-medium transition-colors',
                appearance === value
                    ? 'bg-background text-foreground shadow-xs'
                    : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
            ]"
        >
            <component :is="Icon" class="size-4" />
            <span>{{ label }}</span>
        </button>
    </div>

    <DropdownMenu v-else>
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                :class="cn('size-9', props.class)"
                :aria-label="`Theme: ${currentOption.label}`"
            >
                <component :is="currentOption.Icon" class="size-4" />
                <span class="sr-only">Change theme</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent :align="align" :side="side" class="w-40">
            <DropdownMenuRadioGroup
                :model-value="appearance"
                @update:model-value="handleDropdownUpdate"
            >
                <DropdownMenuRadioItem
                    v-for="{ value, Icon, label } in options"
                    :key="value"
                    :value="value"
                    class="gap-2"
                >
                    <component :is="Icon" class="size-4" />
                    <span>{{ label }}</span>
                </DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
