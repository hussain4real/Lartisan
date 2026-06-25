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
            $table->index(
                ['is_public', 'verification_status', 'subscription_status', 'availability_status', 'state_id'],
                'lartisan_artisan_profiles_marketplace_idx',
            );
            $table->index(
                ['local_government_id', 'territory_id', 'is_public'],
                'lartisan_artisan_profiles_location_idx',
            );
        });

        Schema::table('artisan_services', function (Blueprint $table) {
            $table->index(
                ['service_category_id', 'status', 'artisan_profile_id'],
                'lartisan_artisan_services_marketplace_idx',
            );
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(
                ['status', 'ends_at', 'artisan_profile_id'],
                'lartisan_subscriptions_active_lookup_idx',
            );
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->index(
                ['status', 'scheduled_at'],
                'lartisan_bookings_status_schedule_idx',
            );
            $table->index(
                ['local_government_id', 'status', 'created_at'],
                'lartisan_bookings_lga_status_idx',
            );
        });

        Schema::table('kyc_submissions', function (Blueprint $table) {
            $table->index(
                ['status', 'risk_level', 'submitted_at'],
                'lartisan_kyc_queue_idx',
            );
        });

        Schema::table('disputes', function (Blueprint $table) {
            $table->index(
                ['status', 'severity', 'opened_at'],
                'lartisan_disputes_queue_idx',
            );
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->index(
                ['status', 'requested_at'],
                'lartisan_payouts_queue_idx',
            );
        });

        Schema::table('provider_webhook_events', function (Blueprint $table) {
            $table->index(
                ['provider', 'status', 'received_at'],
                'lartisan_webhook_events_processing_idx',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_webhook_events', function (Blueprint $table) {
            $table->dropIndex('lartisan_webhook_events_processing_idx');
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->dropIndex('lartisan_payouts_queue_idx');
        });

        Schema::table('disputes', function (Blueprint $table) {
            $table->dropIndex('lartisan_disputes_queue_idx');
        });

        Schema::table('kyc_submissions', function (Blueprint $table) {
            $table->dropIndex('lartisan_kyc_queue_idx');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('lartisan_bookings_lga_status_idx');
            $table->dropIndex('lartisan_bookings_status_schedule_idx');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('lartisan_subscriptions_active_lookup_idx');
        });

        Schema::table('artisan_services', function (Blueprint $table) {
            $table->dropIndex('lartisan_artisan_services_marketplace_idx');
        });

        Schema::table('artisan_profiles', function (Blueprint $table) {
            $table->dropIndex('lartisan_artisan_profiles_location_idx');
            $table->dropIndex('lartisan_artisan_profiles_marketplace_idx');
        });
    }
};
