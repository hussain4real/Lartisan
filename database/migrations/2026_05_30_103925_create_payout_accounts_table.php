<?php

use App\Enums\PaymentProviderName;
use App\Enums\PayoutAccountStatus;
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
        Schema::create('payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artisan_profile_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default(PaymentProviderName::Paystack->value)->index();
            $table->string('bank_code');
            $table->string('bank_name');
            $table->text('account_number');
            $table->string('account_name');
            $table->string('recipient_code')->nullable()->index();
            $table->string('status')->default(PayoutAccountStatus::Pending->value)->index();
            $table->timestamp('verified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['artisan_profile_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payout_accounts');
    }
};
