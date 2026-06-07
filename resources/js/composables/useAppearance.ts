import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

const appearances = ['light', 'dark', 'system'] as const;
const systemPrefersDark = ref(false);

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
};

export function isAppearance(value: unknown): value is Appearance {
    return (
        typeof value === 'string' && appearances.includes(value as Appearance)
    );
}

export function normalizeAppearance(value: unknown): Appearance {
    return isAppearance(value) ? value : 'system';
}

const resolveAppearance = (value: Appearance): ResolvedAppearance => {
    if (value === 'system') {
        return systemPrefersDark.value ? 'dark' : 'light';
    }

    return value;
};

export function updateTheme(value: Appearance): void {
    if (typeof window === 'undefined') {
        return;
    }

    const resolvedAppearance = resolveAppearance(normalizeAppearance(value));

    document.documentElement.classList.toggle(
        'dark',
        resolvedAppearance === 'dark',
    );
    document.documentElement.style.colorScheme = resolvedAppearance;
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const mediaQuery = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const getStoredAppearance = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    let storedAppearance: string | null = null;

    try {
        storedAppearance = localStorage.getItem('appearance');
    } catch {
        return null;
    }

    if (storedAppearance === null) {
        return null;
    }

    if (!isAppearance(storedAppearance)) {
        try {
            localStorage.removeItem('appearance');
        } catch {
            return null;
        }

        return null;
    }

    return storedAppearance;
};

const getCookieAppearance = () => {
    if (typeof document === 'undefined') {
        return null;
    }

    const appearanceCookie = document.cookie
        .split(';')
        .map((cookie) => cookie.trim())
        .find((cookie) => cookie.startsWith('appearance='));

    if (!appearanceCookie) {
        return null;
    }

    try {
        const value = decodeURIComponent(
            appearanceCookie.substring('appearance='.length),
        );

        return isAppearance(value) ? value : null;
    } catch {
        return null;
    }
};

const getPersistedAppearance = (): Appearance => {
    return getStoredAppearance() ?? getCookieAppearance() ?? 'system';
};

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const handleSystemThemeChange = () => {
    systemPrefersDark.value = prefersDark();
    updateTheme(getPersistedAppearance());
};

let mediaQueryListenerInitialized = false;

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    const initialAppearance = getPersistedAppearance();

    systemPrefersDark.value = prefersDark();
    appearance.value = initialAppearance;
    updateTheme(initialAppearance);

    if (!mediaQueryListenerInitialized) {
        mediaQuery()?.addEventListener('change', handleSystemThemeChange);
        mediaQueryListenerInitialized = true;
    }
}

const appearance = ref<Appearance>('system');

export function useAppearance(): UseAppearanceReturn {
    onMounted(() => {
        const savedAppearance = getPersistedAppearance();

        systemPrefersDark.value = prefersDark();

        appearance.value = savedAppearance;

        updateTheme(appearance.value);
    });

    const resolvedAppearance = computed<ResolvedAppearance>(() => {
        return resolveAppearance(appearance.value);
    });

    function updateAppearance(value: Appearance) {
        const nextAppearance = normalizeAppearance(value);

        appearance.value = nextAppearance;

        try {
            localStorage.setItem('appearance', nextAppearance);
        } catch {
            // Cookie persistence still works when localStorage is unavailable.
        }

        setCookie('appearance', nextAppearance);

        updateTheme(nextAppearance);
    }

    return {
        appearance,
        resolvedAppearance,
        updateAppearance,
    };
}
