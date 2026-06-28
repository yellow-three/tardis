<?php

namespace Tardis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = ['name', 'slug', 'group'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'tardis_permission_role');
    }

    public static function forBread(string $slug): void
    {
        $actions = ['browse', 'read', 'edit', 'add', 'delete'];

        foreach ($actions as $action) {
            static::firstOrCreate(
                ['slug' => "{$action} {$slug}"],
                ['name' => ucfirst($action).' '.str_replace('-', ' ', $slug), 'group' => 'BREAD']
            );
        }
    }
}
