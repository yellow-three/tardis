<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Earlier installs created foreign keys from tardis_role_user.user_id and
 * tardis_activity_logs.user_id to users.id. The panel's user model may use
 * another table, so they are dropped; the columns keep their index.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['tardis_role_user', 'tardis_activity_logs'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (Schema::getForeignKeys($table) as $foreignKey) {
                if ($foreignKey['columns'] === ['user_id']) {
                    Schema::table($table, function (Blueprint $blueprint) use ($foreignKey) {
                        $blueprint->dropForeign($foreignKey['name']);
                    });
                }
            }
        }
    }

    public function down(): void
    {
        // The keys are not recreated: the user table may not be "users".
    }
};
