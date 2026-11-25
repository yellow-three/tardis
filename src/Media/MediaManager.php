<?php

declare(strict_types=1);

namespace Tardis\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\StorageAttributes;
use League\MimeTypeDetection\ExtensionMimeTypeDetector;
use Tardis\Bread\ActivityLogger;
use Tardis\Models\Media;
use Tardis\Support\ModelResolver;

class MediaManager
{
    protected string $disk;

    protected string $basePath;

    /** Thumbnails live here, keyed by media id, outside the browsable folder. */
    public const THUMBNAIL_ROOT = '.tardis-thumbnails';

    private ?bool $thumbnailColumn = null;

    public function __construct()
    {
        $this->disk = config('tardis.media.disk', 'public');
        $this->basePath = config('tardis.media.path', 'media');
    }

    public function upload(
        UploadedFile $file,
        string $path = '',
        ?string $altText = null,
        ?string $filenameTemplate = null,
    ): Media {
        $this->assertAllowedExtension($file->getClientOriginalName(), 'upload');

        $path = Str::finish($this->fullPath($path), '/');

        $name = $this->resolveFilenameTemplate($filenameTemplate, $file, $path);

        // A filename template may end in a different extension than the file.
        $this->assertAllowedExtension($name, 'upload');

        SvgSanitizer::sanitizeUpload($file);

        // storeAs() joins with its own separator, so it must not receive a trailing slash.
        $storedPath = $file->storeAs(rtrim($path, '/'), $name, $this->disk);

        $media = ModelResolver::media()::create([
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

        $this->makeThumbnail($media);

        $this->audit('uploaded', ['path' => $storedPath, 'size' => $file->getSize()], $media->getKey());

        return $media;
    }

    /**
     * Store a thumbnail of an image upload, sized by tardis.media.thumbnail_size,
     * outside the media folder so the browser never lists it. Without GD, or for
     * a format it cannot read, there is none and the original is shown.
     */
    public function makeThumbnail(Media $media): void
    {
        $processor = app(ImageProcessor::class);

        if (! $processor->supports($media->mime_type) || ! $this->hasThumbnailColumn()) {
            return;
        }

        $storage = Storage::disk($media->disk);
        $size = (array) config('tardis.media.thumbnail_size', []);

        $bytes = $processor->resize(
            (string) $storage->get($media->path),
            (string) $media->mime_type,
            isset($size['width']) ? (int) $size['width'] : 300,
            isset($size['height']) ? (int) $size['height'] : 300,
            (int) config('tardis.media.thumbnail_quality', 85),
        );

        if ($bytes === null) {
            return;
        }

        $extension = pathinfo($media->path, PATHINFO_EXTENSION);
        $path = self::THUMBNAIL_ROOT.'/'.$media->getKey().($extension !== '' ? '.'.strtolower($extension) : '');

        $storage->put($path, $bytes);
        $media->forceFill(['thumbnail_path' => $path])->saveQuietly();
    }

    /**
     * Delete the thumbnails of the media rows a query matches, before the rows.
     */
    protected function deleteThumbnails(mixed $query): void
    {
        if (! $this->hasThumbnailColumn()) {
            return;
        }

        $query->clone()->whereNotNull('thumbnail_path')->get(['disk', 'thumbnail_path'])
            ->filter(fn ($media) => is_string($media->thumbnail_path) && $media->thumbnail_path !== '')
            ->each(fn ($media) => Storage::disk($media->disk)->delete($media->thumbnail_path));
    }

    /**
     * Until the 2026_11_19 migration has run there is nowhere to record a
     * thumbnail; uploads then work as before, without one.
     */
    protected function hasThumbnailColumn(): bool
    {
        return $this->thumbnailColumn ??= Schema::hasColumn((new (ModelResolver::media()))->getTable(), 'thumbnail_path');
    }

    /**
     * The entries of one folder, folders and files, sorted by name.
     *
     * Everything comes from the listing itself: size and modification time
     * are part of what Flysystem returns for each file, and the MIME type is
     * read from the extension. A folder of a thousand files on S3 is one
     * listing request, not three requests per file. Only a driver that leaves
     * the size out of its listing is asked for it.
     */
    public function listFiles(string $path = ''): Collection
    {
        $path = Str::finish($this->fullPath($path), '/');
        $storage = Storage::disk($this->disk);
        $mimes = new ExtensionMimeTypeDetector;

        return collect($storage->listContents($path))
            // Hidden entries (the thumbnail folder when the media path is the
            // disk root) are never shown.
            ->reject(fn ($item) => str_starts_with(basename((string) $item['path']), '.'))
            ->map(function ($item) use ($storage, $mimes) {
                $isDir = $item instanceof DirectoryAttributes || str_ends_with($item['path'], '/');
                $size = 0;
                $lastModified = null;
                $mimeType = 'directory';

                if (! $isDir) {
                    $size = $item instanceof FileAttributes ? $item->fileSize() : null;
                    $lastModified = $item instanceof StorageAttributes ? $item->lastModified() : null;
                    $mimeType = ($item instanceof FileAttributes ? $item->mimeType() : null)
                        ?? $mimes->detectMimeTypeFromPath($item['path'])
                        ?? 'application/octet-stream';

                    if ($size === null) {
                        try {
                            $size = $storage->fileSize($item['path']);
                        } catch (\Throwable) {
                            $size = 0;
                        }
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
    }

    public function createDirectory(string $path, string $name): bool
    {
        $this->assertValidName($name, 'createDirectory');

        $created = Storage::disk($this->disk)->makeDirectory($this->fullPath($path).'/'.$name);

        if ($created) {
            $this->audit('directory_created', ['path' => $this->fullPath($path).'/'.$name]);
        }

        return $created;
    }

    public function deleteFile(string $path): bool
    {
        $fullPath = $this->fullPath($path);

        $deleted = Storage::disk($this->disk)->delete($fullPath);

        $rows = ModelResolver::media()::query()->where('path', $fullPath);
        $this->deleteThumbnails($rows);
        $rows->delete();

        $this->audit('deleted', ['path' => $fullPath]);

        return $deleted;
    }

    public function deleteDirectory(string $path): bool
    {
        $fullPath = $this->fullPath($path);

        $deleted = Storage::disk($this->disk)->deleteDirectory($fullPath);

        $rows = ModelResolver::media()::query()->whereRaw("path like ? escape '!'", [$this->likePrefix($fullPath.'/')]);
        $this->deleteThumbnails($rows);
        $rows->delete();

        $this->audit('directory_deleted', ['path' => $fullPath]);

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

        // Upload validation is pointless if a rename can turn photo.jpg into
        // shell.php on a public disk, so files keep to the same list.
        if (! $wasDirectory) {
            $this->assertAllowedExtension($newName, 'rename');
        }

        if (! $storage->move($source, $target)) {
            return false;
        }

        if ($wasDirectory) {
            $this->syncDirectoryRename($source, $target);
        } else {
            // name tracks the current basename; original_name keeps the uploaded one.
            ModelResolver::media()::query()->where('path', $source)->update([
                'path' => $target,
                'name' => $newName,
            ]);
        }

        $this->audit('renamed', ['from' => $source, 'to' => $target]);

        return true;
    }

    /**
     * Media changes go to the activity log next to record changes (R50).
     *
     * @param  array<string, mixed>  $values
     */
    protected function audit(string $action, array $values, int|string $id = 0): void
    {
        app(ActivityLogger::class)->audit('tardis.media', $action, $values, $id);
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

    /**
     * Extensions a stored file may end in (tardis.media.allowed_mimes), lower
     * case. An empty list means the host turned the restriction off.
     *
     * @return list<string>
     */
    public function allowedExtensions(): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn ($extension) => strtolower(ltrim(trim((string) $extension), '.')),
            (array) config('tardis.media.allowed_mimes', []),
        ))));
    }

    public function isAllowedFileName(string $name): bool
    {
        $allowed = $this->allowedExtensions();

        if ($allowed === []) {
            return true;
        }

        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        return $extension !== '' && in_array($extension, $allowed, true);
    }

    /**
     * The check lives here, not only in the screens that call this class, so a
     * new upload or rename surface cannot forget it.
     */
    protected function assertAllowedExtension(string $name, string $operation): void
    {
        if (! $this->isAllowedFileName($name)) {
            throw new InvalidArgumentException(
                $operation.'() name ['.$name.'] has an extension outside tardis.media.allowed_mimes.'
            );
        }
    }

    /**
     * A LIKE pattern matching everything under $prefix. Folder names may hold
     * _ and %, which LIKE would otherwise read as wildcards ("a_b" matching
     * "axb"); "!" is the escape character, as in BreadQuery.
     */
    protected function likePrefix(string $prefix): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $prefix).'%';
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

        ModelResolver::media()::query()
            ->whereRaw("path like ? escape '!'", [$this->likePrefix($prefix)])
            ->orWhereRaw("collection like ? escape '!'", [$this->likePrefix($prefix)])
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

    protected function resolveFilenameTemplate(?string $template, UploadedFile $file, string $path): string
    {
        $originalName = $file->getClientOriginalName();
        $pathinfo = pathinfo($originalName);
        $extension = isset($pathinfo['extension']) ? '.'.$pathinfo['extension'] : '';
        $base = $pathinfo['filename'];

        if (empty($template)) {
            return $this->getUniqueFileName($file, $path);
        }

        // Every token must be on the allow-list, checked before substitution
        // so substituted values are never re-scanned as tokens.
        if (preg_match_all('/\{([^{}]*)\}/', $template, $matches) > 0) {
            foreach ($matches[1] as $token) {
                if (preg_match('/^(?:name|filename|ext|uid|random:\d+|date:.+)$/', $token) !== 1) {
                    throw new InvalidArgumentException('Template contains unknown token');
                }
            }
        }

        $result = str_replace(
            ['{name}', '{filename}', '{ext}', '{uid}'],
            [$base, $originalName, ltrim($extension, '.'), (string) (auth()->id() ?? 'guest')],
            $template,
        );

        $result = preg_replace_callback('/\{date:([^}]+)\}/', function ($m) {
            return now()->format($m[1]);
        }, $result);

        $result = preg_replace_callback('/\{random:(\d+)\}/', function ($m) {
            $n = (int) $m[1];
            $n = max(1, min($n, 64));
            $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $rand = '';
            for ($i = 0; $i < $n; $i++) {
                $rand .= $chars[random_int(0, strlen($chars) - 1)];
            }

            return $rand;
        }, $result);

        // Validate the substituted result: the values that went in are not
        // trusted, so the same rules the template got are applied to the output.
        if (str_contains($result, "\0")) {
            throw new InvalidArgumentException('Filename contains null bytes');
        }

        if (str_contains($result, '\\')) {
            throw new InvalidArgumentException('Filename cannot contain backslashes');
        }

        if (str_starts_with($result, '/')) {
            throw new InvalidArgumentException('Filename cannot start with /');
        }

        if (in_array('..', explode('/', str_replace('\\', '/', $result)), true)) {
            throw new InvalidArgumentException('Filename cannot contain ".." path segments');
        }

        // The stored file always keeps the uploaded file's extension: a template
        // decides the name, never the type, so "{name}-{ext}" cannot leave a
        // file ending in "-png" and a template cannot produce "x.php".
        if ($extension !== '' && strcasecmp('.'.pathinfo($result, PATHINFO_EXTENSION), $extension) !== 0) {
            $result .= $extension;
        }

        if ($extension && str_ends_with($result, $extension.$extension)) {
            $result = substr($result, 0, -strlen($extension));
        }

        $storage = Storage::disk($this->disk);
        $count = 0;
        $candidate = $result;
        while ($storage->exists($path.$candidate)) {
            $count++;
            $pi = pathinfo($result);
            $dir = $pi['dirname'] !== '.' ? $pi['dirname'].'/' : '';
            $ext = isset($pi['extension']) ? '.'.$pi['extension'] : $extension;
            $fn = $pi['filename'];
            $candidate = $dir.$fn.'_'.$count.$ext;
        }

        return $candidate;
    }

    protected function getUniqueFileName(UploadedFile $file, string $path): string
    {
        $name = $file->getClientOriginalName();
        $storage = Storage::disk($this->disk);
        $count = 0;

        while ($storage->exists($path.$name)) {
            $count++;
            $pathinfo = pathinfo($file->getClientOriginalName());
            $extension = isset($pathinfo['extension']) ? '.'.$pathinfo['extension'] : '';
            $name = $pathinfo['filename'].'_'.$count.$extension;
        }

        return $name;
    }
}
