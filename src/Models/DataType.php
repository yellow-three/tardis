<?php

namespace Tardis\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataType extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'model',
        'icon',
        'model_class',
        'controller',
        'policy',
        'order_column',
        'order_direction',
        'default_search_key',
        'description',
        'server_side',
        'generate_permissions',
        'generate_model',
        'soft_delete',
    ];

    protected function casts(): array
    {
        return [
            'generate_permissions' => 'bool',
            'generate_model' => 'bool',
            'soft_delete' => 'bool',
        ];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(DataRow::class, 'data_type_id');
    }
}
