<?php

use App\Enums\DisputeSeverity;
use App\Enums\DisputeStatus;
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
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('review_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('artisan_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('opened_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default(DisputeStatus::Open->value)->index();
            $table->string('severity')->default(DisputeSeverity::Medium->value)->index();
            $table->string('subject');
            $table->text('description')->nullable();
            $table->text('escalation_reason')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['artisan_profile_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index(['opened_by_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
