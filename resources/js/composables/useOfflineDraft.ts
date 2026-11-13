import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

export const OFFLINE_DRAFT_PREFIX = 'lartisan:offline-draft:';

export type StoredOfflineDraft<T> = {
    data: T;
    savedAt: string;
    queuedForSync: boolean;
};

export type OfflineDraftSummary = {
    key: string;
    savedAt: string | null;
    queuedForSync: boolean;
};

function readStoredDraft<T>(storageKey: string): StoredOfflineDraft<T> | null {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const raw = window.localStorage.getItem(
            `${OFFLINE_DRAFT_PREFIX}${storageKey}`,
        );

        if (!raw) {
            return null;
        }

        const parsed = JSON.parse(raw) as StoredOfflineDraft<T>;

        if (!parsed || typeof parsed !== 'object' || !parsed.data) {
            return null;
        }

        return parsed;
    } catch {
        return null;
    }
}

export function listSavedOfflineDrafts(): OfflineDraftSummary[] {
    if (typeof window === 'undefined') {
        return [];
    }

    const drafts: OfflineDraftSummary[] = [];

    try {
        for (let index = 0; index < window.localStorage.length; index += 1) {
            const fullKey = window.localStorage.key(index);

            if (!fullKey || !fullKey.startsWith(OFFLINE_DRAFT_PREFIX)) {
                continue;
            }

            const shortKey = fullKey.slice(OFFLINE_DRAFT_PREFIX.length);
            const stored = readStoredDraft<Record<string, unknown>>(shortKey);

            drafts.push({
                key: shortKey,
                savedAt: stored?.savedAt ?? null,
                queuedForSync: Boolean(stored?.queuedForSync),
            });
        }
    } catch {
        return [];
    }

    return drafts;
}

export function useOfflineDraft<T extends Record<string, unknown>>(
    storageKey: string,
    initialValues: T,
) {
    const stored = readStoredDraft<T>(storageKey);

    const draft = ref<T>({
        ...initialValues,
        ...(stored?.data ?? {}),
    });
    const savedAt = ref<string | null>(stored?.savedAt ?? null);
    const queuedForSync = ref<boolean>(Boolean(stored?.queuedForSync));
    const statusMessage = ref<string | null>(
        stored
            ? stored.queuedForSync
                ? 'Saved offline draft queued for sync. Review and submit when online.'
                : 'Restored saved draft from this device.'
            : null,
    );
    const isOnline = ref<boolean>(
        typeof navigator === 'undefined' ? true : navigator.onLine,
    );
    const isSyncingReset = ref(false);

    const hasDraft = computed(() => savedAt.value !== null);

    const writeDraft = (
        queueForSync: boolean,
        message: string | null = null,
    ): void => {
        if (typeof window === 'undefined') {
            return;
        }

        const timestamp = new Date().toISOString();
        const payload: StoredOfflineDraft<T> = {
            data: draft.value,
            savedAt: timestamp,
            queuedForSync: queueForSync,
        };

        try {
            window.localStorage.setItem(
                `${OFFLINE_DRAFT_PREFIX}${storageKey}`,
                JSON.stringify(payload),
            );
            savedAt.value = timestamp;
            queuedForSync.value = queueForSync;

            if (message !== null) {
                statusMessage.value = message;
            }
        } catch {
            statusMessage.value =
                'Unable to save draft locally on this device.';
        }
    };

    const saveDraft = (
        message = 'Draft saved locally on this device.',
    ): void => {
        writeDraft(!isOnline.value || queuedForSync.value, message);
    };

    const clearDraft = (message: string | null = null): void => {
        isSyncingReset.value = true;
        draft.value = { ...initialValues };
        savedAt.value = null;
        queuedForSync.value = false;
        statusMessage.value = message;

        if (typeof window !== 'undefined') {
            try {
                window.localStorage.removeItem(
                    `${OFFLINE_DRAFT_PREFIX}${storageKey}`,
                );
            } catch {
                // Ignore storage removal errors.
            }
        }

        isSyncingReset.value = false;
    };

    const handleOfflineSubmit = (event: Event): boolean => {
        if (isOnline.value) {
            return false;
        }

        event.preventDefault();
        event.stopPropagation();

        writeDraft(
            true,
            'Offline mode: Draft saved on this device and queued to sync when your connection returns.',
        );

        return true;
    };

    const handleOnline = (): void => {
        isOnline.value = true;

        if (queuedForSync.value) {
            statusMessage.value =
                'Connection restored. Review your saved draft and submit now.';
        }
    };

    const handleOffline = (): void => {
        isOnline.value = false;
    };

    watch(
        draft,
        () => {
            if (isSyncingReset.value) {
                return;
            }

            writeDraft(queuedForSync.value);
        },
        { deep: true },
    );

    onMounted(() => {
        if (typeof window === 'undefined') {
            return;
        }

        isOnline.value = navigator.onLine;
        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);
    });

    onBeforeUnmount(() => {
        if (typeof window === 'undefined') {
            return;
        }

        window.removeEventListener('online', handleOnline);
        window.removeEventListener('offline', handleOffline);
    });

    return {
        draft,
        savedAt,
        queuedForSync,
        statusMessage,
        isOnline,
        hasDraft,
        saveDraft,
        clearDraft,
        handleOfflineSubmit,
    };
}
