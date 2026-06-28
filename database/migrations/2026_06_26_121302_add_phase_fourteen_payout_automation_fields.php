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
        Schema::table('payout_accounts', function (Blueprint $table) {
            $table->timestamp('verification_checked_at')->nullable()->after('verified_at');
            $table->timestamp('recipient_registered_at')->nullable()->after('verification_checked_at');
            $table->string('verification_provider_status')->nullable()->after('recipient_registered_at')->index();
            $table->text('verification_failure_reason')->nullable()->after('verification_provider_status');
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->foreignId('payout_batch_id')->nullable()->after('wallet_id')->constrained('payout_batches')->nullOnDelete();
            $table->string('provider_status')->nullable()->after('provider_transfer_code')->index();
            $table->timestamp('reconciled_at')->nullable()->after('provider_status')->index();
            $table->timestamp('next_retry_at')->nullable()->after('reconciled_at')->index();

            $table->unique('provider_reference', 'lartisan_payouts_provider_reference_unique');
            $table->unique('provider_transfer_code', 'lartisan_payouts_provider_transfer_code_unique');
            $table->index(['payout_batch_id', 'status'], 'lartisan_payouts_batch_status_idx');
        });

        Schema::table('payout_attempts', function (Blueprint $table) {
            $table->string('provider_transfer_code')->nullable()->after('provider_reference')->index();
            $table->string('provider_status')->nullable()->after('provider_transfer_code')->index();
            $table->timestamp('last_reconciled_at')->nullable()->after('provider_payload');

            $table->unique('provider_reference', 'lartisan_payout_attempts_provider_reference_unique');
            $table->unique('provider_transfer_code', 'lartisan_payout_attempts_provider_transfer_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payout_attempts', function (Blueprint $table) {
            $table->dropUnique('lartisan_payout_attempts_provider_transfer_code_unique');
            $table->dropUnique('lartisan_payout_attempts_provider_reference_unique');
            $table->dropColumn([
                'provider_transfer_code',
                'provider_status',
                'last_reconciled_at',
            ]);
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->dropIndex('lartisan_payouts_batch_status_idx');
            $table->dropUnique('lartisan_payouts_provider_transfer_code_unique');
            $table->dropUnique('lartisan_payouts_provider_reference_unique');
            $table->dropConstrainedForeignId('payout_batch_id');
            $table->dropColumn([
                'provider_status',
                'reconciled_at',
                'next_retry_at',
            ]);
        });

        Schema::table('payout_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'verification_checked_at',
                'recipient_registered_at',
                'verification_provider_status',
                'verification_failure_reason',
            ]);
        });
    }
};
