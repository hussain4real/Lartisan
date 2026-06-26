<?php

namespace Database\Factories;

use App\Enums\RestoreTestStatus;
use App\Models\RestoreTestRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestoreTestRecord>
 */
class RestoreTestRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'verified_by' => null,
            'status' => RestoreTestStatus::Passed,
            'backup_disk' => 'local',
            'backup_path' => 'Lartisan/test-backup.zip',
            'database_verified' => true,
            'media_verified' => true,
            'notes' => 'Factory restore test.',
            'metadata' => ['source' => 'factory'],
            'tested_at' => now(),
        ];
    }
}
