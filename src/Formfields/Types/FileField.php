<?php

namespace Tardis\Formfields\Types;

use Illuminate\Http\UploadedFile;
use Tardis\Formfields\Formfield;

class FileField extends Formfield
{
    protected array $configurable = ['mimes', 'max_size' => 'maxSize', 'disk', 'directory'];

    public array $mimes = [];

    public int $maxSize = 0;

    public string $disk = 'public';

    public string $directory = 'uploads';

    public function mimes(array $mimes): self
    {
        $this->mimes = $mimes;

        return $this;
    }

    public function maxSize(int $kb): self
    {
        $this->maxSize = $kb;

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

    public function transform(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return $value->store($this->directory, $this->disk);
        }

        return $value;
    }

    public function type(): string
    {
        return 'file';
    }

    public function render(): string
    {
        return 'tardis::formfields.file';
    }

    protected function extraViewData(): array
    {
        return [
            'mimes' => $this->mimes,
            'maxSize' => $this->maxSize,
        ];
    }
}
