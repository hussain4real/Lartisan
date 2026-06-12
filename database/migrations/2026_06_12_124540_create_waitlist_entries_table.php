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
        Schema::create('waitlist_entries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 64);
            $table->string('audience_type')->index();
            $table->string('business_name')->nullable();
            $table->foreignId('service_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->foreignId('state_id')->constrained()->restrictOnDelete();
            $table->foreignId('local_government_id')->constrained()->restrictOnDelete();
            $table->foreignId('territory_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note')->nullable();
            $table->boolean('contact_consent')->default(false);
            $table->timestamps();

            $table->index(['audience_type', 'state_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
