<?php

declare(strict_types=1);

namespace Tardis\Commands;

use Illuminate\Console\Command;
use Tardis\Database\Seeders\PermissionSeeder;

/**
 * One-shot installer: migrate, seed the TARDIS permissions and publish the
 * admin assets, then optionally create the first administrator.
 *
 * Every step is idempotent so re-running the command after a failed deploy is
 * safe. The confirmation prompt is skipped under --no-interaction (and with
 * --force) so the command can run unattended in a pipeline.
 */
class TardisInstallCommand extends Command
{
    protected $signature = 'tardis:install
        {--email= : Email of the first administrator to create}
        {--force : Overwrite published files and skip the confirmation}';

    protected $description = 'Install TARDIS: migrate, seed permissions, publish assets and create an administrator';

    public function handle(): int
    {
        if (! $this->option('force') && $this->input->isInteractive() && ! $this->confirm('Run migrations, seed permissions and publish assets?')) {
            $this->components->info('Installation cancelled.');

            return self::SUCCESS;
        }

        $this->components->task('Running migrations', fn (): bool => $this->call('migrate', ['--force' => true]) === 0);

        $this->components->task('Seeding permissions', function (): bool {
            (new PermissionSeeder)->run();

            return true;
        });

        $this->components->task('Publishing assets', fn (): bool => $this->call('vendor:publish', [
            '--tag' => 'tardis-assets',
            '--force' => (bool) $this->option('force'),
        ]) === 0);

        $email = trim((string) $this->option('email'));

        if ($email === '') {
            $this->components->warn('No --email given; skipping administrator creation. Run php artisan tardis:admin <email> --create afterwards.');

            return self::SUCCESS;
        }

        // Reuse the admin command rather than duplicating its role/permission
        // logic, so the two entry points can never drift apart.
        return $this->call('tardis:admin', ['email' => $email, '--create' => true]) === 0
            ? self::SUCCESS
            : self::FAILURE;
    }
}
