# Customizing Tardis

Most changes need no fork: register something in a service provider of your application, or add a key to a BREAD definition. Every example below is exercised by `tests/Feature/CustomizationGuideTest.php`, so the code here is code that runs.

- [Actions on records](#actions-on-records)
- [A field type of your own](#a-field-type-of-your-own)
- [Computed and related values in listings](#computed-and-related-values-in-listings)
- [Replacing a BREAD screen](#replacing-a-bread-screen)
- [Overriding views](#overriding-views)
- [Panel routes of your own](#panel-routes-of-your-own)
- [Signing in with another guard](#signing-in-with-another-guard)
- [Soft deletes](#soft-deletes)
- [Export and bulk actions on every match](#export-and-bulk-actions-on-every-match)
- [Who sees what](#who-sees-what)

## Actions on records

An action is a button on each row of a BREAD list (and, when `$bulk` is true, on the selection too). Extend `Tardis\Bread\Action` and implement `handle()`:

```php
namespace App\Tardis;

use Illuminate\Database\Eloquent\Model;
use Tardis\Bread\Action;

class PublishAction extends Action
{
    public string $icon = 'check-circle';

    public ?string $permission = 'edit'; // checked as "edit {slug}"

    public bool $bulk = true;

    public function getTitle(): string
    {
        return __('Publish');
    }

    public function appliesTo(Model $record): bool
    {
        return $record->status !== 'published';
    }

    public function handle(Model $record, string $slug): mixed
    {
        return $record->update(['status' => 'published']);
    }
}
```

Register it for one BREAD, or for all of them with `'*'`:

```php
use Tardis\Facades\Tardis;

public function boot(): void
{
    Tardis::addAction('posts', \App\Tardis\PublishAction::class);
}
```

`Tardis::replaceAction('posts', 'delete', MyDelete::class)` swaps a built-in action (`delete`, `restore`, `force-delete`); `Tardis::manipulateActions('posts', fn ($actions) => $actions->reject(fn ($action) => $action->name() === 'force-delete'))` edits the list freely (it receives a collection of action objects). An action's name is its class name in kebab case without `Action` (`PublishAction` → `publish`).

## A field type of your own

A field type is a `Tardis\Formfields\Formfield` subclass: a `type()` name, a Blade view from `render()`, and hooks for each screen.

```php
namespace App\Tardis;

use Tardis\Formfields\Formfield;

class RatingField extends Formfield
{
    protected array $configurable = ['max'];

    public int $max = 5;

    public function type(): string
    {
        return 'rating';
    }

    public function render(): string
    {
        return 'tardis-fields.rating'; // resources/views/tardis-fields/rating.blade.php
    }

    public function browse(mixed $value): mixed
    {
        return str_repeat('★', (int) $value);
    }

    public function store(mixed $value): mixed
    {
        return max(0, min($this->max, (int) $value));
    }

    protected function extraViewData(): array
    {
        return ['max' => $this->max];
    }
}
```

The view receives `$model` (the `wire:model` path), `$id`, `$label`, `$disabled` and whatever `extraViewData()` returns:

```blade
<input type="range" min="0" max="{{ $max }}" id="{{ $id }}" wire:model="{{ $model }}" class="range" />
```

Register the type, then use it in any definition (`"type": "rating", "max": 10`); the builder lists it too:

```php
Tardis::formfields()->registerType('rating', \App\Tardis\RatingField::class);
```

Hooks: `browse()` / `read()` shape the value a list or detail page shows (return text, it is escaped), `edit()` / `add()` the value a form opens with, `store()` / `update()` what is written, and `stored()` / `updated()` run after the record is saved, for relations. Return `true` from `isRelation()` for a field that has no column of its own. A plugin can ship field types as a `FormfieldPlugin` (see [PLUGIN_GUIDE.md](PLUGIN_GUIDE.md)).

## Computed and related values in listings

A listing column reads `accessor` when the field has one, so a related or computed value needs no custom code:

```json
{"name": "author", "type": "text", "accessor": "author.name", "add": false, "edit": false}
```

For a model accessor (`getExcerptAttribute()`, or an `Attribute` cast), add a field named after the attribute with `add` and `edit` set to `false`: the list and the detail page read it like a column, and the forms leave it alone. For a related record that should be picked on the form, use a relation field instead (`belongs_to`, `has_one`, `belongs_to_many`, `has_many`; see [EXAMPLE_BREAD.md](EXAMPLE_BREAD.md)).

## Replacing a BREAD screen

A definition's `components` replaces the Livewire component behind any of `browse`, `add`, `edit`, `read` and `order` for that BREAD only. The URL stays the same; the override is registered under the stock route's name plus the slug (`tardis.bread.index.posts`, `tardis.bread.edit.posts`, …), ahead of the stock route.

```json
{"slug": "posts", "components": {"browse": "post-board"}}
```

Your component receives the same `slug` (and `id`) parameters as the stock one. It is a page like any other admin page, so it should declare `#[Layout('tardis::layouts.admin')]` and check `app(\Tardis\Auth\BreadAuthorization::class)->authorize('browse', $this->slug)` in `boot()`.

## Overriding views

`php artisan vendor:publish --tag=tardis-views` copies the layouts, Blade components, form field views, widgets and partials to `resources/views/vendor/tardis`; a file there wins over the package's. A published layout keeps `<x-tardis::head />` and `<x-tardis::scripts />`: they hold the order the assets must load in. Livewire pages are not published (Livewire reads them from the package only); replace a BREAD screen through `components`, and add your own pages as routes.

## Panel routes of your own

Pages of your own that belong inside the panel open their group through `Tardis\Http\PanelRoutes`, which applies the admin prefix, the `tardis.` name prefix and the panel middleware:

```php
use Illuminate\Support\Facades\Route;
use Tardis\Http\PanelRoutes;

PanelRoutes::panel(function () {
    Route::livewire('/reports', 'reports')->name('reports'); // /admin/reports, tardis.reports
});
```

Add a sidebar entry with `Tardis::menu()->addItems((new \Tardis\Menu\MenuItem('Reports', 'heroicon-o-chart-bar'))->route('tardis.reports'))`. A plugin adds routes through the `Provider\Routes` contract instead.

## Signing in with another guard

The built-in `SessionAuthenticationPlugin` uses the default guard. To sign in to the panel with another one, extend it and register it under its own key; the authentication plugin registered last is the one used:

```php
namespace App\Tardis;

use Tardis\Auth\SessionAuthenticationPlugin;

class StaffAuthentication extends SessionAuthenticationPlugin
{
    public function guard(): string
    {
        return 'staff';
    }

    public function name(): string
    {
        return 'staff-authentication';
    }
}
```

```php
$plugins = app(\Tardis\Plugins\PluginManager::class);
$plugins->register('staff-authentication', \App\Tardis\StaffAuthentication::class);
$plugins->enableByDefault('staff-authentication');
```

The users screen and the profile page work on `auth.providers.users.model`; point that, or `tardis.users.columns`, at the staff model's table if it differs.

## Soft deletes

When the model uses `SoftDeletes` and the definition has `"soft_delete": true` (the builder's *Enable soft delete* switch), the list gets a *trashed* filter (without / with / only) and the *restore* and *force delete* actions appear on trashed rows. Deleting stays a soft delete. Without the switch, trashed records stay hidden as Eloquent hides them.

## Export and bulk actions on every match

*Export CSV* on a BREAD list writes the columns the list shows, for the records the current search, filters and sort select. Password fields and the model's `$hidden` attributes are left out; set `tardis.bread.export` to `false` to remove the button. Ticking the header checkbox selects the page; *Select all N matching records* then makes a bulk action (`$bulk = true`) run on every match, each record authorised on its own, up to `tardis.bread.bulk_limit`.

## Who sees what

- `scope`: a model scope applied to the list, search, relation pickers and every record lookup, so a record outside it cannot be opened by URL either (`"scope": "published"`).
- `policy`: the word abilities are built from, when several BREADs should share permissions (`"policy": "content"` → `browse content`, `edit content`).
- A Laravel policy for the model keeps working: `Tardis\Policies\BasePolicy` answers through the same abilities the pages check.
