<?php

use App\Enums\UserStatus;
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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_country_code', 8)->nullable()->after('email_verified_at');
            $table->string('phone_number', 32)->nullable()->after('phone_country_code');
            $table->string('phone_e164', 32)->nullable()->unique()->after('phone_number');
            $table->timestamp('phone_verified_at')->nullable()->after('phone_e164');
            $table->string('status')->default(UserStatus::Active->value)->index()->after('password');
            $table->string('preferred_channel')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone_e164']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'phone_country_code',
                'phone_number',
                'phone_e164',
                'phone_verified_at',
                'status',
                'preferred_channel',
            ]);
        });
    }
};
