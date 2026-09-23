<?php

namespace Tardis\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\ModelReflector;
use Tardis\Bread\Sources\ConfigBreadSource;

class TardisMakeBreadCommand extends Command
{
    protected $signature = 'tardis:make-bread
        {model : The fully qualified model class (e.g., App\\Models\\Post)}
        {slug? : BREAD slug (default: plural snake case of the model basename)}';

    protected $description = 'Create a BREAD config file from an Eloquent model';

    public function handle(ConfigBreadSource $source): int
    {
        $model = $this->argument('model');

        if (! class_exists($model)) {
            $this->components->error("Model class [{$model}] does not exist.");

            return self::FAILURE;
        }

        $slug = $this->argument('slug')
            ?: Str::plural(Str::snake(class_basename($model)));

        $analysis = ModelReflector::analyze($model);
        $fields = $this->buildFields($model, $analysis['table'] ?? null);

        $definition = BreadDefinition::fromArray([
            'slug' => $slug,
            'model' => $model,
            'name' => Str::headline(class_basename($model)),
            'name_plural' => Str::headline(Str::plural(class_basename($model))),
            'fields' => $fields,
            'soft_delete' => $analysis['softDelete'] ?? false,
            'order_column' => 'id',
            'order_direction' => 'asc',
            'search_key' => 'id',
        ]);

        $source->save($definition);

        $target = $source->path().'/'.$slug.'.php';

        $this->components->info("BREAD definition [{$slug}] created successfully.");
        $this->components->twoColumnDetail('Config File', $target);
        $this->components->twoColumnDetail('Model', $model);
        $this->components->twoColumnDetail('Fields Detected', (string) count($fields));

        $this->newLine();
        $this->components->bulletList([
            "Edit {$target} to tweak fields, labels, validation and layout.",
            'The BREAD screens pick the definition up automatically from the config file.',
        ]);

        return self::SUCCESS;
    }

    /**
     * Build a fields array from the model fillable list and DB schema.
     *
     * The type returned by the model reflector is normalised to a supported
     * FieldType value, and nullability from Schema::getColumns drives the
     * required flag.
     */
    protected function buildFields(string $model, ?string $table): array
    {
        $fields = ModelReflector::getFields($model);

        if ($table === null || $table === '') {
            return $this->normalizeFields($fields);
        }

        $columns = Schema::getColumns($table);

        if ($columns === []) {
            return $this->normalizeFields($fields);
        }

        $nullability = collect($columns)
            ->mapWithKeys(fn (array $column) => [
                $column['name'] => $column['nullable'] ?? true,
            ]);

        foreach ($fields as $name => $field) {
            if ($nullability->has($name)) {
                $fields[$name]['required'] = ! $nullability[$name];
            }
        }

        return $this->normalizeFields($fields);
    }

    /**
     * Map reflector-only types (image, email, simple_array) onto the
     * supported FieldType values so the resulting config always validates.
     */
    protected function normalizeFields(array $fields): array
    {
        $normalised = [
            'image' => 'file',
            'email' => 'text',
            'simple_array' => 'tags',
        ];

        foreach ($fields as $name => $field) {
            $field['type'] = $normalised[$field['type'] ?? 'text'] ?? $field['type'] ?? 'text';
            $fields[$name] = $field;
        }

        return $fields;
    }
}
