<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Tardis\Support\UserKey;

/**
 * The user columns were bigints, which a UUID or ULID user table cannot use.
 * They now follow tardis.users.key_type ("auto": the user model). On an
 * integer-keyed app this changes nothing. A table that already has rows of the
 * other type is left as it is; `php artisan tardis:user-keys --force` empties
 * and converts it.
 */
return new class extends Migration
{
    public function up(): void
    {
        UserKey::apply();
    }

    public function down(): void
    {
        // Not reversed: a UUID cannot go back into a bigint column.
    }
};
