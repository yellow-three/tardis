<?php

namespace Tardis\Media\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tardis\Media\Database\Factories\MediaFactory;

class Media extends Model
{
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return MediaFactory::new();
    }

    protected $table = 'tardis_media';

    protected $fillable = [
        'name', 'original_name', 'path', 'disk',
        'mime_type', 'size', 'alt_text', 'collection', 'created_by',
    ];

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
        if (str_starts_with($this->mime_type, 'image/')) {
            return Storage::disk($this->disk)->url($this->path);
        }

        return null;
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

    public static function upload(
        $file,
        string $collection = 'default',
        ?string $altText = null,
        ?string $disk = null,
    ): self {
        $disk = $disk ?? config('tardis-media.disk', 'public');
        $name = Str::random(40).'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($collection, $name, $disk);

        return static::create([
            'name' => $name,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'disk' => $disk,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'alt_text' => $altText,
            'collection' => $collection,
            'created_by' => Auth::id(),
        ]);
    }
}
