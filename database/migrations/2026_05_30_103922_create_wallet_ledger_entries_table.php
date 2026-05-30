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
        Schema::create('wallet_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            $table->string('direction')->index();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('available_balance_after');
            $table->unsignedBigInteger('pending_balance_after');
            $table->nullableMorphs('source');
            $table->string('immutable_reference')->unique();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('posted_at');
            $table->timestamps();

            $table->index(['wallet_id', 'posted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_ledger_entries');
    }
};
