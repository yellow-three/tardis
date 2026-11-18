<?php

declare(strict_types=1);
use Tardis\Support\LegacyNames;

// Loaded by Composer (autoload.files): resolves the class names Tardis used
// before 0.2 to their new classes on demand. See Tardis\Support\LegacyNames.
spl_autoload_register([LegacyNames::class, 'autoload']);
