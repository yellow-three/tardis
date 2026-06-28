<?php

namespace Tardis\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Tardis\Bread\Repositories\JsonBreadRepository;
use Tardis\Events\BreadCreated;
use Tardis\Events\BreadDeleted;
use Tardis\Events\BreadUpdated;

class BreadController extends Controller
{
    public function __construct(
        protected JsonBreadRepository $repository,
    ) {}

    public function browse(string $slug)
    {
        $bread = $this->repository->find($slug);
        if (! $bread) {
            abort(404);
        }

        $model = $bread->model;
        $query = $model::query();

        if ($bread->orderColumn) {
            $query->orderBy($bread->orderColumn, $bread->orderDirection);
        }

        if ($bread->searchKey && request()->has('search')) {
            $query->where($bread->searchKey, 'like', '%'.request('search').'%');
        }

        $items = $query->paginate(25);

        return view('tardis::pages.bread.browse', compact('bread', 'items'));
    }

    public function read(string $slug, string $id)
    {
        $bread = $this->repository->find($slug);
        if (! $bread) {
            abort(404);
        }

        $model = $bread->model;
        $item = $model::findOrFail($id);

        return view('tardis::pages.bread.read', compact('bread', 'item'));
    }

    public function add(string $slug)
    {
        $bread = $this->repository->find($slug);
        if (! $bread) {
            abort(404);
        }

        return view('tardis::pages.bread.add', compact('bread'));
    }

    public function store(Request $request, string $slug)
    {
        $bread = $this->repository->find($slug);
        if (! $bread) {
            abort(404);
        }

        $validationRules = [];
        foreach ($bread->fields as $field) {
            if ($field['add'] ?? true) {
                $rules = $field['validation'] ?? [];
                if (in_array('required', $rules)) {
                    $validationRules[$field['name']] = 'required';
                } else {
                    $validationRules[$field['name']] = 'nullable';
                }
            }
        }

        $validated = $request->validate($validationRules);

        $model = $bread->model;
        $item = $model::create($validated);

        BreadCreated::dispatch($slug, $item, $validated);

        return redirect()->route('tardis.bread.index', $slug)
            ->with('message', 'Item created successfully.');
    }

    public function edit(string $slug, string $id)
    {
        $bread = $this->repository->find($slug);
        if (! $bread) {
            abort(404);
        }

        $model = $bread->model;
        $item = $model::findOrFail($id);

        return view('tardis::pages.bread.edit', compact('bread', 'item'));
    }

    public function update(Request $request, string $slug, string $id)
    {
        $bread = $this->repository->find($slug);
        if (! $bread) {
            abort(404);
        }

        $validationRules = [];
        foreach ($bread->fields as $field) {
            if ($field['edit'] ?? true) {
                $rules = $field['validation'] ?? [];
                $validationRules[$field['name']] = in_array('required', $rules) ? 'required' : 'nullable';
            }
        }

        $validated = $request->validate($validationRules);

        $model = $bread->model;
        $item = $model::findOrFail($id);

        $old = $item->toArray();
        $item->update($validated);

        BreadUpdated::dispatch($slug, $item, $old, $validated);

        return redirect()->route('tardis.bread.index', $slug)
            ->with('message', 'Item updated successfully.');
    }

    public function destroy(string $slug, string $id)
    {
        $bread = $this->repository->find($slug);
        if (! $bread) {
            abort(404);
        }

        $model = $bread->model;
        $item = $model::findOrFail($id);
        $item->delete();

        BreadDeleted::dispatch($slug, $item);

        return redirect()->route('tardis.bread.index', $slug)
            ->with('message', 'Item deleted successfully.');
    }
}
