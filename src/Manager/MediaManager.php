<?php

namespace Tardis\Manager;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        $path = Str::finish($this->basePath.'/'.$path, '/');

        $name = $this->getUniqueFileName($file, $path);
        $storedPath = $file->storeAs($path, $name, $this->disk);

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
        $path = Str::finish($this->basePath.'/'.$path, '/');
        $storage = Storage::disk($this->disk);

        $files = collect($storage->listContents($path))
            ->map(function ($item) use ($storage) {
                $isDir = $item instanceof \Illuminate\Filesystem\DirectoryAttributes
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
        $fullPath = Str::finish($this->basePath.'/'.$path, '/').$name;

        return Storage::disk($this->disk)->makeDirectory($fullPath);
    }

    public function deleteFile(string $path): bool
    {
        return Storage::disk($this->disk)->delete($this->basePath.'/'.$path);
    }

    public function deleteDirectory(string $path): bool
    {
        return Storage::disk($this->disk)->deleteDirectory($this->basePath.'/'.$path);
    }

    public function rename(string $oldPath, string $newName): bool
    {
        $fullOldPath = $this->basePath.'/'.$oldPath;
        $directory = dirname($fullOldPath);
        $newPath = $directory.'/'.$newName;

        $content = Storage::disk($this->disk)->get($fullOldPath);
        if ($content === null) {
            return false;
        }

        Storage::disk($this->disk)->put($newPath, $content);
        Storage::disk($this->disk)->delete($fullOldPath);

        return true;
    }

    public function move(string $from, string $toDirectory): bool
    {
        $fullFrom = $this->basePath.'/'.$from;
        $fileName = basename($fullFrom);
        $fullTo = Str::finish($this->basePath.'/'.$toDirectory, '/').$fileName;

        $content = Storage::disk($this->disk)->get($fullFrom);
        if ($content === null) {
            return false;
        }

        Storage::disk($this->disk)->put($fullTo, $content);
        Storage::disk($this->disk)->delete($fullFrom);

        return true;
    }

    public function copy(string $from, string $toDirectory): bool
    {
        $fullFrom = $this->basePath.'/'.$from;
        $fileName = basename($fullFrom);
        $fullTo = Str::finish($this->basePath.'/'.$toDirectory, '/').$fileName;

        $content = Storage::disk($this->disk)->get($fullFrom);
        if ($content === null) {
            return false;
        }

        Storage::disk($this->disk)->put($fullTo, $content);

        return true;
    }

    public function download(string $path): ?string
    {
        $fullPath = $this->basePath.'/'.$path;

        if (! Storage::disk($this->disk)->exists($fullPath)) {
            return null;
        }

        return Storage::disk($this->disk)->get($fullPath);
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

        foreach ($paths as $path) {
            $fullPath = $this->basePath.'/'.$path;
            $fileName = basename($fullPath);

            if (Storage::disk($this->disk)->exists($fullPath)) {
                $tempFile = tempnam(sys_get_temp_dir(), 'tardis_');
                file_put_contents($tempFile, Storage::disk($this->disk)->get($fullPath));
                $zip->addFile($tempFile, $fileName);
                unlink($tempFile);
            }
        }

        $zip->close();

        return $zipPath;
    }

    public function getCollections(): array
    {
        return Media::distinct()->pluck('collection')->filter()->values()->toArray();
    }

    public function getMimeTypes(): array
    {
        return Media::distinct()
            ->pluck('mime_type')
            ->filter()
            ->map(fn (string $mime) => explode('/', $mime)[0])
            ->unique()
            ->values()
            ->toArray();
    }

    public function filterByMimeType(string $mimeType): Collection
    {
        $files = $this->listFiles();

        return $files->filter(function ($file) use ($mimeType) {
            return str_starts_with($file['type'], $mimeType.'/');
        })->values();
    }

    public function getFileInfo(string $path): ?array
    {
        $fullPath = $this->basePath.'/'.$path;
        $storage = Storage::disk($this->disk);

        if (! $storage->exists($fullPath)) {
            return null;
        }

        return [
            'name' => basename($fullPath),
            'path' => $fullPath,
            'relative_path' => Str::after($fullPath, $this->basePath.'/'),
            'size' => $storage->fileSize($fullPath),
            'mime_type' => $storage->mimeType($fullPath),
            'url' => $storage->url($fullPath),
            'last_modified' => $storage->lastModified($fullPath),
        ];
    }

    public function search(string $query, string $path = ''): Collection
    {
        $files = $this->listFiles($path);

        return $files->filter(function ($file) use ($query) {
            return str_contains(strtolower($file['name']), strtolower($query));
        })->values();
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
