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
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->string('channel');
            $table->string('status')->default('pending');
            $table->nullableMorphs('recipient');
            $table->string('recipient_name')->nullable();
            $table->string('recipient_address');
            $table->nullableMorphs('source');
            $table->string('subject');
            $table->text('body');
            $table->string('provider')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('provider_status')->nullable();
            $table->string('dedupe_key')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('dead_lettered_at')->nullable();
            $table->timestamp('callback_received_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'status']);
            $table->index(['channel', 'status']);
            $table->index(['provider', 'provider_message_id']);
            $table->index(['dedupe_key', 'channel', 'recipient_address']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
