<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tardis\Models\ActivityLog;

test('the activity log table carries the tardis_ prefix, rows kept', function () {
    $this->artisan('migrate', ['--path' => realpath(__DIR__.'/../../database/migrations/2026_06_14_000003_create_activity_logs_table.php'), '--realpath' => true]);
    DB::table('activity_logs')->insert(['action' => 'created', 'model_type' => 'App\\Models\\Post', 'model_id' => 1, 'created_at' => now(), 'updated_at' => now()]);

    $this->artisan('migrate');

    expect(Schema::hasTable('activity_logs'))->toBeFalse()
        ->and(Schema::hasTable(ActivityLog::TABLE))->toBeTrue()
        ->and(ActivityLog::query()->value('action'))->toBe('created');
});

test('the role and activity tables carry no foreign key to users', function () {
    $this->artisan('migrate');

    foreach (['tardis_role_user', 'tardis_activity_logs'] as $table) {
        expect(collect(Schema::getForeignKeys($table))->pluck('columns')->all())->not->toContain(['user_id']);
    }
});
