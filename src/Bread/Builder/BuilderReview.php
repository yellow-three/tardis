<?php

declare(strict_types=1);

namespace Tardis\Bread\Builder;

/**
 * The advisories on the BREAD builder's review step, worked out from the
 * builder's state alone so the user can judge a BREAD before writing it.
 * None of them blocks saving.
 */
final class BuilderReview
{
    /**
     * @param  array<string, array<string, mixed>>  $fieldConfig
     * @param  list<string>  $orderedKeys
     * @param  array<string, array<string, mixed>>  $browseColumns
     * @param  list<string>  $readLayout
     * @param  array<int, array<string, mixed>>  $editTabs
     */
    public function __construct(
        private readonly string $slug,
        private readonly string $slugStatus,
        private readonly array $fieldConfig,
        private readonly array $orderedKeys,
        private readonly array $browseColumns,
        private readonly array $readLayout,
        private readonly string $searchKey,
        private readonly array $editTabs,
        private readonly bool $modelHasSoftDeletes,
        private readonly bool $softDelete,
    ) {}

    /**
     * The fields shown as list columns, in table order.
     *
     * @return list<string>
     */
    public function browseKeys(): array
    {
        return array_values(array_filter(
            $this->orderedKeys,
            fn ($key) => $this->browseColumns[$key]['visible'] ?? ($this->fieldConfig[$key]['browse'] ?? true)
        ));
    }

    public function countWhere(string $flag): int
    {
        return count(array_filter($this->fieldConfig, fn ($field) => (bool) ($field[$flag] ?? false)));
    }

    /**
     * @return list<array{level: string, message: string}>
     */
    public function warnings(): array
    {
        return [
            ...$this->slugWarnings(),
            ...$this->fieldWarnings(),
            ...$this->tabWarnings(),
            ...($this->modelHasSoftDeletes && ! $this->softDelete
                ? [self::warning('info', 'soft_delete_disabled')]
                : []),
        ];
    }

    /** @return list<array{level: string, message: string}> */
    private function slugWarnings(): array
    {
        if ($this->slug === '') {
            return [self::warning('error', 'slug_empty')];
        }

        return $this->slugStatus === 'taken' ? [self::warning('error', 'slug_taken', ['slug' => $this->slug])] : [];
    }

    /** @return list<array{level: string, message: string}> */
    private function fieldWarnings(): array
    {
        if ($this->fieldConfig === []) {
            return [self::warning('error', 'no_fields')];
        }

        $warnings = [];

        if ($this->browseKeys() === []) {
            $warnings[] = self::warning('warning', 'no_browse_columns');
        }

        if ($this->countWhere('add') === 0) {
            $warnings[] = self::warning('warning', 'no_add_field');
        }

        if ($this->countWhere('edit') === 0) {
            $warnings[] = self::warning('warning', 'no_edit_field');
        }

        if ($this->readLayout === []) {
            $warnings[] = self::warning('warning', 'empty_read_layout');
        }

        if ($this->searchKey !== '' && ! ($this->browseColumns[$this->searchKey]['searchable'] ?? false)) {
            $warnings[] = self::warning('warning', 'search_key_not_searchable', ['key' => $this->searchKey]);
        }

        return $warnings;
    }

    /** @return list<array{level: string, message: string}> */
    private function tabWarnings(): array
    {
        $warnings = [];

        foreach ($this->editTabs as $index => $tab) {
            if (($tab['fields'] ?? []) === []) {
                $warnings[] = self::warning('info', 'tab_without_fields', [
                    'tab' => $tab['name'] ?? __('tardis::builder.tab_n', ['number' => $index + 1]),
                ]);
            }
        }

        return $warnings;
    }

    /**
     * @param  array<string, mixed>  $replace
     * @return array{level: string, message: string}
     */
    private static function warning(string $level, string $key, array $replace = []): array
    {
        return ['level' => $level, 'message' => (string) __('tardis::builder.warnings.'.$key, $replace)];
    }
}
