<?php

declare(strict_types=1);

namespace Tardis\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Tardis\Database\Factories\MediaFactory;

/**
 * @property int $id
 * @property string $name
 * @property string|null $original_name
 * @property string $path
 * @property string|null $thumbnail_path
 * @property string $disk
 * @property string|null $mime_type
 * @property int $size
 * @property string|null $alt_text
 * @property string|null $collection
 * @property int|null $created_by
 * @property string|null $caption
 * @property string|null $description
 * @property-read string $url
 * @property-read string|null $thumbnail_url
 * @property-read string $formatted_size
 */
class Media extends Model
{
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return MediaFactory::new();
    }

    protected static function booted(): void
    {
        // The thumbnail belongs to the row: deleting a media record (the edit
        // screen does) removes it too. Bulk deletes go through MediaManager.
        static::deleting(function (Media $media): void {
            if (is_string($media->thumbnail_path) && $media->thumbnail_path !== '') {
                Storage::disk($media->disk)->delete($media->thumbnail_path);
            }
        });
    }

    protected $table = 'tardis_media';

    protected $fillable = [
        'name', 'original_name', 'path', 'thumbnail_path', 'disk',
        'mime_type', 'size', 'alt_text', 'collection', 'created_by', 'caption', 'description',
    ];

    // "url" comes from getUrlAttribute(); Larastan reads the url() method
    // next to it as the attribute and does not find one.
    // @phpstan-ignore rules.modelAppends
    protected $appends = ['url', 'thumbnail_url', 'formatted_size'];

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function getUrlAttribute(): string
    {
        return $this->url();
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (! str_starts_with((string) $this->mime_type, 'image/')) {
            return null;
        }

        if (is_string($this->thumbnail_path) && $this->thumbnail_path !== '') {
            return Storage::disk($this->disk)->url($this->thumbnail_path);
        }

        return Storage::disk($this->disk)->url($this->path);
    }

    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.($units[$i] ?? 'B');
    }

    public function scopeCollection($query, string $collection)
    {
        return $query->where('collection', $collection);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }
}
