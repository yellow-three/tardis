<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tardis\Support\UserKey;

/**
 * Two-factor secrets live next to the user table rather than in it: the
 * users table belongs to the application.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tardis_two_factor', function (Blueprint $table) {
            $table->id();
            UserKey::column($table, 'user_id')->unique();
            $table->text('secret');
            $table->text('recovery_codes')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('last_used_step')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tardis_two_factor');
    }
};
