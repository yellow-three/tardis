<?php

declare(strict_types=1);

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tardis\Contracts\Plugins\AuthorizationPlugin;
use Tardis\Manager\PluginManager;
use Tardis\Policies\BasePolicy;

/** A host policy: the BREAD slug is derived from the class name. */
class PostPolicy extends BasePolicy {}

/** A host policy that names its BREAD explicitly. */
class ArticlePolicy extends BasePolicy
{
    protected ?string $slug = 'news-articles';
}

class PolicyTestUser extends Authenticatable
{
    protected $table = 'users';
}

class PolicyRecordingPlugin implements AuthorizationPlugin
{
    /** @var array<int, string> */
    public static array $asked = [];

    /** @var array<int, string> */
    public static array $allowed = [];

    public function name(): string
    {
        return 'policy-recording';
    }

    public function can(string $ability, mixed $model = null): bool
    {
        static::$asked[] = $ability;

        return in_array($ability, static::$allowed, true);
    }

    public function authorize(string $ability, mixed $model = null): void
    {
        $this->can($ability, $model) || abort(403);
    }
}

function policyAllows(array $abilities): void
{
    PolicyRecordingPlugin::$asked = [];
    PolicyRecordingPlugin::$allowed = $abilities;

    $manager = app(PluginManager::class);
    $manager->register('policy-recording', PolicyRecordingPlugin::class);
    $manager->enableByDefault('policy-recording');
}

test('a policy asks the authorization plugin for the same ability a BREAD page does', function () {
    policyAllows(['browse posts', 'read posts', 'edit posts', 'add posts', 'delete posts']);

    $policy = new PostPolicy;
    $user = new PolicyTestUser;

    expect($policy->browseAny($user))->toBeTrue()
        ->and($policy->browse($user, null))->toBeTrue()
        ->and($policy->read($user, null))->toBeTrue()
        ->and($policy->edit($user, null))->toBeTrue()
        ->and($policy->add($user))->toBeTrue()
        ->and($policy->delete($user, null))->toBeTrue()
        ->and(PolicyRecordingPlugin::$asked)->toBe([
            'browse posts', 'browse posts', 'read posts', 'edit posts', 'add posts', 'delete posts',
        ]);
});

test('a denial from the plugin is passed through', function () {
    policyAllows(['browse posts']);

    $policy = new PostPolicy;
    $user = new PolicyTestUser;

    expect($policy->browse($user, null))->toBeTrue()
        ->and($policy->delete($user, null))->toBeFalse()
        ->and($policy->read($user, null))->toBeFalse();
});

test('a policy can name its BREAD explicitly', function () {
    policyAllows(['edit news-articles']);

    expect((new ArticlePolicy)->edit(new PolicyTestUser, null))->toBeTrue()
        ->and((new ArticlePolicy)->delete(new PolicyTestUser, null))->toBeFalse();
});

test('the derived slug is the plural snake name of the model the policy is for', function () {
    policyAllows(['browse blog_posts']);

    $policy = new class extends BasePolicy {};

    // Anonymous classes have no usable name, so name the model through a subclass.
    $named = new class extends BasePolicy
    {
        public function slugForTest(string $class): string
        {
            return $this->slugFromPolicyClass($class);
        }
    };

    expect($named->slugForTest('App\\Policies\\BlogPostPolicy'))->toBe('blog_posts')
        ->and($named->slugForTest('PostPolicy'))->toBe('posts');
});

test('without any authorization plugin a policy allows, like the BREAD pages do', function () {
    expect((new PostPolicy)->browse(new PolicyTestUser, null))->toBeTrue();
});
