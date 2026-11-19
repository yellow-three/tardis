<?php

declare(strict_types=1);

namespace Tardis\Settings;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tardis\Support\FileSignature;
use Tardis\Support\JsonFile;

class SettingsManager
{
    protected string $path;

    protected ?Collection $settings = null;

    /** The version of settings.json the list was read from (FileSignature). */
    protected ?string $signature = null;

    public function __construct()
    {
        $this->path = Str::finish(storage_path('tardis/settings'), '/').'settings.json';

        $this->ensureDirectoryExists();
    }

    public function all(): Collection
    {
        $this->load();

        return $this->settings;
    }

    public function groups(): Collection
    {
        return $this->all()
            ->groupBy(fn (Setting $setting) => $setting->group ?? '_ungrouped')
            ->sortKeys();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->load();

        return $this->findByKey($key)->value ?? $default;
    }

    public function set(string $key, mixed $value, bool $save = true): void
    {
        if (! $save) {
            $this->load();
            $setting = $this->findByKey($key);

            if ($setting) {
                $setting->value = $value;
            }

            return;
        }

        $this->update([$key => $value]);
    }

    /**
     * Write values by full key. Only these values change, on top of what is
     * on disk now: a setting another administrator saved a moment ago stays.
     *
     * @param  array<string, mixed>  $settings
     */
    public function update(array $settings): void
    {
        $this->mutate(function (array $items) use ($settings) {
            foreach ($items as $index => $item) {
                $fullKey = self::fullKey($item);

                if (array_key_exists($fullKey, $settings)) {
                    $items[$index]['value'] = $settings[$fullKey];
                }
            }

            return $items;
        });
    }

    /**
     * Replace every setting with $data, or write the list as it is in memory.
     *
     * @param  array<int, array<string, mixed>>|null  $data
     */
    public function save(?array $data = null): void
    {
        $data ??= $this->all()->map(fn (Setting $setting) => $setting->toArray())->values()->all();

        $this->mutate(fn () => $data);
    }

    public function load(bool $force = false): void
    {
        // Re-read when another process wrote the file since, so a long-running
        // worker never keeps serving a value that was changed elsewhere.
        $signature = FileSignature::of($this->path);

        if ($this->settings !== null && ! $force && $signature === $this->signature) {
            return;
        }

        $this->ensureDirectoryExists();
        $this->signature = $signature;
        $this->settings = collect($this->readItems())->map(fn (array $item) => new Setting($item));
    }

    /**
     * The stored items, or the preset shipped with the package while nothing
     * has been saved yet. The preset is only read; saving writes the file.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function readItems(): array
    {
        $source = is_file($this->path) ? $this->path : self::presetPath();

        return array_values(array_filter(JsonFile::read($source), 'is_array'));
    }

    /**
     * Change the stored list under the file's lock, starting from what is on
     * disk now.
     *
     * @param  callable(array<int, array<string, mixed>>): array<int, array<string, mixed>>  $change
     */
    protected function mutate(callable $change): void
    {
        $written = JsonFile::update($this->path, fn () => array_values($change($this->readItems())));

        $this->settings = collect($written)->map(fn (array $item) => new Setting($item));
        $this->signature = FileSignature::of($this->path);
    }

    /** @param array<string, mixed> $item */
    protected static function fullKey(array $item): string
    {
        return ! empty($item['group']) ? $item['group'].'.'.($item['key'] ?? '') : (string) ($item['key'] ?? '');
    }

    public static function presetPath(): string
    {
        return dirname(__DIR__, 2).'/resources/presets/settings.json';
    }

    public function loadPreset(string $path): void
    {
        if (! File::exists($path)) {
            return;
        }

        $content = File::get($path);
        $data = json_decode($content, true) ?? [];

        $this->load();

        foreach ($data as $item) {
            $key = isset($item['group']) && $item['group']
                ? $item['group'].'.'.$item['key']
                : $item['key'];

            if (! $this->findByKey($key)) {
                $this->settings->push(new Setting($item));
            }
        }

        $this->save();
    }

    public function create(array $data): Setting
    {
        $this->load();

        $setting = new Setting($data);

        if (! $setting->uuid) {
            $setting->uuid = (string) Str::uuid();
        }

        $existing = $this->findByKey($setting->getFullKey());
        if ($existing) {
            throw new \RuntimeException("Setting with key '{$setting->getFullKey()}' already exists.");
        }

        $this->mutate(function (array $items) use ($setting) {
            foreach ($items as $item) {
                if (self::fullKey($item) === $setting->getFullKey()) {
                    throw new \RuntimeException("Setting with key '{$setting->getFullKey()}' already exists.");
                }
            }

            return [...$items, $setting->toArray()];
        });

        $this->logAudit('created', $setting->getFullKey(), null, $setting->toArray());

        return $setting;
    }

    public function delete(string $key): bool
    {
        $this->load();

        $setting = $this->findByKey($key);
        if (! $setting) {
            return false;
        }

        $oldValues = $setting->toArray();
        $this->mutate(fn (array $items) => array_filter($items, fn (array $item) => self::fullKey($item) !== $key));

        $this->logAudit('deleted', $key, $oldValues, null);

        return true;
    }

    public function duplicate(string $key): ?Setting
    {
        $this->load();

        $setting = $this->findByKey($key);
        if (! $setting) {
            return null;
        }

        $data = $setting->toArray();
        $data['key'] = $setting->key.'_copy';
        $data['uuid'] = (string) Str::uuid();

        return $this->create($data);
    }

    public function import(string $json): int
    {
        $data = json_decode($json, true);
        if (! is_array($data)) {
            throw new \RuntimeException('Invalid JSON data.');
        }

        $items = array_values(array_filter($data, fn ($item) => is_array($item) && isset($item['key'])));

        // Validate everything before writing anything: a clash on the tenth item
        // must not leave the first nine behind as a half-finished import.
        $seen = [];
        foreach ($items as $item) {
            $fullKey = (new Setting($item))->getFullKey();

            if (isset($seen[$fullKey]) || $this->findByKey($fullKey)) {
                throw new \RuntimeException("Setting with key '{$fullKey}' already exists. Nothing was imported.");
            }

            $seen[$fullKey] = true;
        }

        foreach ($items as $item) {
            $this->create($item);
        }

        return count($items);
    }

    public function export(): string
    {
        $this->load();

        return $this->settings->map(fn (Setting $setting) => $setting->toArray()
        )->values()->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function findByKey(string $key): ?Setting
    {
        $this->load();

        return $this->settings->first(function (Setting $setting) use ($key) {
            return $setting->getFullKey() === $key;
        });
    }

    protected function logAudit(string $action, string $key, ?array $oldValues, mixed $newValues): void
    {
        $auditPath = dirname($this->path).'/audit.json';

        $entry = [
            'action' => $action,
            'key' => $key,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'user_id' => auth()->id(),
            'timestamp' => now()->toIso8601String(),
        ];

        JsonFile::update($auditPath, function (array $audit) use ($entry) {
            $audit[] = $entry;

            return array_values(array_slice($audit, -100));
        });
    }

    protected function ensureDirectoryExists(): void
    {
        $dir = dirname($this->path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
