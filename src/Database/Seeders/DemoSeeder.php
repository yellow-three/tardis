<?php

declare(strict_types=1);

namespace Tardis\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tardis\Auth\Abilities;
use Tardis\Support\ModelResolver;

/**
 * Optional demo data seeder for local/testing environments.
 *
 * This seeder is opt-in and safe by design:
 * - Only runs in local or testing environments.
 * - Does not truncate or delete existing data.
 * - Uses updateOrCreate/firstOrCreate with stable natural keys.
 * - Seeds through package models and abilities (no raw permission slugs invented).
 * - Outputs clearly marked demo credentials.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Safety: only run in local/testing environments
        if (! in_array(app()->environment(), ['local', 'testing'], true)) {
            return;
        }

        // Opt-in via config: system.demo.enabled must be true
        if (! config('tardis.system.demo.enabled', false)) {
            return;
        }

        // Ensure permissions exist (idempotent)
        $this->seedPermissions();

        // Create demo roles
        $adminRole = ModelResolver::role()::firstOrCreate(
            ['slug' => 'demo-admin'],
            ['name' => 'Demo Admin']
        );

        $editorRole = ModelResolver::role()::firstOrCreate(
            ['slug' => 'demo-editor'],
            ['name' => 'Demo Editor']
        );

        // Assign permissions to roles
        $adminPermissions = ModelResolver::permission()::query()->pluck('id');
        $adminRole->permissions()->sync($adminPermissions);

        $editorPermissions = ModelResolver::permission()::whereIn('slug', [
            Abilities::ACCESS,
            'browse admin',
            Abilities::MEDIA_BROWSE,
            'read media',
        ])->pluck('id');
        $editorRole->permissions()->sync($editorPermissions);

        // Create demo users with hashed passwords
        $adminUser = $this->getUserModel()::updateOrCreate(
            ['email' => 'demo-admin@example.test'],
            [
                'name' => 'Demo Admin',
                'password' => Hash::make('demo-admin-password'),
            ]
        );

        $editorUser = $this->getUserModel()::updateOrCreate(
            ['email' => 'demo-editor@example.test'],
            [
                'name' => 'Demo Editor',
                'password' => Hash::make('demo-editor-password'),
            ]
        );

        // Assign roles (sync to avoid duplicates) - use pivot table directly
        $userModel = $this->getUserModel();
        $userKey = (new $userModel)->getKeyName();

        // Ensure we don't duplicate
        DB::table('tardis_role_user')->updateOrInsert(
            ['role_id' => $adminRole->id, 'user_id' => $adminUser->$userKey],
            []
        );
        DB::table('tardis_role_user')->updateOrInsert(
            ['role_id' => $editorRole->id, 'user_id' => $editorUser->$userKey],
            []
        );

        // Output demo credentials clearly
        $this->command?->info('Demo data seeded successfully.');
        $this->command?->info('Demo Admin: demo-admin@example.test / demo-admin-password');
        $this->command?->info('Demo Editor: demo-editor@example.test / demo-editor-password');
    }

    /**
     * Seed core permissions using PermissionSeeder conventions.
     */
    protected function seedPermissions(): void
    {
        $seeder = new PermissionSeeder;
        $seeder->run();
    }

    /**
     * Get the user model class from auth config.
     */
    protected function getUserModel(): string
    {
        return config('auth.providers.users.model', 'App\\Models\\User');
    }
}
