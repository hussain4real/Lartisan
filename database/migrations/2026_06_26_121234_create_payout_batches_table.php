<?php

use App\Enums\PayoutBatchStatus;
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
        Schema::create('payout_batches', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default(PayoutBatchStatus::Pending->value)->index();
            $table->timestamp('scheduled_for')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('total_payouts')->default(0);
            $table->unsignedInteger('successful_payouts')->default(0);
            $table->unsignedInteger('failed_payouts')->default(0);
            $table->unsignedInteger('action_required_payouts')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->char('currency_code', 3)->default('NGN');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_for'], 'lartisan_payout_batches_status_schedule_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payout_batches');
    }
};
