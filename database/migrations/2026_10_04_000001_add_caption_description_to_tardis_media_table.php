<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tardis_media', function (Blueprint $table) {
            if (! Schema::hasColumn('tardis_media', 'caption')) {
                $table->string('caption')->nullable()->after('alt_text');
            }
            if (! Schema::hasColumn('tardis_media', 'description')) {
                $table->text('description')->nullable()->after('caption');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tardis_media', function (Blueprint $table) {
            if (Schema::hasColumn('tardis_media', 'caption')) {
                $table->dropColumn('caption');
            }
            if (Schema::hasColumn('tardis_media', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
