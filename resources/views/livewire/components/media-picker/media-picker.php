<?php

use Livewire\Attributes\On;
use Livewire\Component;
use Tardis\Auth\Abilities;
use Tardis\Auth\BreadAuthorization;
use Tardis\Manager\MediaManager;

new class extends Component
{
    public string $model = '';

    public bool $open = false;

    public string $currentPath = '';

    public array $files = [];

    public array $selected = [];

    public ?string $error = null;

    public array $mimes = [];

    public int $max = 0;

    public int $min = 0;

    public bool $multiple = false;

    public bool $showFolders = false;

    public function mount(string $model = '', array $mimes = [], int $max = 0, int $min = 0, bool $showFolders = false): void
    {
        $this->model = $model;
        $this->mimes = array_values(array_filter($mimes, 'is_string'));
        $this->max = $max;
        $this->min = $min;
        $this->showFolders = $showFolders;
        $this->multiple = $max > 1 || $min > 1;
    }

    /**
     * The open event carries the field's model name; every picker embedded on
     * the page receives it, so a picker that is not the target stays closed.
     * Authorization lives here (and in confirm()), not in boot()/mount(): the
     * child renders embedded in the form field for every user allowed to see
     * the form, and only browsing the library needs `browse media`.
     */
    #[On('media-picker-open')]
    public function openPicker(?string $model = null, mixed $value = null): void
    {
        if ($model !== $this->model) {
            return;
        }

        app(BreadAuthorization::class)->authorizeAbility(Abilities::MEDIA_BROWSE);

        $this->error = null;
        $this->selected = $this->normalizeSelection($value);
        $this->loadFiles();
        $this->open = true;
    }

    public function loadFiles(): void
    {
        $files = app(MediaManager::class)->listFiles($this->currentPath);

        $files = $files->filter(function (array $file): bool {
            if (! $this->showFolders && ($file['type'] ?? '') === 'directory') {
                return false;
            }

            return $this->matchesMime($file);
        });

        $this->files = $files->sortBy('name')->values()->toArray();
    }

    public function toggleSelect(string $path): void
    {
        $this->error = null;

        if (in_array($path, $this->selected, true)) {
            $this->selected = array_values(array_diff($this->selected, [$path]));

            return;
        }

        if (! $this->multiple) {
            $this->selected = [$path];

            return;
        }

        if ($this->max > 0 && count($this->selected) >= $this->max) {
            $this->error = __('tardis::media.select_limit', ['max' => $this->max]);

            return;
        }

        $this->selected[] = $path;
    }

    public function confirm(): void
    {
        app(BreadAuthorization::class)->authorizeAbility(Abilities::MEDIA_BROWSE);

        if ($this->min > 0 && count($this->selected) < $this->min) {
            $this->error = __('tardis::media.select_minimum', ['min' => $this->min]);

            return;
        }

        $value = $this->multiple
            ? array_values($this->selected)
            : ($this->selected[0] ?? null);

        $this->dispatch('media-picked', model: $this->model, value: $value);
        $this->open = false;
        $this->error = null;
    }

    public function close(): void
    {
        $this->open = false;
        $this->error = null;
    }

    public function navigateTo(string $path): void
    {
        if (! $this->showFolders) {
            return;
        }

        $this->currentPath = $path;
        $this->loadFiles();
    }

    public function goToParent(): void
    {
        $parent = dirname($this->currentPath);
        if ($parent === '.' || $parent === '/') {
            $parent = '';
        }

        $this->navigateTo($parent);
    }

    public function getBreadcrumbs(): array
    {
        $parts = $this->currentPath ? explode('/', $this->currentPath) : [];
        $breadcrumbs = [['label' => 'Home', 'path' => '']];

        $current = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $current = $current ? $current.'/'.$part : $part;
            $breadcrumbs[] = ['label' => $part, 'path' => $current];
        }

        return $breadcrumbs;
    }

    public function formatSize(int $bytes): string
    {
        if ($bytes > 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        } elseif ($bytes > 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }

    /**
     * The bound value is the storage path (`media/photo.jpg`, the `path` key
     * from MediaManager) so it round-trips through the form field untouched.
     * A non-string or empty entry can never be a stored file, and single mode
     * keeps at most one path.
     */
    protected function normalizeSelection(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];
        $values = array_values(array_filter($values, fn ($v) => is_string($v) && $v !== ''));

        if (! $this->multiple) {
            $values = array_slice($values, 0, 1);
        }

        return $values;
    }

    /**
     * A mimes entry containing `/` is a MIME type or wildcard (`image/jpeg`,
     * `image/*`); anything else is a bare extension (`pdf`). Directories are
     * always kept so navigation is never filtered away.
     */
    protected function matchesMime(array $file): bool
    {
        if ($this->mimes === [] || ($file['type'] ?? '') === 'directory') {
            return true;
        }

        $type = (string) ($file['type'] ?? '');
        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

        foreach ($this->mimes as $mime) {
            if (str_contains($mime, '/')) {
                if (str_starts_with($type, rtrim($mime, '*'))) {
                    return true;
                }
            } elseif ($extension === strtolower($mime)) {
                return true;
            }
        }

        return false;
    }
};
