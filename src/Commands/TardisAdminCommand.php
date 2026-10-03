<?php

declare(strict_types=1);

namespace Tardis\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tardis\Database\Seeders\PermissionSeeder;
use Tardis\Models\Role;

class TardisAdminCommand extends Command
{
    protected $signature = 'tardis:admin
        {email : Email of the user to make an administrator}
        {--create : Create the user when it does not exist}
        {--password= : Password for a newly created user (generated when omitted)}';

    protected $description = 'Give a user the super-admin role (and optionally create the user)';

    public function handle(): int
    {
        $userModel = (string) config('auth.providers.users.model');

        if (! class_exists($userModel)) {
            $this->components->error("The user model [{$userModel}] configured in auth.providers.users.model does not exist.");

            return self::FAILURE;
        }

        $email = (string) $this->argument('email');
        $user = $userModel::query()->where('email', $email)->first();

        if ($user === null) {
            if (! $this->option('create')) {
                $this->components->error("No user with email [{$email}]. Pass --create to create one.");

                return self::FAILURE;
            }

            $given = $this->option('password');
            $password = $given ? (string) $given : Str::password(16);

            $user = $userModel::query()->create([
                'name' => Str::headline(Str::before($email, '@')),
                'email' => $email,
                'password' => Hash::make($password),
            ]);

            $this->components->info('User created.');

            if (! $given) {
                $this->components->twoColumnDetail('Generated password', $password);
            }
        }

        // The role and every permission must exist before the user can be
        // granted anything, so provision them (idempotent) first.
        (new PermissionSeeder)->run();

        $slug = (string) (config('tardis.authorization.super_admin_roles')[0] ?? 'super-admin');
        $role = Role::where('slug', $slug)->firstOrFail();

        $alreadyAssigned = DB::table('tardis_role_user')
            ->where('role_id', $role->id)
            ->where('user_id', $user->getKey())
            ->exists();

        if (! $alreadyAssigned) {
            DB::table('tardis_role_user')->insert([
                'role_id' => $role->id,
                'user_id' => $user->getKey(),
            ]);
        }

        $this->components->info("[{$email}] is a super administrator.");

        return self::SUCCESS;
    }
}
