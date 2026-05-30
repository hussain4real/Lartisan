<?php

use App\Enums\PaymentProviderName;
use App\Enums\ProviderWebhookEventStatus;
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
        Schema::create('provider_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default(PaymentProviderName::Paystack->value);
            $table->string('event');
            $table->string('provider_event_id')->nullable();
            $table->string('reference')->nullable();
            $table->json('payload');
            $table->string('signature')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->string('status')->default(ProviderWebhookEventStatus::Pending->value)->index();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_event_id']);
            $table->unique(['provider', 'event', 'reference']);
            $table->index(['provider', 'reference']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_webhook_events');
    }
};
