<?php

namespace Tardis\Manager;

use Illuminate\Filesystem\DirectoryAttributes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tardis\Models\Media;

class MediaManager
{
    protected string $disk;

    protected string $basePath;

    public function __construct()
    {
        $this->disk = config('tardis-media.disk', 'public');
        $this->basePath = config('tardis-media.path', 'media');
    }

    public function upload(
        UploadedFile $file,
        string $path = '',
        ?string $altText = null,
    ): Media {
        $path = Str::finish($this->fullPath($path), '/');

        $name = $this->getUniqueFileName($file, $path);

        // storeAs() joins with its own separator, so it must not receive a trailing slash.
        $storedPath = $file->storeAs(rtrim($path, '/'), $name, $this->disk);

        return Media::create([
            'name' => $name,
            'original_name' => $file->getClientOriginalName(),
            'path' => $storedPath,
            'disk' => $this->disk,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'alt_text' => $altText,
            'collection' => trim($path, '/'),
            'created_by' => auth()->id(),
        ]);
    }

    public function listFiles(string $path = ''): Collection
    {
        $path = Str::finish($this->fullPath($path), '/');
        $storage = Storage::disk($this->disk);

        $files = collect($storage->listContents($path))
            ->map(function ($item) use ($storage) {
                $isDir = $item instanceof DirectoryAttributes
                    || str_ends_with($item['path'], '/');

                $size = 0;
                $mimeType = 'directory';
                $lastModified = null;

                if (! $isDir) {
                    try {
                        $size = $storage->fileSize($item['path']);
                        $mimeType = $storage->mimeType($item['path']);
                        $lastModified = $storage->lastModified($item['path']);
                    } catch (\Throwable) {
                        // File might not exist or metadata unavailable
                    }
                }

                return [
                    'type' => $mimeType,
                    'name' => basename($item['path']),
                    'path' => $item['path'],
                    'relative_path' => Str::after($item['path'], $this->basePath.'/'),
                    'size' => $size,
                    'url' => $storage->url($item['path']),
                    'last_modified' => $lastModified,
                ];
            })
            ->sortBy('type')
            ->sortBy('name')
            ->values();

        return $files;
    }

    public function createDirectory(string $path, string $name): bool
    {
        $this->assertValidName($name, 'createDirectory');

        return Storage::disk($this->disk)->makeDirectory($this->fullPath($path).'/'.$name);
    }

    public function deleteFile(string $path): bool
    {
        $fullPath = $this->fullPath($path);

        $deleted = Storage::disk($this->disk)->delete($fullPath);

        Media::query()->where('path', $fullPath)->delete();

        return $deleted;
    }

    public function deleteDirectory(string $path): bool
    {
        $fullPath = $this->fullPath($path);

        $deleted = Storage::disk($this->disk)->deleteDirectory($fullPath);

        Media::query()->where('path', 'like', $fullPath.'/%')->delete();

        return $deleted;
    }

    public function rename(string $oldPath, string $newName): bool
    {
        $this->assertValidName($newName, 'rename');

        $source = $this->fullPath($oldPath);
        $target = dirname($source).'/'.$newName;

        $storage = Storage::disk($this->disk);

        if (! $storage->exists($source)) {
            return false;
        }

        $wasDirectory = $storage->directoryExists($source);

        if (! $storage->move($source, $target)) {
            return false;
        }

        if ($wasDirectory) {
            $this->syncDirectoryRename($source, $target);
        } else {
            // name tracks the current basename; original_name keeps the uploaded one.
            Media::query()->where('path', $source)->update([
                'path' => $target,
                'name' => $newName,
            ]);
        }

        return true;
    }

    public function downloadZip(array $paths, string $filename = 'media-export.zip'): string
    {
        $zip = new \ZipArchive;
        $zipPath = storage_path('app/temp/'.$filename);

        if (! is_dir(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create ZIP archive');
        }

        $storage = Storage::disk($this->disk);
        $tempFiles = [];
        $added = 0;

        try {
            foreach ($paths as $path) {
                $fullPath = $this->fullPath($path);

                if (! $storage->fileExists($fullPath)) {
                    continue;
                }

                $tempFiles[] = $this->addStreamToArchive($zip, $storage->readStream($fullPath), $fullPath);
                $added++;
            }

            // Temp files must outlive close(): the archive is only finalised here.
            $zip->close();
        } finally {
            foreach ($tempFiles as $tempFile) {
                unlink($tempFile);
            }
        }

        // ZipArchive writes nothing at all when no entry was added, so returning
        // $zipPath here would hand the caller a path to a file that was never created.
        if ($added === 0) {
            if (is_file($zipPath)) {
                unlink($zipPath);
            }

            throw new \RuntimeException('None of the selected media entries could be archived.');
        }

        return $zipPath;
    }

    public function getFileInfo(string $path): ?array
    {
        $fullPath = $this->fullPath($path);
        $storage = Storage::disk($this->disk);

        if (! $storage->exists($fullPath)) {
            return null;
        }

        // Flysystem refuses fileSize()/mimeType() on a directory, so a folder is
        // described exactly the way listFiles() describes one instead.
        $isDirectory = $storage->directoryExists($fullPath);
        $mimeType = $isDirectory ? 'directory' : $storage->mimeType($fullPath);

        return [
            'type' => $mimeType,
            'name' => basename($fullPath),
            'path' => $fullPath,
            'relative_path' => Str::after($fullPath, $this->basePath.'/'),
            'size' => $isDirectory ? 0 : $storage->fileSize($fullPath),
            'mime_type' => $mimeType,
            'url' => $storage->url($fullPath),
            'last_modified' => $isDirectory ? null : $storage->lastModified($fullPath),
        ];
    }

    /**
     * Every public $path argument is relative to basePath; this is the only place
     * that turns one into a storage path, so traversal cannot slip in elsewhere.
     */
    protected function fullPath(string $relativePath): string
    {
        $path = trim($relativePath, '/');

        if (str_contains($path, "\0") || in_array('..', explode('/', str_replace('\\', '/', $path)), true)) {
            throw new InvalidArgumentException(
                'Media path ['.$relativePath.'] is invalid: it may not contain ".." segments or null bytes.'
            );
        }

        return rtrim($this->basePath.'/'.$path, '/');
    }

    protected function assertValidName(string $name, string $operation): void
    {
        if ($name === ''
            || str_contains($name, "\0")
            || str_contains($name, '/')
            || str_contains($name, '\\')
            || str_contains($name, '..')) {
            throw new InvalidArgumentException(
                $operation.'() name ['.$name.'] is invalid: use a single name without path separators, ".." or null bytes.'
            );
        }
    }

    /**
     * A LIKE pre-select is not trusted for the rewrite itself: the prefix is
     * re-checked in PHP so a name containing % or _ cannot shift a path.
     */
    protected function syncDirectoryRename(string $from, string $to): void
    {
        $prefix = $from.'/';

        Media::query()
            ->where('path', 'like', $prefix.'%')
            ->orWhere('collection', 'like', $prefix.'%')
            ->orWhere('collection', $from)
            ->get(['id', 'path', 'collection'])
            ->each(function (Media $media) use ($from, $to) {
                $path = $this->swapPathPrefix($media->path, $from, $to);
                $collection = $this->swapPathPrefix($media->collection, $from, $to);

                if ($path === $media->path && $collection === $media->collection) {
                    return;
                }

                $media->path = $path;
                $media->collection = $collection;
                $media->save();
            });
    }

    protected function swapPathPrefix(?string $value, string $from, string $to): ?string
    {
        if ($value === $from) {
            return $to;
        }

        if ($value !== null && str_starts_with($value, $from.'/')) {
            return $to.substr($value, strlen($from));
        }

        return $value;
    }

    protected function addStreamToArchive(\ZipArchive $zip, mixed $stream, string $fullPath): string
    {
        if (! is_resource($stream)) {
            throw new \RuntimeException('Could not read media file ['.$fullPath.']');
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'tardis_');

        if ($tempFile === false) {
            fclose($stream);

            throw new \RuntimeException('Could not create a temporary file for media file ['.$fullPath.']');
        }

        try {
            if (file_put_contents($tempFile, $stream) === false) {
                throw new \RuntimeException('Could not buffer media file ['.$fullPath.'] for the ZIP archive');
            }

            $zip->addFile($tempFile, basename($fullPath));
        } catch (\Throwable $e) {
            unlink($tempFile);

            throw $e;
        } finally {
            fclose($stream);
        }

        return $tempFile;
    }

    protected function getUniqueFileName(UploadedFile $file, string $path): string
    {
        $name = $file->getClientOriginalName();
        $storage = Storage::disk($this->disk);
        $count = 0;

        while ($storage->exists($path.$name)) {
            $count++;
            $pathinfo = pathinfo($file->getClientOriginalName());
            $name = $pathinfo['filename'].'_'.$count.'.'.$pathinfo['extension'];
        }

        return $name;
    }
}
