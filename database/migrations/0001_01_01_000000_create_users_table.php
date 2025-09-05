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
        Schema::create('users', function (Blueprint $table) {
            $table->engine('InnoDB');

            $table->id();
            $table->string('email', 128)->unique();
            $table->string('mobile')->nullable();
            $table->string('password', 256);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('mobile_verified_at')->nullable();
            $table->enum('status', [
                \App\UserStatusEnum::ACTIVE->value,
                \App\UserStatusEnum::INACTIVE->value,
                \App\UserStatusEnum::SUSPENDED->value,
                \App\UserStatusEnum::PENDING->value,
            ])->default(\App\UserStatusEnum::PENDING->value);
            $table->enum('role', [
                \App\Enums\UseRoleEnum::CUSTOMER->value,
                \App\Enums\UseRoleEnum::ADMIN->value,
                \App\Enums\UseRoleEnum::SUPERADMIN->value,
                \App\Enums\UseRoleEnum::SERVICE_PROVIDER->value,
            ])->default(\App\Enums\UseRoleEnum::CUSTOMER->value);
            $table->json('metadata')->nullable();
            $table->enum('two_factor_auth', [
                \App\Enums\TwoFactorAuthEnum::DISABLED->value,
                \App\Enums\TwoFactorAuthEnum::MOBILE->value,
                \App\Enums\TwoFactorAuthEnum::EMAIL->value,
            ])->default(\App\Enums\TwoFactorAuthEnum::DISABLED->value);
            $table->softDeletes();
            $table->timestamps();

            $table->index('email');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
