<?php

declare(strict_types=1);

namespace Tardis\Support;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The type of the columns that hold a user's key: tardis_role_user.user_id,
 * tardis_activity_logs.user_id and tardis_media.created_by.
 *
 * tardis.users.key_type picks it (int, uuid, ulid or string); "auto", the
 * default, reads it off the panel's user model: HasUuids is uuid, HasUlids is
 * ulid, any other non-integer key is string, the rest int. The columns must
 * match the users table, or a UUID user can hold no role (and on PostgreSQL a
 * join between a uuid and a bigint fails outright).
 */
final class UserKey
{
    public const TYPES = ['int', 'uuid', 'ulid', 'string'];

    /** table => [column, nullable] */
    public const COLUMNS = [
        'tardis_role_user' => ['user_id', false],
        'tardis_activity_logs' => ['user_id', true],
        'tardis_media' => ['created_by', true],
    ];

    public static function type(): string
    {
        $configured = strtolower((string) config('tardis.users.key_type', 'auto'));

        return in_array($configured, self::TYPES, true) ? $configured : self::fromModel();
    }

    /** The type the user model implies, whatever the config says. */
    public static function fromModel(): string
    {
        $class = config('auth.providers.users.model');

        if (! is_string($class) || ! is_subclass_of($class, Model::class)) {
            return 'int';
        }

        $traits = class_uses_recursive($class);

        if (in_array(HasUuids::class, $traits, true)) {
            return 'uuid';
        }

        if (in_array(HasUlids::class, $traits, true)) {
            return 'ulid';
        }

        return (new $class)->getKeyType() === 'int' ? 'int' : 'string';
    }

    public static function column(Blueprint $table, string $name, ?string $type = null): ColumnDefinition
    {
        return match ($type ?? self::type()) {
            'uuid' => $table->uuid($name),
            'ulid' => $table->ulid($name),
            'string' => $table->string($name, 64),
            default => $table->unsignedBigInteger($name),
        };
    }

    /**
     * Change the columns to the current type where they differ. Returns the
     * "table.column" names it changed. Rows written with the old type cannot be
     * kept when it changes between numbers and UUIDs, so a table that is not
     * empty is left alone unless $force is set, in which case it is emptied.
     *
     * @return array{changed: list<string>, skipped: list<string>}
     */
    public static function apply(bool $force = false): array
    {
        $type = self::type();
        $changed = [];
        $skipped = [];

        foreach (self::COLUMNS as $table => [$column, $nullable]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || self::matches($table, $column, $type)) {
                continue;
            }

            if (DB::table($table)->exists()) {
                if (! $force) {
                    $skipped[] = $table.'.'.$column;

                    continue;
                }

                DB::table($table)->delete();
            }

            // PostgreSQL will not cast bigint to uuid (or back) on its own;
            // the table is empty here, so the cast through text never runs on
            // a value. Other drivers ignore `using`.
            $target = ['uuid' => 'uuid', 'ulid' => 'char(26)', 'string' => 'varchar(64)'][$type] ?? 'bigint';

            Schema::table($table, function (Blueprint $blueprint) use ($column, $nullable, $type, $target) {
                self::column($blueprint, $column, $type)->nullable($nullable)
                    ->using('"'.$column.'"::text::'.$target)
                    ->change();
            });

            $changed[] = $table.'.'.$column;
        }

        return ['changed' => $changed, 'skipped' => $skipped];
    }

    /** Whether a column already has the type (compared by kind, not exact SQL). */
    public static function matches(string $table, string $column, ?string $type = null): bool
    {
        $actual = strtolower(Schema::getColumnType($table, $column));
        $isNumber = in_array($actual, ['bigint', 'integer', 'int', 'int8', 'int4'], true);

        return ($type ?? self::type()) === 'int' ? $isNumber : ! $isNumber;
    }
}
