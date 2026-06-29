<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('artisan_profiles', function (Blueprint $table) {
            $table->decimal('marketplace_latitude', 10, 7)->nullable()->after('territory_id');
            $table->decimal('marketplace_longitude', 10, 7)->nullable()->after('marketplace_latitude');
            $table->timestamp('marketplace_coordinates_verified_at')->nullable()->after('marketplace_longitude');

            $table->index(
                ['marketplace_latitude', 'marketplace_longitude'],
                'lartisan_artisan_profiles_marketplace_coordinates_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('artisan_profiles', function (Blueprint $table) {
            $table->dropIndex('lartisan_artisan_profiles_marketplace_coordinates_idx');
            $table->dropColumn([
                'marketplace_latitude',
                'marketplace_longitude',
                'marketplace_coordinates_verified_at',
            ]);
        });
    }
};
