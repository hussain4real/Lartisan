<div
    id="filament-pwa-banner"
    data-test="filament-pwa-banner"
    hidden
    class="border-b border-gray-200 bg-amber-50/90 px-4 py-2.5 text-xs text-gray-900 dark:border-white/10 dark:bg-gray-900/95 dark:text-gray-100"
>
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-2">
            <span
                id="filament-network-badge"
                data-test="filament-network-badge"
                class="inline-flex items-center rounded-md bg-amber-100 px-2 py-0.5 font-medium text-amber-900 dark:bg-amber-500/20 dark:text-amber-200"
            >
                Field ready
            </span>
            <span id="filament-network-message">
                Field-visit drafts are saved locally on this device while typing.
            </span>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button
                id="filament-install-app"
                type="button"
                data-test="filament-install-button"
                hidden
                class="inline-flex min-h-9 items-center rounded-md bg-amber-600 px-3 py-1.5 font-medium text-white shadow-xs hover:bg-amber-500 focus-visible:ring-2 focus-visible:ring-amber-600 focus-visible:outline-none"
            >
                Install app
            </button>
            <p
                id="filament-install-status"
                role="status"
                aria-live="polite"
                data-test="filament-install-status"
                class="text-xs text-gray-600 dark:text-gray-300"
            ></p>
        </div>
    </div>
</div>

<script type="module">
    (() => {
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').catch(() => {});
            });
        }

        const banner = document.getElementById('filament-pwa-banner');
        const networkBadge = document.getElementById('filament-network-badge');
        const networkMessage = document.getElementById('filament-network-message');
        const installButton = document.getElementById('filament-install-app');
        const installStatus = document.getElementById('filament-install-status');

        if (!banner || !networkBadge || !networkMessage || !installButton || !installStatus) {
            return;
        }

        const supportsWebInstall = typeof Navigator.prototype.install === 'function';
        let deferredInstallPrompt = null;

        const updateBannerVisibility = () => {
            const isOffline = !navigator.onLine;
            const hasInstallAction = !installButton.hidden || installStatus.textContent.trim() !== '';

            banner.hidden = !isOffline && !hasInstallAction;
            networkBadge.textContent = isOffline ? 'Offline mode' : 'Online';
            networkMessage.textContent = isOffline
                ? 'Connection lost. Any open field-visit form draft is preserved locally on this device.'
                : 'Install Lartisan Operations or resume field-visit drafts.';
        };

        if (supportsWebInstall) {
            installButton.hidden = false;
            updateBannerVisibility();
        }

        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            deferredInstallPrompt = event;

            if (!supportsWebInstall) {
                installButton.hidden = false;
                updateBannerVisibility();
            }
        });

        window.addEventListener('online', updateBannerVisibility);
        window.addEventListener('offline', updateBannerVisibility);
        updateBannerVisibility();

        installButton.addEventListener('click', async () => {
            if (installButton.getAttribute('aria-disabled') === 'true') {
                return;
            }

            installButton.setAttribute('aria-disabled', 'true');

            try {
                if (supportsWebInstall) {
                    await navigator.install();
                    installStatus.textContent = 'The app was installed or opened.';
                    installButton.hidden = true;
                } else if (deferredInstallPrompt) {
                    const promptEvent = deferredInstallPrompt;
                    deferredInstallPrompt = null;
                    await promptEvent.prompt();
                    const { outcome } = await promptEvent.userChoice;
                    installStatus.textContent = outcome === 'accepted'
                        ? 'The app was installed.'
                        : 'Installation canceled.';

                    if (!deferredInstallPrompt) {
                        installButton.hidden = true;
                    }
                }
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    installStatus.textContent = 'Installation was canceled or could not complete.';
                } else {
                    installStatus.textContent = 'Installation could not start.';
                }
            } finally {
                installButton.removeAttribute('aria-disabled');
                updateBannerVisibility();
            }
        });

        const draftKey = 'lartisan:offline-draft:filament-field-visit';
        const trackedFields = ['status', 'territory_id', 'visited_at', 'latitude', 'longitude', 'notes'];

        const readDraft = () => {
            try {
                const raw = window.localStorage.getItem(draftKey);

                return raw ? JSON.parse(raw) : null;
            } catch {
                return null;
            }
        };

        const saveFieldValue = (field, value) => {
            try {
                const existing = readDraft() || { data: {}, savedAt: null, queuedForSync: false };
                existing.data[field] = value;
                existing.savedAt = new Date().toISOString();
                existing.queuedForSync = !navigator.onLine;
                window.localStorage.setItem(draftKey, JSON.stringify(existing));
            } catch {
                // Ignore storage quota issues.
            }
        };

        const resolveFieldName = (element) => {
            const name = element.getAttribute('name') || element.getAttribute('id') || '';

            return trackedFields.find((field) => name.endsWith(field) || name.includes(`.${field}`));
        };

        document.addEventListener('input', (event) => {
            const target = event.target;

            if (!(target instanceof HTMLInputElement || target instanceof HTMLTextAreaElement || target instanceof HTMLSelectElement)) {
                return;
            }

            const field = resolveFieldName(target);

            if (field) {
                saveFieldValue(field, target.value);
            }
        });

        document.addEventListener('change', (event) => {
            const target = event.target;

            if (!(target instanceof HTMLSelectElement || target instanceof HTMLInputElement)) {
                return;
            }

            const field = resolveFieldName(target);

            if (field) {
                saveFieldValue(field, target.value);
            }
        });

        const observer = new MutationObserver(() => {
            const saved = readDraft();

            if (!saved || !saved.data) {
                return;
            }

            document.querySelectorAll('input, textarea, select').forEach((element) => {
                const field = resolveFieldName(element);

                if (field && saved.data[field] !== undefined && element.value === '') {
                    element.value = String(saved.data[field]);
                    element.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
        });

        observer.observe(document.body, { childList: true, subtree: true });
    })();
</script>
