<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('kind')->default('workspace')->after('slug')->index();
        });

        DB::table('teams')
            ->where('is_personal', true)
            ->update(['kind' => 'personal']);

        if (Schema::hasTable('artisan_profiles')) {
            $artisanTeamIds = DB::table('artisan_profiles')
                ->pluck('team_id')
                ->all();

            if ($artisanTeamIds !== []) {
                DB::table('teams')
                    ->whereIn('id', $artisanTeamIds)
                    ->update(['kind' => 'artisan-business']);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
