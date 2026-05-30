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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('artisan_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artisan_service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('requested')->index();
            $table->string('customer_name');
            $table->string('customer_phone', 32);
            $table->string('customer_email')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('quoted_amount')->nullable();
            $table->char('currency_code', 3)->default('NGN');
            $table->json('address_snapshot');
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('state_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('local_government_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('territory_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tracker_code', 32)->unique();
            $table->string('secure_token_hash', 64)->unique();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('wallet_released_at')->nullable();
            $table->timestamps();

            $table->index(['artisan_profile_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index(['service_category_id', 'status']);
            $table->index(['country_id', 'state_id', 'local_government_id', 'territory_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
