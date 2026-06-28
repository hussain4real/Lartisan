<?php

namespace Database\Factories;

use App\Enums\OperationalHealthStatus;
use App\Models\SystemHealthSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SystemHealthSnapshot>
 */
class SystemHealthSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'generated_by' => null,
            'status' => OperationalHealthStatus::Passing,
            'summary' => ['message' => 'All monitored systems are passing.'],
            'checks' => [
                'database' => ['status' => OperationalHealthStatus::Passing->value, 'message' => 'Database reachable.'],
            ],
            'queue_failed_jobs_count' => 0,
            'queue_pending_jobs_count' => 0,
            'oldest_failed_job_at' => null,
            'scheduler_last_seen_at' => now(),
            'backup_last_successful_at' => now(),
            'generated_at' => now(),
        ];
    }
}
