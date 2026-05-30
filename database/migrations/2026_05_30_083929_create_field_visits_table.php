<?php

use App\Enums\FieldVisitStatus;
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
        Schema::create('field_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kyc_submission_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('artisan_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('area_agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('territory_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default(FieldVisitStatus::Scheduled->value)->index();
            $table->timestamp('visited_at')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $table->json('checklist')->nullable();
            $table->timestamps();

            $table->index(['artisan_profile_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('field_visits');
    }
};
