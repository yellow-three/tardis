<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A fresh install has no user foreign keys; upgrades from older releases do.
 * Build the old shape on the test connection so the upgrade is exercised.
 */
test('the user foreign key upgrade drops SQLite keys without losing rows or columns', function () {
    $migration = __DIR__.'/../../database/migrations/2026_11_19_000002_drop_user_foreign_keys.php';

    try {
        $this->artisan('migrate');

        foreach (['tardis_role_user', 'tardis_activity_logs'] as $table) {
            Schema::connection('testing')->dropIfExists($table);
        }

        if (! Schema::connection('testing')->hasTable('users')) {
            Schema::connection('testing')->create('users', function (Blueprint $table) {
                $table->id();
            });
        }

        Schema::connection('testing')->create('tardis_role_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->foreign('user_id')->references('id')->on('users');
        });

        Schema::connection('testing')->create('tardis_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('action');
            $table->string('model_type');
            $table->foreign('user_id')->references('id')->on('users');
        });

        DB::connection('testing')->table('users')->insert(['id' => 7]);
        DB::connection('testing')->table('tardis_role_user')->insert(['user_id' => 7, 'role_id' => 1]);
        DB::connection('testing')->table('tardis_activity_logs')->insert(['user_id' => 7, 'action' => 'created', 'model_type' => 'Post']);

        foreach (['tardis_role_user', 'tardis_activity_logs'] as $table) {
            expect(collect(Schema::connection('testing')->getForeignKeys($table))->pluck('columns')->all())->toContain(['user_id']);
        }

        (require $migration)->up();

        foreach (['tardis_role_user', 'tardis_activity_logs'] as $table) {
            expect(collect(Schema::connection('testing')->getForeignKeys($table))->pluck('columns')->all())->not->toContain(['user_id'])
                ->and(Schema::connection('testing')->hasColumn($table, 'user_id'))->toBeTrue()
                ->and(DB::connection('testing')->table($table)->where('user_id', 7)->exists())->toBeTrue();
        }

        // Running the upgrade again on a schema that no longer has the tables
        // is harmless (and is what a fresh install sees).
        Schema::connection('testing')->dropIfExists('tardis_role_user');
        Schema::connection('testing')->dropIfExists('tardis_activity_logs');

        (require $migration)->up();

        expect(Schema::connection('testing')->hasTable('tardis_role_user'))->toBeFalse()
            ->and(Schema::connection('testing')->hasTable('tardis_activity_logs'))->toBeFalse();
    } finally {
        // Leave the normal package schema for any test sharing this database.
        Schema::connection('testing')->dropAllTables();
        $this->artisan('migrate');
    }
});
