<?php

declare(strict_types=1);

namespace Tardis\Commands;

use Illuminate\Console\Command;
use Tardis\Demo\DemoContent;

class DemoCommand extends Command
{
    protected $signature = 'tardis:demo
        {--remove : Take the demo content away again}
        {--force : Run outside the local and testing environments}';

    protected $description = 'Add sample categories, posts, pages and a site menu to the panel (or remove them)';

    public function handle(DemoContent $demo): int
    {
        if (! $this->option('force') && ! app()->environment(['local', 'testing'])) {
            $this->components->error('The demo content is meant for local installs. Pass --force to run it in the ['.app()->environment().'] environment.');

            return self::FAILURE;
        }

        $steps = $this->option('remove') ? $demo->remove() : $demo->install();

        foreach ($steps as $step) {
            $this->components->bulletList([$step]);
        }

        if (! $this->option('remove')) {
            $this->components->info('Demo content ready. Remove it with php artisan tardis:demo --remove.');
        }

        return self::SUCCESS;
    }
}
