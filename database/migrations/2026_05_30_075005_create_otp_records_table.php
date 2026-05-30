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
        Schema::create('otp_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone_country_code', 8)->nullable();
            $table->string('phone_number', 32)->nullable();
            $table->string('phone_e164', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('purpose');
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('last_sent_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['phone_e164', 'purpose', 'consumed_at']);
            $table->index(['email', 'purpose']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp_records');
    }
};
