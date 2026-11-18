<?php

declare(strict_types=1);

namespace Tardis\Demo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Sample content installed by `tardis:install --demo`. */
class DemoPost extends Model
{
    protected $table = 'tardis_demo_posts';

    protected $fillable = ['title', 'slug', 'category_id', 'excerpt', 'body', 'image', 'status', 'featured', 'published_at'];

    protected function casts(): array
    {
        return ['featured' => 'boolean', 'published_at' => 'datetime'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DemoCategory::class, 'category_id');
    }
}
