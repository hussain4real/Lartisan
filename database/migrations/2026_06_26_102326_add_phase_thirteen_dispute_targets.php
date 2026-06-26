<?php

use App\Enums\DisputeTargetType;
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
        Schema::table('disputes', function (Blueprint $table): void {
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('target')->default(DisputeTargetType::Booking->value);
            $table->unsignedBigInteger('money_adjustment_amount')->nullable();
            $table->string('money_adjustment_direction')->nullable();
            $table->foreignId('money_adjustment_ledger_entry_id')->nullable()->constrained('wallet_ledger_entries')->nullOnDelete();
            $table->timestamp('money_adjusted_at')->nullable();

            $table->index(['target', 'status'], 'disputes_target_status_idx');
            $table->index(['payment_id', 'status'], 'disputes_payment_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table): void {
            $table->dropForeign(['payment_id']);
            $table->dropForeign(['money_adjustment_ledger_entry_id']);
            $table->dropIndex('disputes_target_status_idx');
            $table->dropIndex('disputes_payment_status_idx');
            $table->dropColumn([
                'payment_id',
                'target',
                'money_adjustment_amount',
                'money_adjustment_direction',
                'money_adjustment_ledger_entry_id',
                'money_adjusted_at',
            ]);
        });
    }
};
