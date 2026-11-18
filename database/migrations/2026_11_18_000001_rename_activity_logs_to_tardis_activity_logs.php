<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Every package table carries the tardis_ prefix except the activity log,
 * whose generic name could clash with a host's own table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_logs') && ! Schema::hasTable('tardis_activity_logs')) {
            Schema::rename('activity_logs', 'tardis_activity_logs');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tardis_activity_logs') && ! Schema::hasTable('activity_logs')) {
            Schema::rename('tardis_activity_logs', 'activity_logs');
        }
    }
};
