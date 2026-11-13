import { onBeforeUnmount, onMounted, ref } from 'vue';

type BeforeInstallPromptEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{
        outcome: 'accepted' | 'dismissed';
        platform: string;
    }>;
};

type NavigatorWithInstall = Navigator & {
    install?: () => Promise<void>;
};

let serviceWorkerRegistered = false;

export function registerServiceWorker(): void {
    if (
        serviceWorkerRegistered ||
        typeof window === 'undefined' ||
        !('serviceWorker' in navigator)
    ) {
        return;
    }

    serviceWorkerRegistered = true;

    const register = (): void => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    };

    if (document.readyState === 'complete') {
        register();

        return;
    }

    window.addEventListener('load', register, { once: true });
}

export function usePwa() {
    const isOnline = ref<boolean>(
        typeof navigator === 'undefined' ? true : navigator.onLine,
    );
    const reconnectedMessage = ref<string | null>(null);
    const installStatus = ref<string>('');
    const isInstalling = ref<boolean>(false);

    const supportsWebInstall =
        typeof Navigator !== 'undefined' &&
        typeof (Navigator.prototype as unknown as NavigatorWithInstall)
            .install === 'function';

    const canInstall = ref<boolean>(supportsWebInstall);
    let deferredInstallPrompt: BeforeInstallPromptEvent | null = null;

    const handleBeforeInstallPrompt = (event: Event): void => {
        event.preventDefault();
        deferredInstallPrompt = event as BeforeInstallPromptEvent;

        if (!supportsWebInstall) {
            canInstall.value = true;
        }
    };

    const handleAppInstalled = (): void => {
        deferredInstallPrompt = null;
        canInstall.value = false;
        installStatus.value = 'The app was installed.';
    };

    const handleOnline = (): void => {
        isOnline.value = true;
        reconnectedMessage.value =
            'Back online. Saved field-visit and artisan drafts are ready to submit.';
    };

    const handleOffline = (): void => {
        isOnline.value = false;
        reconnectedMessage.value = null;
    };

    const promptInstall = async (event: MouseEvent): Promise<void> => {
        const button = event.currentTarget as HTMLButtonElement | null;

        if (
            button?.getAttribute('aria-disabled') === 'true' ||
            isInstalling.value
        ) {
            return;
        }

        isInstalling.value = true;
        button?.setAttribute('aria-disabled', 'true');

        try {
            if (supportsWebInstall) {
                const installableNavigator = navigator as NavigatorWithInstall;

                if (installableNavigator.install) {
                    await installableNavigator.install();
                    installStatus.value = 'The app was installed or opened.';
                    canInstall.value = false;
                }
            } else if (deferredInstallPrompt) {
                const promptEvent = deferredInstallPrompt;
                deferredInstallPrompt = null;

                await promptEvent.prompt();
                const { outcome } = await promptEvent.userChoice;

                installStatus.value =
                    outcome === 'accepted'
                        ? 'The app was installed.'
                        : 'Installation canceled.';

                if (!deferredInstallPrompt) {
                    canInstall.value = false;
                }
            }
        } catch (error) {
            if (error instanceof Error && error.name === 'AbortError') {
                installStatus.value =
                    'Installation was canceled or could not complete.';
            } else {
                installStatus.value = 'Installation could not start.';
            }
        } finally {
            isInstalling.value = false;
            button?.removeAttribute('aria-disabled');
        }
    };

    onMounted(() => {
        if (typeof window === 'undefined') {
            return;
        }

        isOnline.value = navigator.onLine;
        window.addEventListener(
            'beforeinstallprompt',
            handleBeforeInstallPrompt,
        );
        window.addEventListener('appinstalled', handleAppInstalled);
        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);
    });

    onBeforeUnmount(() => {
        if (typeof window === 'undefined') {
            return;
        }

        window.removeEventListener(
            'beforeinstallprompt',
            handleBeforeInstallPrompt,
        );
        window.removeEventListener('appinstalled', handleAppInstalled);
        window.removeEventListener('online', handleOnline);
        window.removeEventListener('offline', handleOffline);
    });

    return {
        isOnline,
        reconnectedMessage,
        canInstall,
        installStatus,
        isInstalling,
        promptInstall,
    };
}
