<?php

declare(strict_types=1);

namespace Tardis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One user's two-factor setup. The secret is encrypted with the app key;
 * recovery codes are stored hashed and shown only once.
 *
 * @property int $id
 * @property int|string $user_id
 * @property string $secret
 * @property list<string>|null $recovery_codes
 * @property Carbon|null $confirmed_at
 * @property int|null $last_used_step
 */
class TwoFactor extends Model
{
    public const TABLE = 'tardis_two_factor';

    protected $table = self::TABLE;

    protected $fillable = ['user_id', 'secret', 'recovery_codes', 'confirmed_at', 'last_used_step'];

    protected $hidden = ['secret', 'recovery_codes'];

    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'recovery_codes' => 'array',
            'confirmed_at' => 'datetime',
            'last_used_step' => 'integer',
        ];
    }
}
