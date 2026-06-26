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
        Schema::create('system_health_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->index();
            $table->json('summary');
            $table->json('checks');
            $table->unsignedInteger('queue_failed_jobs_count')->default(0);
            $table->unsignedInteger('queue_pending_jobs_count')->default(0);
            $table->timestamp('oldest_failed_job_at')->nullable();
            $table->timestamp('scheduler_last_seen_at')->nullable();
            $table->timestamp('backup_last_successful_at')->nullable();
            $table->timestamp('generated_at')->index();
            $table->timestamps();

            $table->index(['status', 'generated_at'], 'system_health_snapshots_status_generated_idx');
        });

        Schema::create('restore_test_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->index();
            $table->string('backup_disk')->nullable();
            $table->string('backup_path')->nullable();
            $table->boolean('database_verified')->default(false);
            $table->boolean('media_verified')->default(false);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('tested_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restore_test_records');
        Schema::dropIfExists('system_health_snapshots');
    }
};
