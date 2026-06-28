<x-filament-panels::page>
    @php
        $snapshot = $this->latestSnapshot();
        $restoreTest = $this->latestRestoreTest();
        $checks = $this->checks();
        $statusClass = match ($snapshot?->status?->value) {
            'passing' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/20',
            'failing' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-400/20',
            default => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/20',
        };
    @endphp

    <div class="space-y-6">
        <section class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Operational status</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        {{ $snapshot ? data_get($snapshot->summary, 'message') : 'No health snapshot has been collected yet.' }}
                    </p>
                </div>

                @if ($snapshot)
                    <span class="inline-flex w-fit items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $statusClass }}">
                        {{ ucfirst($snapshot->status->value) }}
                    </span>
                @endif
            </div>

            @if ($snapshot)
                <dl class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Generated</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $snapshot->generated_at->diffForHumans() }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Failed jobs</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ number_format($snapshot->queue_failed_jobs_count) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Pending jobs</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ number_format($snapshot->queue_pending_jobs_count) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Scheduler heartbeat</dt>
                        <dd class="mt-1 text-sm font-medium text-gray-950 dark:text-white">
                            {{ $snapshot->scheduler_last_seen_at?->diffForHumans() ?? 'Missing' }}
                        </dd>
                    </div>
                </dl>
            @endif
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            @forelse ($checks as $name => $check)
                @php
                    $checkStatus = data_get($check, 'status', 'warning');
                    $checkClass = match ($checkStatus) {
                        'passing' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/20',
                        'failing' => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-400/20',
                        default => 'bg-amber-50 text-amber-700 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/20',
                    };
                @endphp

                <article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ str($name)->replace('_', ' ')->title() }}</h3>
                        <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $checkClass }}">
                            {{ ucfirst($checkStatus) }}
                        </span>
                    </div>
                    <p class="mt-3 text-sm text-gray-700 dark:text-gray-300">{{ data_get($check, 'message') }}</p>

                    @if (data_get($check, 'guidance'))
                        <p class="mt-3 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ data_get($check, 'guidance') }}</p>
                    @endif
                </article>
            @empty
                <article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Checks</h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Collect a snapshot to populate provider, queue, storage, scheduler, backup, restore-test, Nightwatch, log, and retention checks.</p>
                </article>
            @endforelse
        </section>

        <section class="grid gap-4 lg:grid-cols-3">
            <article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Queue recovery</h3>
                <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                    Failed: {{ number_format($snapshot?->queue_failed_jobs_count ?? 0) }} · Pending: {{ number_format($snapshot?->queue_pending_jobs_count ?? 0) }}
                </p>
                <p class="mt-3 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ data_get($checks, 'queue.guidance', 'Use queue:failed and queue:retry with exact failed-job UUIDs after inspecting logs.') }}</p>
            </article>

            <article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Backup recovery</h3>
                <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                    Latest backup: {{ $snapshot?->backup_last_successful_at?->diffForHumans() ?? 'Not confirmed' }}
                </p>
                <p class="mt-3 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ data_get($checks, 'backup.guidance', 'Run backup:run, backup:monitor, and backup:clean on the production schedule.') }}</p>
            </article>

            <article class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Restore tests</h3>
                <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                    {{ $restoreTest ? ucfirst($restoreTest->status->value).' '.$restoreTest->tested_at->diffForHumans() : 'No restore test recorded' }}
                </p>
                <p class="mt-3 text-xs leading-5 text-gray-500 dark:text-gray-400">{{ data_get($checks, 'restore_test.guidance', 'Record database and critical media verification after each recovery rehearsal.') }}</p>
            </article>
        </section>
    </div>
</x-filament-panels::page>
