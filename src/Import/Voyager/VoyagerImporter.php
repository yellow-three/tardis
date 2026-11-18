<?php

declare(strict_types=1);

namespace Tardis\Import\Voyager;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tardis\Bread\BreadManager;
use Tardis\Bread\ReservedSlugs;
use Tardis\Menu\SiteMenuStore;
use Tardis\Settings\Setting;
use Tardis\Settings\SettingsManager;
use Tardis\Support\ModelResolver;

/**
 * Reads a Voyager 1.x installation's tables and writes what Tardis can use:
 * data_types/data_rows as BREAD definitions, menus (except the admin menu,
 * which Tardis builds in code) as site menus, settings, and roles with their
 * permissions and user assignments.
 *
 * Nothing existing is overwritten unless $force is set. With $dryRun nothing
 * is written at all; the report says what would happen either way.
 */
final class VoyagerImporter
{
    public const PARTS = ['bread', 'menus', 'settings', 'roles'];

    private ImportReport $report;

    /** @var array<string, string> Voyager table => BREAD slug */
    private array $slugs = [];

    public function __construct(
        private readonly ?string $connection = null,
        private readonly bool $dryRun = false,
        private readonly bool $force = false,
    ) {
        $this->report = new ImportReport;
    }

    /**
     * @param  list<string>  $parts
     */
    public function run(array $parts = self::PARTS): ImportReport
    {
        foreach ($this->rows('data_types') ?? [] as $type) {
            $this->slugs[(string) $type['name']] = (string) $type['slug'];
        }

        foreach (array_intersect(self::PARTS, $parts) as $part) {
            $this->{'import'.ucfirst($part)}();
        }

        return $this->report;
    }

    private function importBread(): void
    {
        $types = $this->rows('data_types');

        if ($types === null) {
            $this->report->add('bread', 'data_types', ImportReport::SKIPPED, 'Table not found.');

            return;
        }

        $rows = collect($this->rows('data_rows') ?? [])->groupBy('data_type_id');
        $mapper = new BreadMapper;

        foreach ($types as $type) {
            $slug = (string) $type['slug'];

            if (ReservedSlugs::has($slug)) {
                $this->report->add('bread', $slug, ImportReport::SKIPPED, 'A built-in Tardis screen handles this.');

                continue;
            }

            $definition = $mapper->map($type, $rows->get($type['id'], collect())->all());

            foreach ($mapper->problems as $problem) {
                $this->report->add('bread', $slug, ImportReport::UNMAPPED, $problem);
            }

            if ($definition === null) {
                continue;
            }

            if (! $this->force && app(BreadManager::class)->has($slug)) {
                $this->report->add('bread', $slug, ImportReport::SKIPPED, 'A BREAD with this slug exists (use --force).');

                continue;
            }

            if (! $this->dryRun) {
                app(BreadManager::class)->save($definition);
            }

            $this->report->add('bread', $slug, ImportReport::IMPORTED, count($definition['fields']).' fields');
        }
    }

    private function importMenus(): void
    {
        $menus = $this->rows('menus');

        if ($menus === null) {
            $this->report->add('menus', 'menus', ImportReport::SKIPPED, 'Table not found.');

            return;
        }

        $items = collect($this->rows('menu_items') ?? [])->groupBy('menu_id');
        $store = app(SiteMenuStore::class);

        foreach ($menus as $menu) {
            $name = Str::slug((string) $menu['name'], '_');

            if ($menu['name'] === 'admin') {
                $this->report->add('menus', 'admin', ImportReport::SKIPPED, 'The panel builds its own sidebar; adjust it in the menu builder.');

                continue;
            }

            if (! SiteMenuStore::isValidName($name)) {
                $this->report->add('menus', (string) $menu['name'], ImportReport::UNMAPPED, 'The name cannot be used as a site menu name.');

                continue;
            }

            if ($store->has($name) && ! $this->force) {
                $this->report->add('menus', $name, ImportReport::SKIPPED, 'A site menu with this name exists (use --force).');

                continue;
            }

            if (! $this->dryRun) {
                $store->delete($name);
                $store->create($name);
            }

            $count = $this->importMenuItems($store, $name, $items->get($menu['id'], collect())->all(), null, null);
            $this->report->add('menus', $name, ImportReport::IMPORTED, $count.' items');
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function importMenuItems(SiteMenuStore $store, string $menu, array $items, mixed $voyagerParent, ?string $parent, int $depth = 1): int
    {
        $children = array_filter($items, fn (array $item) => ($item['parent_id'] ?? null) == $voyagerParent);
        usort($children, fn (array $a, array $b) => (int) $a['order'] <=> (int) $b['order']);
        $count = 0;

        foreach ($children as $item) {
            $url = $this->menuUrl($item);

            if ($url === null || $depth > SiteMenuStore::MAX_DEPTH) {
                $this->report->add('menus', $menu.': '.$item['title'], ImportReport::UNMAPPED, $url === null ? 'No usable url or route.' : 'Nested deeper than Tardis allows.');

                continue;
            }

            $id = $this->dryRun ? null : $store->add($menu, [
                'title' => (string) $item['title'],
                'url' => $url,
                'new_tab' => ($item['target'] ?? '_self') === '_blank',
            ], $parent);

            $count += 1 + $this->importMenuItems($store, $menu, $items, $item['id'], $id, $depth + 1);
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function menuUrl(array $item): ?string
    {
        $url = trim((string) ($item['url'] ?? ''));

        if ($url === '' && ! empty($item['route']) && Route::has((string) $item['route'])) {
            $parameters = json_decode((string) ($item['parameters'] ?? ''), true);
            $url = route((string) $item['route'], is_array($parameters) ? $parameters : [], false);
        }

        if ($url !== '' && ! str_starts_with($url, 'http') && ! str_starts_with($url, '#')) {
            $url = '/'.ltrim($url, '/');
        }

        return preg_match('#^(https?://|/(?!/)|\#)#i', $url) === 1 ? $url : null;
    }

    private function importSettings(): void
    {
        $settings = $this->rows('settings');

        if ($settings === null) {
            $this->report->add('settings', 'settings', ImportReport::SKIPPED, 'Table not found.');

            return;
        }

        $manager = app(SettingsManager::class);
        $types = ['text' => 'text', 'text_area' => 'textarea', 'rich_text_box' => 'textarea', 'code_editor' => 'textarea',
            'checkbox' => 'toggle', 'radio_btn' => 'radio', 'select_dropdown' => 'select', 'file' => 'file', 'image' => 'image'];

        foreach ($settings as $setting) {
            $fullKey = (string) $setting['key'];
            [$group, $key] = str_contains($fullKey, '.') ? explode('.', $fullKey, 2) : [null, $fullKey];
            $type = $types[$setting['type'] ?? 'text'] ?? null;

            if ($type === null || ! array_key_exists($type, Setting::availableTypes())) {
                $this->report->add('settings', $fullKey, ImportReport::UNMAPPED, "Setting type [{$setting['type']}] has no Tardis equivalent.");

                continue;
            }

            if ($manager->findByKey($fullKey) !== null) {
                if (! $this->force) {
                    $this->report->add('settings', $fullKey, ImportReport::SKIPPED, 'A setting with this key exists (use --force to take the value).');

                    continue;
                }

                if (! $this->dryRun) {
                    $manager->set($fullKey, $setting['value']);
                }

                $this->report->add('settings', $fullKey, ImportReport::IMPORTED, 'value updated');

                continue;
            }

            $details = is_array($setting['details'] ?? null) ? $setting['details'] : [];

            if (! $this->dryRun) {
                $manager->create([
                    'group' => $group,
                    'key' => $key,
                    'name' => (string) ($setting['display_name'] ?? $key),
                    'type' => $type,
                    'value' => $setting['value'],
                    'options' => is_array($details['options'] ?? null) ? $details['options'] : [],
                ]);
            }

            $this->report->add('settings', $fullKey, ImportReport::IMPORTED);
        }
    }

    private function importRoles(): void
    {
        $roles = $this->rows('roles');

        if ($roles === null) {
            $this->report->add('roles', 'roles', ImportReport::SKIPPED, 'Table not found.');

            return;
        }

        $mapper = new PermissionMapper($this->slugs);
        $permissionKeys = collect($this->rows('permissions') ?? [])->pluck('key', 'id');
        $grants = collect($this->rows('permission_role') ?? [])->groupBy('role_id');
        $roleIds = [];

        foreach ($roles as $role) {
            $slug = Str::slug((string) $role['name']);
            $abilities = [];

            foreach ($grants->get($role['id'], collect()) as $grant) {
                $key = (string) ($permissionKeys[$grant['permission_id']] ?? '');
                $ability = $mapper->map($key);

                $ability === null
                    ? $this->report->add('roles', $slug, ImportReport::UNMAPPED, "Permission [{$key}] has no Tardis equivalent.")
                    : $abilities[$ability] = true;
            }

            if (! $this->dryRun) {
                $model = ModelResolver::role()::query()->firstOrCreate(['slug' => $slug], ['name' => (string) ($role['display_name'] ?? $slug)]);
                $ids = collect(array_keys($abilities))->map(fn (string $ability) => ModelResolver::permission()::query()
                    ->firstOrCreate(['slug' => $ability], ['name' => $ability])->getKey())->all();
                $model->permissions()->syncWithoutDetaching($ids);
                $roleIds[$role['id']] = $model->getKey();
            }

            $this->report->add('roles', $slug, ImportReport::IMPORTED, count($abilities).' permissions');
        }

        if (! $this->dryRun) {
            $this->assignUsers($roleIds);
        }
    }

    /**
     * @param  array<int|string, int|string>  $roleIds  Voyager role id => Tardis role id
     */
    private function assignUsers(array $roleIds): void
    {
        $pairs = collect($this->rows('user_roles') ?? [])->map(fn (array $row) => [$row['user_id'], $row['role_id']]);

        if ($this->source()->getSchemaBuilder()->hasColumn('users', 'role_id')) {
            $pairs = $pairs->merge(collect($this->rows('users') ?? [])->filter(fn (array $user) => ! empty($user['role_id']))
                ->map(fn (array $user) => [$user['id'], $user['role_id']]));
        }

        $assigned = 0;

        foreach ($pairs as [$userId, $voyagerRole]) {
            if (! isset($roleIds[$voyagerRole])) {
                continue;
            }

            $assigned += DB::table('tardis_role_user')->insertOrIgnore(['user_id' => $userId, 'role_id' => $roleIds[$voyagerRole]]);
        }

        $this->report->add('roles', 'users', ImportReport::IMPORTED, $assigned.' role assignments');
    }

    /**
     * Rows of a source table as arrays with JSON "details" decoded, or null
     * when the table does not exist.
     *
     * @return list<array<string, mixed>>|null
     */
    private function rows(string $table): ?array
    {
        if (! $this->source()->getSchemaBuilder()->hasTable($table)) {
            return null;
        }

        return $this->source()->table($table)->get()->map(function ($row) {
            $row = (array) $row;

            if (isset($row['details']) && is_string($row['details'])) {
                $decoded = json_decode($row['details'], true);
                $row['details'] = is_array($decoded) ? $decoded : [];
            }

            return $row;
        })->all();
    }

    private function source(): ConnectionInterface
    {
        return DB::connection($this->connection);
    }
}
