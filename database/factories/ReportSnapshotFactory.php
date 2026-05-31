<?php

namespace Database\Factories;

use App\Enums\ReportSnapshotScope;
use App\Models\ReportSnapshot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportSnapshot>
 */
class ReportSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'generated_by' => User::factory(),
            'scope' => ReportSnapshotScope::Global,
            'scopeable_type' => null,
            'scopeable_id' => null,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'metrics' => [
                'bookings_total' => 0,
                'confirmed_bookings' => 0,
                'open_disputes' => 0,
                'pending_payouts' => 0,
            ],
            'generated_at' => now(),
        ];
    }
}
