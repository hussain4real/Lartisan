<?php

namespace Database\Seeders;

use App\Actions\Setup\SeedPlatformAccess;
use Illuminate\Database\Seeder;

class PlatformAccessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(SeedPlatformAccess::class)->handle();
    }
}
