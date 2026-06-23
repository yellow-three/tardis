<?php

namespace Tardis\Core\Manager;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tardis\Core\Classes\Setting;

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
            $this->settings = collect($data);
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

    protected function findByKey(string $key): ?Setting
    {
        return $this->settings->first(function (Setting $setting) use ($key) {
            return $setting->getFullKey() === $key;
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
