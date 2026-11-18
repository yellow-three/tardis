<?php

declare(strict_types=1);

namespace Tardis\Formfields\Types;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tardis\Media\ImageProcessor;

/**
 * An uploaded image, optionally fitted to a size, re-encoded at a quality and
 * stored with named thumbnails next to it (`photo.jpg`, `photo-small.jpg`).
 *
 * Definition keys, on top of the file field's:
 *   "resize": {"width": 1600, "height": null}
 *   "quality": 85
 *   "thumbnails": [{"name": "small", "width": 300, "height": 300, "crop": true}]
 *
 * Processing needs the GD extension; without it the original is stored and
 * no thumbnails are made.
 */
class ImageField extends FileField
{
    protected array $configurable = ['mimes', 'max_size' => 'maxSize', 'disk', 'directory', 'resize', 'quality', 'thumbnails'];

    /** @var array{width?: int|null, height?: int|null} */
    public array $resize = [];

    public int $quality = 90;

    /** @var list<array{name: string, width?: int|null, height?: int|null, crop?: bool}> */
    public array $thumbnails = [];

    public function transform(mixed $value): mixed
    {
        if (! $value instanceof UploadedFile) {
            return $value;
        }

        $path = $value->store($this->directory, $this->disk);

        if (! is_string($path)) {
            return $path;
        }

        $this->process($path, (string) $value->getMimeType());

        return $path;
    }

    /**
     * The stored path of a named thumbnail of $path.
     */
    public static function thumbnailPath(string $path, string $name): string
    {
        $info = pathinfo($path);
        $dir = ($info['dirname'] ?? '.') === '.' ? '' : $info['dirname'].'/';
        $extension = isset($info['extension']) ? '.'.$info['extension'] : '';

        return $dir.$info['filename'].'-'.$name.$extension;
    }

    public function thumbnailUrl(?string $path, string $name): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $thumbnail = self::thumbnailPath($path, $name);
        $storage = Storage::disk($this->disk);

        return $storage->url($storage->exists($thumbnail) ? $thumbnail : $path);
    }

    protected function process(string $path, string $mimeType): void
    {
        $processor = app(ImageProcessor::class);

        if (! $processor->supports($mimeType)) {
            return;
        }

        $storage = Storage::disk($this->disk);
        $original = (string) $storage->get($path);

        $width = $this->dimension($this->resize['width'] ?? null);
        $height = $this->dimension($this->resize['height'] ?? null);

        if ($width !== null || $height !== null) {
            $resized = $processor->resize($original, $mimeType, $width, $height, $this->quality);

            if ($resized !== null) {
                $storage->put($path, $resized);
                $original = $resized;
            }
        }

        foreach ($this->thumbnails as $thumbnail) {
            $name = (string) ($thumbnail['name'] ?? '');

            if (preg_match('/^[a-z0-9_-]+$/i', $name) !== 1) {
                continue;
            }

            $bytes = $processor->resize(
                $original,
                $mimeType,
                $this->dimension($thumbnail['width'] ?? null),
                $this->dimension($thumbnail['height'] ?? null),
                $this->quality,
                (bool) ($thumbnail['crop'] ?? false),
            );

            if ($bytes !== null) {
                $storage->put(self::thumbnailPath($path, $name), $bytes);
            }
        }
    }

    private function dimension(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    public function type(): string
    {
        return 'image';
    }

    public function render(): string
    {
        return 'tardis::formfields.image';
    }
}
