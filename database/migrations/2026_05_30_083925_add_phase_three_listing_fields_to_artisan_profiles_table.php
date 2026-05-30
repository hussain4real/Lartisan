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
            $table->text('public_summary')->nullable()->after('business_name');
            $table->unsignedSmallInteger('years_experience')->nullable()->after('public_summary');
            $table->unsignedSmallInteger('service_radius_km')->nullable()->after('years_experience');
            $table->string('public_phone', 32)->nullable()->after('service_radius_km');
            $table->string('public_email')->nullable()->after('public_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('artisan_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'public_summary',
                'years_experience',
                'service_radius_km',
                'public_phone',
                'public_email',
            ]);
        });
    }
};
