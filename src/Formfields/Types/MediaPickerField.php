<?php

namespace Tardis\Formfields\Types;

use Illuminate\Http\UploadedFile;
use Tardis\Formfields\Formfield;

class MediaPickerField extends Formfield
{
    protected array $configurable = [
        'mimes',
        'allowed',
        'max',
        'min',
        'disk',
        'directory',
        'show_folders' => 'showFolders',
    ];

    public array $mimes = [];

    public array $allowed = [];

    public int $max = 0;

    public int $min = 0;

    public string $disk = 'public';

    public string $directory = 'uploads';

    public bool $showFolders = false;

    public function mimes(array $mimes): self
    {
        $this->mimes = $mimes;

        return $this;
    }

    public function allowed(array $allowed): self
    {
        $this->allowed = $allowed;

        return $this;
    }

    public function max(int $max): self
    {
        $this->max = $max;

        return $this;
    }

    public function min(int $min): self
    {
        $this->min = $min;

        return $this;
    }

    public function disk(string $disk): self
    {
        $this->disk = $disk;

        return $this;
    }

    public function directory(string $directory): self
    {
        $this->directory = $directory;

        return $this;
    }

    public function showFolders(bool $value = true): self
    {
        $this->showFolders = $value;

        return $this;
    }

    public function transform(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return $value->store($this->directory, $this->disk);
        }

        return $value;
    }

    public function type(): string
    {
        return 'media_picker';
    }

    public function render(): string
    {
        return 'tardis::formfields.media-picker';
    }

    protected function extraViewData(): array
    {
        return [
            'mimes' => $this->allowed ?: $this->mimes,
            'max' => $this->max,
            'min' => $this->min,
            'multiple' => $this->max > 1 || $this->min > 1,
            'showFolders' => $this->showFolders,
        ];
    }
}
