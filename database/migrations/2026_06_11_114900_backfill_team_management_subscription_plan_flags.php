<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('subscription_plans')
            ->whereIn('slug', ['growth-listing', 'annual-partner'])
            ->update(['includes_team_management' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('subscription_plans')
            ->whereIn('slug', ['growth-listing', 'annual-partner'])
            ->update(['includes_team_management' => false]);
    }
};
