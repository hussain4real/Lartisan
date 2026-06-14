<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder as QueryBuilder;
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
        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->foreignId('state_id')->nullable()->change();
            $table->foreignId('local_government_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $entriesMissingLocalGeography = DB::table('waitlist_entries')
            ->whereNull('state_id')
            ->orWhereNull('local_government_id')
            ->exists();

        $stateId = DB::table('states')
            ->where('slug', 'federal-capital-territory')
            ->value('id');
        $localGovernmentId = DB::table('local_governments')
            ->where('slug', 'abuja-municipal-area-council')
            ->value('id');
        $countryId = DB::table('countries')
            ->where('iso_code', 'NG')
            ->value('id');

        if ($entriesMissingLocalGeography && ($countryId === null || $stateId === null || $localGovernmentId === null)) {
            throw new RuntimeException('Cannot rollback nullable waitlist geography without seeded Nigeria geography.');
        }

        if ($stateId !== null && $localGovernmentId !== null) {
            DB::table('waitlist_entries')
                ->where(function (QueryBuilder $query): void {
                    $query->whereNull('state_id')
                        ->orWhereNull('local_government_id');
                })
                ->update([
                    'country_id' => $countryId,
                    'state_id' => $stateId,
                    'local_government_id' => $localGovernmentId,
                ]);
        }

        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->foreignId('state_id')->nullable(false)->change();
            $table->foreignId('local_government_id')->nullable(false)->change();
        });
    }
};
