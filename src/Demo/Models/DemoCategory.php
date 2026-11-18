<?php

declare(strict_types=1);

namespace Tardis\Demo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Sample content installed by `tardis:install --demo`. */
class DemoCategory extends Model
{
    protected $table = 'tardis_demo_categories';

    protected $fillable = ['name', 'slug'];

    public function posts(): HasMany
    {
        return $this->hasMany(DemoPost::class, 'category_id');
    }
}
