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
        Schema::create('admin_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->nullableMorphs('scope');
            $table->string('status')->default('active');
            $table->foreignId('appointed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('appointed_at')->nullable();
            $table->timestamps();

            $table->index(['role', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_profiles');
    }
};
