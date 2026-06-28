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
        Schema::table('reviews', function (Blueprint $table): void {
            $table->foreignId('artisan_response_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('artisan_response')->nullable();
            $table->timestamp('artisan_responded_at')->nullable();
            $table->string('moderation_signal')->nullable();
            $table->unsignedSmallInteger('moderation_score')->default(0);
            $table->json('moderation_metadata')->nullable();

            $table->index('moderation_signal', 'reviews_moderation_signal_idx');
            $table->index(['status', 'moderation_score'], 'reviews_status_moderation_score_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropForeign(['artisan_response_by']);
            $table->dropIndex('reviews_moderation_signal_idx');
            $table->dropIndex('reviews_status_moderation_score_idx');
            $table->dropColumn([
                'artisan_response_by',
                'artisan_response',
                'artisan_responded_at',
                'moderation_signal',
                'moderation_score',
                'moderation_metadata',
            ]);
        });
    }
};
