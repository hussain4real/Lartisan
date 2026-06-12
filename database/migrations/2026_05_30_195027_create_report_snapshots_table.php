<?php

use App\Enums\ReportSnapshotScope;
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
        Schema::create('report_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope')->default(ReportSnapshotScope::Global->value)->index();
            $table->nullableMorphs('scopeable');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->json('metrics');
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->index(['scope', 'generated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_snapshots');
    }
};
