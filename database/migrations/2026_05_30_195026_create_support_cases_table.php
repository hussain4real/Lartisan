<?php

use App\Enums\SupportCaseCategory;
use App\Enums\SupportCasePriority;
use App\Enums\SupportCaseStatus;
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
        Schema::create('support_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('supportable');
            $table->string('category')->default(SupportCaseCategory::General->value)->index();
            $table->string('priority')->default(SupportCasePriority::Normal->value)->index();
            $table->string('status')->default(SupportCaseStatus::Open->value)->index();
            $table->string('subject');
            $table->text('description')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'status']);
            $table->index(['requester_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_cases');
    }
};
