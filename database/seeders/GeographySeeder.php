<?php

namespace Database\Seeders;

use App\Actions\Setup\SeedGeography;
use Illuminate\Database\Seeder;

class GeographySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(SeedGeography::class)->handle();
    }
}
