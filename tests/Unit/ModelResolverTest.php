<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Tardis\Models\ActivityLog;
use Tardis\Models\Media;
use Tardis\Models\Permission;
use Tardis\Models\Role;
use Tardis\Support\ModelResolver;
use Tests\TestCase;

class ModelResolverTest extends TestCase
{
    public function test_resolves_defaults(): void
    {
        $this->assertSame(Role::class, ModelResolver::role());
        $this->assertSame(Permission::class, ModelResolver::permission());
        $this->assertSame(Media::class, ModelResolver::media());
        $this->assertSame(ActivityLog::class, ModelResolver::activityLog());
    }

    public function test_resolves_custom_subclasses(): void
    {
        $r = new class extends Role {};
        $p = new class extends Permission {};
        $m = new class extends Media {};
        $a = new class extends ActivityLog {};

        $rc = get_class($r);
        $pc = get_class($p);
        $mc = get_class($m);
        $ac = get_class($a);

        Config::set('tardis.models.role', $rc);
        Config::set('tardis.models.permission', $pc);
        Config::set('tardis.models.media', $mc);
        Config::set('tardis.models.activity_log', $ac);

        $this->assertSame($rc, ModelResolver::role());
        $this->assertSame($pc, ModelResolver::permission());
        $this->assertSame($mc, ModelResolver::media());
        $this->assertSame($ac, ModelResolver::activityLog());
    }

    public function test_throws_for_non_subclass(): void
    {
        Config::set('tardis.models.role', self::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/role.*does not extend.*Role/i');
        ModelResolver::role();
    }

    public function test_throws_for_missing_class(): void
    {
        Config::set('tardis.models.role', 'NonExistent\\Class\\Role');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/class does not exist/i');
        ModelResolver::role();
    }

    public function test_throws_for_invalid_type(): void
    {
        Config::set('tardis.models.role', 123);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Invalid model class for config key.*tardis\.models\.role/i');
        ModelResolver::role();
    }
}
