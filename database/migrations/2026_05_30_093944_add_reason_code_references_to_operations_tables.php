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
        Schema::table('kyc_submissions', function (Blueprint $table) {
            $table->foreignId('reason_code_id')
                ->nullable()
                ->after('decision_reason')
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('area_agent_assignments', function (Blueprint $table) {
            $table->foreignId('reason_code_id')
                ->nullable()
                ->after('reason')
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('artisan_profiles', function (Blueprint $table) {
            $table->foreignId('suspension_reason_code_id')
                ->nullable()
                ->after('internal_notes')
                ->constrained('reason_codes')
                ->nullOnDelete();
            $table->foreignId('suspended_by')
                ->nullable()
                ->after('suspension_reason_code_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('suspended_at')->nullable()->after('suspended_by');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('reason_code_id')
                ->nullable()
                ->after('reason')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reason_code_id');
        });

        Schema::table('artisan_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suspension_reason_code_id');
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropColumn('suspended_at');
        });

        Schema::table('area_agent_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reason_code_id');
        });

        Schema::table('kyc_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reason_code_id');
        });
    }
};
