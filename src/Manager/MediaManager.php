<?php

namespace Tardis\Manager;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tardis\Models\Media;

class MediaManager
{
    public function upload(
        UploadedFile $file,
        string $collection = 'default',
        ?string $altText = null,
        ?string $disk = null,
    ): Media {
        $disk ??= config('tardis-media.disk', 'public');
        $name = Str::random(40).'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($collection, $name, $disk);

        return Media::create([
            'name' => $name,
            'original_name' => $file->getClientOriginalName(),
            'path' => $collection.'/'.$name,
            'disk' => $disk,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'alt_text' => $altText,
            'collection' => $collection,
            'created_by' => auth()->id(),
        ]);
    }

    public function delete(Media $media): bool
    {
        Storage::disk($media->disk)->delete($media->path);

        return (bool) $media->delete();
    }

    public function bulkDelete(array $ids): int
    {
        $count = 0;

        foreach (Media::whereIn('id', $ids)->get() as $media) {
            if ($this->delete($media)) {
                $count++;
            }
        }

        return $count;
    }

    public function downloadZip(array $ids, string $filename = 'media-export.zip'): string
    {
        $zip = new \ZipArchive;
        $zipPath = storage_path('app/temp/'.$filename);

        if (! is_dir(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create ZIP archive');
        }

        $media = Media::whereIn('id', $ids)->get();

        foreach ($media as $item) {
            $filePath = Storage::disk($item->disk)->path($item->path);

            if (file_exists($filePath)) {
                $zip->addFile($filePath, $item->original_name);
            }
        }

        $zip->close();

        return $zipPath;
    }

    public function search(array $filters = []): mixed
    {
        $query = Media::query();

        if (! empty($filters['search'])) {
            $query->where('original_name', 'like', '%'.$filters['search'].'%');
        }

        if (! empty($filters['collection'])) {
            $query->collection($filters['collection']);
        }

        if (! empty($filters['mime_type'])) {
            $query->where('mime_type', 'like', $filters['mime_type'].'%');
        }

        return $query->latest();
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
}
