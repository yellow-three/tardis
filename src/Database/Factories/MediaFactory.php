<?php

namespace Tardis\Media\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tardis\Media\Models\Media;

class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word().'.'.$this->faker->fileExtension(),
            'original_name' => $this->faker->word().'.'.$this->faker->fileExtension(),
            'path' => $this->faker->filePath(),
            'disk' => 'public',
            'mime_type' => $this->faker->mimeType(),
            'size' => $this->faker->numberBetween(100, 999999),
            'alt_text' => $this->faker->sentence(),
            'collection' => $this->faker->word(),
            'created_by' => null,
        ];
    }
}
