<?php

use App\Enums\PaymentProviderName;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artisan_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default(PaymentProviderName::Paystack->value)->index();
            $table->string('purpose')->default(PaymentPurpose::Subscription->value)->index();
            $table->string('status')->default(PaymentStatus::Pending->value)->index();
            $table->string('reference')->unique();
            $table->string('provider_reference')->nullable()->index();
            $table->unsignedBigInteger('amount');
            $table->char('currency_code', 3)->default('NGN');
            $table->text('checkout_url')->nullable();
            $table->string('access_code')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['artisan_profile_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
