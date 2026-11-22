<?php

declare(strict_types=1);

namespace Tardis\Commands;

use Illuminate\Console\Command;
use Tardis\Support\UserKey;

class UserKeysCommand extends Command
{
    protected $signature = 'tardis:user-keys
        {--force : Empty a table that still holds keys of the old type, then convert it}';

    protected $description = 'Give the role, activity log and media user columns the type of your users table key';

    public function handle(): int
    {
        $type = UserKey::type();
        $result = UserKey::apply((bool) $this->option('force'));

        foreach ($result['changed'] as $column) {
            $this->components->info("{$column} is now {$type}.");
        }

        foreach ($result['skipped'] as $column) {
            $this->components->warn("{$column} has rows of the old type; run again with --force to empty and convert it.");
        }

        if ($result['changed'] === [] && $result['skipped'] === []) {
            $this->components->info("The user columns already match the [{$type}] key.");
        }

        return $result['skipped'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
