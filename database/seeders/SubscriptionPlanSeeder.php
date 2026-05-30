<?php

namespace Database\Seeders;

use App\Actions\Setup\SeedSubscriptionPlans;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(SeedSubscriptionPlans::class)->handle();
    }
}
