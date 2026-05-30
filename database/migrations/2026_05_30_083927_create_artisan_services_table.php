<?php

use App\Enums\ArtisanServiceStatus;
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
        Schema::create('artisan_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artisan_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_category_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('starting_price', 12, 2)->nullable();
            $table->char('currency_code', 3)->default('NGN');
            $table->string('status')->default(ArtisanServiceStatus::Draft->value)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['artisan_profile_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artisan_services');
    }
};
