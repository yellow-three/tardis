<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tardis\Bread\ActivityLogger;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\BreadManager;
use Tardis\Media\MediaManager;
use Tardis\Models\ActivityLog;
use Tardis\Settings\SettingsManager;

class FullAuditUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}

beforeEach(function () {
    Schema::dropIfExists('users');
    Schema::create('users', function ($table) {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });
    config()->set('auth.providers.users.model', FullAuditUser::class);
    $this->artisan('migrate');

    $this->admin = FullAuditUser::create(['name' => 'Ada', 'email' => 'ada@example.test', 'password' => Hash::make('secret-pass')]);
    $this->actingAs($this->admin);
});

function fullAuditEntry(string $type, string $action): ?ActivityLog
{
    return ActivityLog::query()->where('model_type', $type)->where('action', $action)->latest('id')->first();
}

test('changing a setting logs the old and new value and hides password values', function () {
    $settings = app(SettingsManager::class);
    $settings->create(['group' => 'site', 'key' => 'title', 'type' => 'text', 'value' => 'Old']);
    $settings->create(['group' => 'mail', 'key' => 'token', 'type' => 'password', 'value' => 'hunter2']);

    expect(fullAuditEntry('tardis.setting', 'created'))->not->toBeNull()
        ->user_id->toBe($this->admin->id);

    $settings->update(['site.title' => 'New', 'mail.token' => 'swordfish']);

    $entry = fullAuditEntry('tardis.setting', 'updated');

    expect($entry->old_values)->toEqual(['site.title' => 'Old', 'mail.token' => SettingsManager::REDACTED])
        ->and($entry->new_values)->toEqual(['site.title' => 'New', 'mail.token' => SettingsManager::REDACTED])
        ->and(json_encode(ActivityLog::all()->toArray()))->not->toContain('hunter2')->not->toContain('swordfish');

    $settings->delete('site.title');

    expect(fullAuditEntry('tardis.setting', 'deleted'))->old_values->toMatchArray(['value' => 'New']);
});

test('saving settings with no change writes no entry', function () {
    $settings = app(SettingsManager::class);
    $settings->create(['group' => 'site', 'key' => 'title', 'type' => 'text', 'value' => 'Same']);

    $settings->update(['site.title' => 'Same']);

    expect(fullAuditEntry('tardis.setting', 'updated'))->toBeNull();
});

test('schema changes on the database screen are logged', function () {
    Livewire::test('tardis::pages.database.create')
        ->set('newTableName', 'audited_things')
        ->set('newTableColumns', [['name' => 'title', 'type' => 'string', 'length' => '', 'nullable' => false, 'default' => '', 'primary' => false, 'key' => '']])
        ->call('createTable');

    expect(Schema::hasTable('audited_things'))->toBeTrue()
        ->and(fullAuditEntry('tardis.schema', 'table_created'))->new_values->toMatchArray(['table' => 'audited_things']);
});

test('permission, media and BREAD definition changes are logged', function () {
    Livewire::test('tardis::pages.permissions')
        ->set('newName', 'Export reports')
        ->set('newSlug', 'export_reports')
        ->call('createPermission');

    expect(fullAuditEntry('tardis.permission', 'created'))->new_values->toBe(['slug' => 'export_reports']);

    Storage::fake('public');
    $media = app(MediaManager::class);
    $media->upload(UploadedFile::fake()->create('report.pdf', 10, 'application/pdf'));
    $media->rename('report.pdf', 'annual.pdf');
    $media->deleteFile('annual.pdf');

    expect(fullAuditEntry('tardis.media', 'uploaded'))->not->toBeNull()
        ->and(fullAuditEntry('tardis.media', 'renamed'))->not->toBeNull()
        ->and(fullAuditEntry('tardis.media', 'deleted'))->new_values->toMatchArray(['path' => 'media/annual.pdf']);

    $breads = app(BreadManager::class);
    $breads->save(BreadDefinition::fromArray(['slug' => 'audited', 'model' => FullAuditUser::class, 'name' => 'Audited', 'name_plural' => 'Audited', 'table' => 'users']));
    $breads->delete('audited');

    expect(fullAuditEntry('tardis.bread_definition', 'created'))->new_values->toMatchArray(['slug' => 'audited'])
        ->and(fullAuditEntry('tardis.bread_definition', 'removed'))->new_values->toBe(['slug' => 'audited']);
});

test('the activity log page filters by kind', function () {
    app(ActivityLogger::class)->audit('tardis.plugin', 'enabled', ['plugin' => 'x']);
    app(ActivityLogger::class)->audit('tardis.media', 'uploaded', ['path' => 'media/a.pdf']);

    Livewire::test('tardis::pages.activity-log')
        ->assertSet('types', fn (array $types) => in_array('tardis.media', $types, true))
        ->set('filterType', 'tardis.media')
        ->assertSet('totalRows', 1);
});

test('the prune command removes entries older than the retention period', function () {
    $old = ActivityLog::create(['model_type' => 'tardis.auth', 'model_id' => '0', 'action' => 'signed_in']);
    $old->forceFill(['created_at' => now()->subDays(40)])->save();
    $recent = ActivityLog::create(['model_type' => 'tardis.auth', 'model_id' => '0', 'action' => 'signed_in']);

    config()->set('tardis.activity_log.retention_days', 30);

    $this->artisan('tardis:activity:prune', ['--pretend' => true])->assertSuccessful();
    expect(ActivityLog::query()->count())->toBe(2);

    $this->artisan('tardis:activity:prune')->assertSuccessful()->expectsOutputToContain('Removed 1');

    expect(ActivityLog::query()->pluck('id')->all())->toBe([$recent->id]);
});

test('the prune command keeps everything without a retention period and rejects a bad one', function () {
    ActivityLog::create(['model_type' => 'tardis.auth', 'model_id' => '0', 'action' => 'signed_in'])
        ->forceFill(['created_at' => now()->subYears(3)])->save();

    config()->set('tardis.activity_log.retention_days', null);

    $this->artisan('tardis:activity:prune')->assertSuccessful();
    $this->artisan('tardis:activity:prune', ['--days' => '0'])->assertFailed();

    expect(ActivityLog::query()->count())->toBe(1);
});
