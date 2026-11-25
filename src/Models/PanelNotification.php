<?php

declare(strict_types=1);

namespace Tardis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|string $user_id
 * @property string $title
 * @property string|null $body
 * @property string|null $url
 * @property string $level
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 */
class PanelNotification extends Model
{
    public const TABLE = 'tardis_notifications';

    public const LEVELS = ['info', 'success', 'warning', 'error'];

    protected $table = self::TABLE;

    protected $fillable = ['user_id', 'title', 'body', 'url', 'level', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
