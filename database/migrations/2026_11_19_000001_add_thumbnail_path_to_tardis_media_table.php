<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where the media screen's thumbnail of an image is stored (null: none, the
 * original is shown).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tardis_media', 'thumbnail_path')) {
            Schema::table('tardis_media', function (Blueprint $table) {
                $table->string('thumbnail_path')->nullable()->after('path');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tardis_media', 'thumbnail_path')) {
            Schema::table('tardis_media', function (Blueprint $table) {
                $table->dropColumn('thumbnail_path');
            });
        }
    }
};
