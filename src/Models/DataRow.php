<?php

namespace Tardis\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataRow extends Model
{
    protected $fillable = [
        'data_type_id',
        'field',
        'type',
        'display_name',
        'required',
        'browse',
        'read',
        'edit',
        'add',
        'delete',
        'details',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'bool',
            'browse' => 'bool',
            'read' => 'bool',
            'edit' => 'bool',
            'add' => 'bool',
            'delete' => 'bool',
            'details' => 'array',
            'order' => 'integer',
        ];
    }

    public function dataType(): BelongsTo
    {
        return $this->belongsTo(DataType::class);
    }
}
