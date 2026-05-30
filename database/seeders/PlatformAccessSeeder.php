<?php

namespace Database\Seeders;

use App\Actions\Setup\SeedPlatformAccess;
use App\Actions\Setup\SeedReasonCodes;
use Illuminate\Database\Seeder;

class PlatformAccessSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(SeedPlatformAccess::class)->handle();
        app(SeedReasonCodes::class)->handle();
    }
}
