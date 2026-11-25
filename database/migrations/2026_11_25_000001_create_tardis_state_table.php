<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Runtime state for tardis.state.driver = database: one row per document
 * (settings, menus, themes, plugin switches, BREAD definitions, ...). Created
 * with the file driver too, so switching needs no extra migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        // A test run on the database driver creates it before migrating.
        if (Schema::hasTable('tardis_state')) {
            return;
        }

        Schema::create('tardis_state', function (Blueprint $table) {
            $table->id();
            $table->string('key', 191)->unique();
            $table->longText('value');
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tardis_state');
    }
};
