<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tardis\Support\UserKey;

/**
 * The panel's notification centre. Kept apart from Laravel's notifications
 * table, which the host may not have (or uses for something else).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tardis_notifications', function (Blueprint $table) {
            $table->id();
            UserKey::column($table, 'user_id')->index();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('level', 16)->default('info');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tardis_notifications');
    }
};
