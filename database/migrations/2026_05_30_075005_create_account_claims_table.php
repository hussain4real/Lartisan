<?php

use App\Enums\AccountClaimStatus;
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
        Schema::create('account_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('claimed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('status')->default(AccountClaimStatus::Pending->value)->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('claimed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_claims');
    }
};
