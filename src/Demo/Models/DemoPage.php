<?php

declare(strict_types=1);

namespace Tardis\Demo\Models;

use Illuminate\Database\Eloquent\Model;

/** Sample content installed by `tardis:install --demo`. */
class DemoPage extends Model
{
    protected $table = 'tardis_demo_pages';

    protected $fillable = ['title', 'slug', 'body', 'status', 'position'];
}
