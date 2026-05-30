<?php

namespace Database\Seeders;

use App\Actions\Setup\SeedPilotUsers;
use Illuminate\Database\Seeder;

class PilotUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(SeedPilotUsers::class)->handle();
    }
}
