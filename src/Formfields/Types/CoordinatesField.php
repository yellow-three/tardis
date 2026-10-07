<?php

namespace Tardis\Formfields\Types;

use Tardis\Formfields\Formfield;

/**
 * A latitude/longitude pair. Stored as a single "lat,lng" column, but edited
 * as two inputs so a value never arrives as one unparseable string.
 */
class CoordinatesField extends Formfield
{
    public function type(): string
    {
        return 'coordinates';
    }

    public function render(): string
    {
        return 'tardis::formfields.coordinates';
    }

    public function add(mixed $value): mixed
    {
        return $this->edit($value);
    }

    /**
     * A stored "41.0082,28.9784" becomes ['lat' => '41.0082', 'lng' => '28.9784'].
     *
     * @return array{lat: string, lng: string}
     */
    public function edit(mixed $value): mixed
    {
        [$lat, $lng] = $this->parts($value);

        return ['lat' => $lat, 'lng' => $lng];
    }

    /**
     * The two inputs become one column again; both blank persists null rather
     * than an empty string so "no location" is unambiguous.
     */
    public function store(mixed $value): mixed
    {
        return $this->stringify($value);
    }

    public function browse(mixed $value): mixed
    {
        return $this->stringify($value);
    }

    public function read(mixed $value): mixed
    {
        return $this->stringify($value);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parts(mixed $value): array
    {
        if (is_array($value)) {
            return [
                trim((string) ($value['lat'] ?? '')),
                trim((string) ($value['lng'] ?? '')),
            ];
        }

        if (is_string($value) && str_contains($value, ',')) {
            [$lat, $lng] = explode(',', $value, 2);

            return [trim($lat), trim($lng)];
        }

        return ['', ''];
    }

    private function stringify(mixed $value): ?string
    {
        if (is_array($value)) {
            $lat = trim((string) ($value['lat'] ?? ''));
            $lng = trim((string) ($value['lng'] ?? ''));

            return ($lat === '' && $lng === '') ? null : $lat.','.$lng;
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
