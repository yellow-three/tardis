<?php

namespace Tardis\Manager;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tardis\Classes\Setting;

class SettingsManager
{
    protected string $path;

    protected ?Collection $settings = null;

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

        return $this->findByKey($key)?->value ?? $default;
    }

    public function set(string $key, mixed $value, bool $save = true): void
    {
        $this->load();

        $setting = $this->findByKey($key);

        if ($setting) {
            $setting->value = $value;

            if ($save) {
                $this->save();
            }
        }
    }

    public function update(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->set($key, $value, false);
        }

        $this->save();
    }

    public function save(?array $data = null): void
    {
        if ($data !== null) {
            $this->settings = collect($data)->map(
                fn (array $item) => new Setting($item)
            );
        }

        $content = $this->settings->map(fn (Setting $setting) => $setting->toArray()
        )->values()->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        File::ensureDirectoryExists(dirname($this->path));
        File::put($this->path, $content);
    }

    public function load(bool $force = false): void
    {
        if ($this->settings === null || $force) {
            $this->ensureDirectoryExists();

            if (! File::exists($this->path)) {
                File::put($this->path, '[]');
            }

            $content = File::get($this->path);
            $data = json_decode($content, true) ?? [];

            $this->settings = collect($data)->map(
                fn (array $item) => new Setting($item)
            );
        }
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

        $this->settings->push($setting);
        $this->save();

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
        $this->settings = $this->settings->filter(
            fn (Setting $s) => $s->getFullKey() !== $key
        )->values();

        $this->save();

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
        $data['key'] = $setting->key . '_copy';
        $data['uuid'] = (string) Str::uuid();

        return $this->create($data);
    }

    public function import(string $json): int
    {
        $data = json_decode($json, true);
        if (! is_array($data)) {
            throw new \RuntimeException('Invalid JSON data.');
        }

        $count = 0;
        foreach ($data as $item) {
            if (isset($item['key'])) {
                $this->create($item);
                $count++;
            }
        }

        return $count;
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

        $audit = [];
        if (File::exists($auditPath)) {
            $audit = json_decode(File::get($auditPath), true) ?? [];
        }

        $audit[] = $entry;

        if (count($audit) > 100) {
            $audit = array_slice($audit, -100);
        }

        File::put($auditPath, json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    protected function ensureDirectoryExists(): void
    {
        $dir = dirname($this->path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}
