<?php

namespace Tardis\Core\Bread\Sources;

use Illuminate\Support\Collection;
use Tardis\Core\Models\DataType;

class DatabaseBreadSource implements BreadSource
{
    public function find(string $slug): ?array
    {
        $dataType = DataType::with('rows')->where('slug', $slug)->first();

        if ($dataType === null) {
            return null;
        }

        return $this->hydrate($dataType);
    }

    public function all(): Collection
    {
        return DataType::with('rows')
            ->get()
            ->keyBy('slug')
            ->map(fn ($dataType) => $this->hydrate($dataType));
    }

    protected function hydrate(DataType $dataType): array
    {
        return array_merge($dataType->toArray(), [
            'source' => 'database',
        ]);
    }
}
