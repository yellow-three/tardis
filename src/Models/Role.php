<?php

declare(strict_types=1);

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
        return $this->belongsToMany(ModelResolver::permission(), 'tardis_permission_role', 'role_id', 'permission_id');
    }

    public function users(): BelongsToMany
    {
        // Named keys: Eloquent would derive the pivot column from the model's
        // class name, so a user model not called User looked for a column
        // such as admin_id that tardis_role_user does not have.
        return $this->belongsToMany(config('auth.providers.users.model', 'App\\Models\\User'), 'tardis_role_user', 'role_id', 'user_id');
    }
}
