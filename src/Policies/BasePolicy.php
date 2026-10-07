<?php

namespace Tardis\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;
use Tardis\Auth\BreadAuthorization;

/**
 * Base for a host application's Eloquent policies.
 *
 * Every check goes through the same BreadAuthorization the BREAD pages use, so
 * a policy and the admin panel always agree on who may do what: "browse posts"
 * means the same thing in `@can('browse', Post::class)` and on /admin/posts,
 * whether the answer comes from the Tardis roles or from the host's own
 * `hasPermissionTo()`.
 *
 * The BREAD slug is derived from the policy class (PostPolicy -> posts); set
 * `$slug` to name it explicitly.
 */
class BasePolicy
{
    use HandlesAuthorization;

    protected ?string $slug = null;

    public function browseAny(Authenticatable $user): bool
    {
        return $this->allows('browse');
    }

    public function browse(Authenticatable $user, $model): bool
    {
        return $this->allows('browse');
    }

    public function read(Authenticatable $user, $model): bool
    {
        return $this->allows('read');
    }

    public function edit(Authenticatable $user, $model): bool
    {
        return $this->allows('edit');
    }

    public function add(Authenticatable $user): bool
    {
        return $this->allows('add');
    }

    public function delete(Authenticatable $user, $model): bool
    {
        return $this->allows('delete');
    }

    protected function allows(string $action): bool
    {
        return app(BreadAuthorization::class)->allows($action, $this->breadSlug());
    }

    protected function breadSlug(): string
    {
        return $this->slug ??= $this->slugFromPolicyClass(static::class);
    }

    /**
     * PostPolicy -> posts, BlogPostPolicy -> blog_posts: the plural snake name
     * `tardis:make-bread` gives a model's BREAD.
     */
    protected function slugFromPolicyClass(string $class): string
    {
        return Str::plural(Str::snake(Str::beforeLast(class_basename($class), 'Policy')));
    }
}
