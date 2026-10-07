<?php

namespace Tardis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Tardis\Support\ModelResolver;

class Role extends Model
{
    protected $table = 'tardis_roles';

    protected $fillable = ['name', 'slug'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(ModelResolver::permission(), 'tardis_permission_role');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(config('auth.providers.users.model', 'App\\Models\\User'), 'tardis_role_user');
    }
}
