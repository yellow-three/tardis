<?php

declare(strict_types=1);

namespace Tardis\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tardis\Auth\BreadAuthorization;
use Tardis\Bread\ActionManager;
use Tardis\Bread\Api\ApiFields;
use Tardis\Bread\BreadDefinition;
use Tardis\Bread\BreadManager;
use Tardis\Bread\BreadQuery;
use Tardis\Bread\BreadSaver;
use Tardis\Bread\FieldValidationRules;
use Tardis\Bread\MissingColumnsException;

/**
 * A JSON API generated from the BREAD definitions (tardis.api). It answers
 * with the same definition, validation, scope, owner_column and field
 * abilities as the panel, and asks the same authorization for every action:
 * browse, read, add, edit, delete.
 */
class BreadApiController
{
    public function __construct(protected BreadAuthorization $auth) {}

    /** The BREADs this user may browse, with their fields. */
    public function definitions(): JsonResponse
    {
        $data = app(BreadManager::class)->all()
            ->filter(fn (BreadDefinition $definition, string $slug) => $this->exposed($slug) && BreadDefinition::isModelClass($definition->model)
                && $this->auth->allows('browse', $slug))
            ->map(fn (BreadDefinition $definition, string $slug) => [
                'slug' => $slug,
                'name' => $definition->resolvedName(),
                'name_plural' => $definition->resolvedNamePlural(),
                'fields' => (new ApiFields($definition))->schema(),
            ])
            ->values();

        return response()->json(['data' => $data]);
    }

    public function index(Request $request, string $slug): JsonResponse
    {
        $definition = $this->definition($slug);
        $this->auth->authorize('browse', $slug);

        $query = new BreadQuery($definition);
        $perPage = max(1, min((int) $request->integer('per_page', (int) config('tardis.api.per_page', 15)), (int) config('tardis.api.max_per_page', 100)));
        $trashed = in_array($request->query('trashed'), BreadQuery::TRASHED, true) ? (string) $request->query('trashed') : 'without';

        $page = $query->build(
            (string) $request->query('search', ''),
            is_string($request->query('sort')) ? $request->query('sort') : null,
            $request->query('direction') === 'desc' ? 'desc' : 'asc',
            $trashed,
            [],
            array_values(array_filter((array) $request->query('filter', []), 'is_string')),
        )->paginate($perPage)->withQueryString();

        $fields = new ApiFields($definition);

        return response()->json([
            'data' => collect($page->items())->map(fn (Model $record) => $fields->serialize($record))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            'links' => [
                'next' => $page->nextPageUrl(),
                'prev' => $page->previousPageUrl(),
            ],
        ]);
    }

    public function show(string $slug, string $id): JsonResponse
    {
        $definition = $this->definition($slug);
        $record = $this->record($definition, $id);
        $this->auth->authorize('read', $slug, $record);

        return response()->json(['data' => (new ApiFields($definition))->serialize($record)]);
    }

    public function store(Request $request, string $slug): JsonResponse
    {
        $definition = $this->definition($slug);
        $this->auth->authorize('add', $slug);

        $fields = (new ApiFields($definition))->writable('add');
        $form = $this->form($request, $fields, partial: false);

        if ($invalid = $this->invalid($fields, $form, 'add')) {
            return $invalid;
        }

        try {
            $record = app(BreadSaver::class)->create($slug, (string) $definition->model, $fields, $form);
        } catch (MissingColumnsException $e) {
            return $this->missingColumns($e);
        }

        return response()->json(['data' => (new ApiFields($definition))->serialize($record->refresh())], 201);
    }

    /** PUT replaces every writable field; PATCH changes only those sent. */
    public function update(Request $request, string $slug, string $id): JsonResponse
    {
        $definition = $this->definition($slug);
        $record = $this->record($definition, $id);
        $this->auth->authorize('edit', $slug, $record);

        $fields = (new ApiFields($definition))->writable('edit');
        $form = $this->form($request, $fields, partial: $request->isMethod('PATCH'));
        $fields = array_values(array_filter($fields, fn (array $field) => array_key_exists((string) $field['name'], $form)));

        if ($invalid = $this->invalid($fields, $form, 'edit')) {
            return $invalid;
        }

        try {
            app(BreadSaver::class)->update($slug, $record, $fields, $form);
        } catch (MissingColumnsException $e) {
            return $this->missingColumns($e);
        }

        return response()->json(['data' => (new ApiFields($definition))->serialize($record->refresh())]);
    }

    public function destroy(string $slug, string $id): JsonResponse
    {
        $definition = $this->definition($slug);
        $record = $this->record($definition, $id);
        $action = app(ActionManager::class)->find($slug, 'delete') ?? abort(404);

        $this->auth->authorize($action->getPermission(), $slug, $record);

        if (! $action->appliesTo($record)) {
            return response()->json(['message' => __('tardis::api.not_applicable')], 409);
        }

        $action->handle($record, $slug);

        return response()->json(null, 204);
    }

    protected function definition(string $slug): BreadDefinition
    {
        $definition = $this->exposed($slug) ? app(BreadManager::class)->find($slug) : null;

        if ($definition === null || ! BreadDefinition::isModelClass($definition->model)) {
            abort(response()->json(['message' => __('tardis::api.not_found')], 404));
        }

        return $definition;
    }

    /** Inside the BREAD's scope and owner restriction, or 404: not even its existence leaks. */
    protected function record(BreadDefinition $definition, string $id): Model
    {
        return $definition->query()->find($id)
            ?? abort(response()->json(['message' => __('tardis::api.not_found')], 404));
    }

    /** tardis.api.only limits the API to some BREADs; null exposes all. */
    protected function exposed(string $slug): bool
    {
        $only = config('tardis.api.only');

        return ! is_array($only) || in_array($slug, $only, true);
    }

    /**
     * The request body reduced to the writable fields. A full write (POST,
     * PUT) sends every field; a missing one is null, as on an empty form.
     *
     * @param  list<array<string, mixed>>  $fields
     * @return array<string, mixed>
     */
    protected function form(Request $request, array $fields, bool $partial): array
    {
        $form = [];

        foreach ($fields as $field) {
            $name = (string) ($field['name'] ?? '');

            if ($name !== '' && ($request->exists($name) || ! $partial)) {
                $form[$name] = $request->input($name);
            }
        }

        return $form;
    }

    /**
     * The panel's rules for these fields; errors are keyed by field name
     * (the panel nests them under "form.").
     *
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $form
     */
    protected function invalid(array $fields, array $form, string $context): ?JsonResponse
    {
        $validator = Validator::make(['form' => $form], FieldValidationRules::for($fields, $form, null, $context));

        if (! $validator->fails()) {
            return null;
        }

        $errors = [];

        foreach ($validator->errors()->messages() as $key => $messages) {
            $errors[str_starts_with($key, 'form.') ? substr($key, 5) : $key] = $messages;
        }

        return response()->json(['message' => __('tardis::api.invalid'), 'errors' => $errors], 422);
    }

    protected function missingColumns(MissingColumnsException $e): JsonResponse
    {
        return response()->json([
            'message' => __('tardis::bread.fields_required_by_database', ['fields' => implode(', ', $e->columns)]),
            'errors' => array_fill_keys($e->columns, [__('tardis::api.required_by_database')]),
        ], 422);
    }
}
