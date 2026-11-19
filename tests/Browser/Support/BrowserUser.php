<?php

declare(strict_types=1);

namespace Tests\Browser\Support;

use Illuminate\Foundation\Auth\User as Authenticatable;

/** The signed-in user of the browser tests. */
class BrowserUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}
