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
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('booking_id')->nullable()->after('artisan_profile_id')->constrained()->nullOnDelete();
            $table->unsignedInteger('commission_basis_points')->nullable()->after('currency_code');
            $table->unsignedBigInteger('commission_amount')->nullable()->after('commission_basis_points');
            $table->unsignedInteger('provider_fee_basis_points')->nullable()->after('commission_amount');
            $table->unsignedBigInteger('provider_fee_flat_amount')->nullable()->after('provider_fee_basis_points');
            $table->unsignedBigInteger('provider_fee_amount')->nullable()->after('provider_fee_flat_amount');
            $table->unsignedBigInteger('net_amount')->nullable()->after('provider_fee_amount');

            $table->index(['booking_id', 'status'], 'payments_booking_status_idx');
            $table->index(['purpose', 'status'], 'payments_purpose_status_idx');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('payment_started_at')->nullable()->after('wallet_released_at');
            $table->timestamp('paid_at')->nullable()->after('payment_started_at');
            $table->timestamp('escrowed_at')->nullable()->after('paid_at');
            $table->timestamp('settled_at')->nullable()->after('escrowed_at');
            $table->timestamp('refunded_at')->nullable()->after('settled_at');
            $table->timestamp('reviewed_at')->nullable()->after('refunded_at');

            $table->index(['status', 'paid_at'], 'bookings_status_paid_idx');
            $table->index(['status', 'settled_at'], 'bookings_status_settled_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_status_settled_idx');
            $table->dropIndex('bookings_status_paid_idx');
            $table->dropColumn([
                'payment_started_at',
                'paid_at',
                'escrowed_at',
                'settled_at',
                'refunded_at',
                'reviewed_at',
            ]);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_purpose_status_idx');
            $table->dropIndex('payments_booking_status_idx');
            $table->dropConstrainedForeignId('booking_id');
            $table->dropColumn([
                'commission_basis_points',
                'commission_amount',
                'provider_fee_basis_points',
                'provider_fee_flat_amount',
                'provider_fee_amount',
                'net_amount',
            ]);
        });
    }
};
