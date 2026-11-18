# Example BREAD Definition

A BREAD definition is a JSON file, `storage/tardis/bread/{slug}.json` (directory configurable with `tardis.bread.path`). The BREAD screens (`/admin/{slug}`, `/create`, `/{id}`, `/{id}/edit`) are rendered from it. Slugs may contain letters, digits, `-` and `_` only.

## Generate from a model

```bash
php artisan tardis:make:bread "App\Models\Post"
```

This writes `storage/tardis/bread/posts.json` with the fields detected from the model's `$fillable` list; a column that is `NOT NULL` in the schema becomes `required`. Pass a second argument to choose the slug: `tardis:make:bread "App\Models\Post" articles`.

You can also build and edit definitions in the browser at `/admin/bread` (BREAD builder). Every save keeps a timestamped backup and the manage screen can restore one.

## Or write it by hand

`storage/tardis/bread/posts.json`:

```json
{
    "slug": "posts",
    "model": "App\\Models\\Post",
    "name": "Post",
    "name_plural": "Posts",
    "order_column": "created_at",
    "order_direction": "desc",
    "search_key": "title",
    "fields": [
        {
            "name": "title",
            "type": "text",
            "label": "Title",
            "browse": true, "read": true, "edit": true, "add": true,
            "validation": ["required", "string", "max:255"]
        },
        {
            "name": "slug",
            "type": "slug",
            "label": "Slug",
            "browse": true, "read": true, "edit": true, "add": true,
            "validation": ["required", "max:255", "unique:posts,slug"]
        },
        {
            "name": "content",
            "type": "textarea",
            "label": "Content",
            "browse": false, "read": true, "edit": true, "add": true,
            "validation": ["required"]
        },
        {
            "name": "published_at",
            "type": "datetime",
            "label": "Published At",
            "browse": true, "read": true, "edit": true, "add": true,
            "validation": ["nullable", "date"]
        }
    ]
}
```

Notes:

- `fields[].type` must be a type registered in `FormfieldManager`: the built-in `text`, `number`, `select`, `toggle`, `date`, `datetime`, `time`, `textarea`, `password`, `file`, `image`, `checkbox`, `radio`, `slider`, `slug`, `tags`, `markdown`, `code_editor`, `belongs_to`, `has_one`, `belongs_to_many`, `has_many`, plus anything a host registers with `registerType()`. Any other value is rejected when the definition is loaded or saved.
- Optional definition keys: `components` (`{"browse": "acme::pages.post-list"}` replaces a page's Livewire component), `policy` (ability word, default the slug) and `scope` (model scope applied to listings and every record lookup).
- Slugs reserved for built-in screens (`settings`, `users`, `bread`, `media`, `database`, `plugins`, `login`, …) cannot be used.
- `validation` is a list of Laravel rules. A field without `required` is treated as nullable, so its other rules only apply when it has a value. Rules containing `|` (for example `regex:/^(a|b)$/`) are safe in the list form.
- `image` takes the file field's keys (`disk`, `directory`, `mimes`, `max_size`) plus `resize` (`{"width": 1600, "height": null}`, fitted, never enlarged), `quality` (1–100, default 90) and `thumbnails` (`[{"name": "small", "width": 300, "height": 300, "crop": true}]`, stored next to the image as `photo-small.jpg`). Processing uses PHP's GD extension; without it the original is stored and no thumbnails are made.
- `order_column` sorts the list; with it set, the list offers an **Order** page (`/admin/{slug}/order`, needs `edit {slug}`) where records are dragged into order and saved as positions 1, 2, 3… in that column. `order_display_column` names the column shown for each record there (default: `search_key`).
- Field `help` (or Voyager's `description`) is shown under the control, `placeholder` inside it, and `default` fills the create form. `timestamp` is accepted as a type name and becomes `datetime`.
- `browse` / `read` / `edit` / `add` choose where a field appears; each defaults to `true`.
- Relation fields use `relation` (the Eloquent relation name), `model` (the related model) and `label_column` (the column shown for a related record, default `name`); `translatable: true` stores a locale map (see `tardis.locales`).
  - `belongs_to` is named after the foreign key column (`category_id`) and stores the picked record's key; with `relation` set, the listing shows the related label without an extra query per row.
  - `has_one` is named after the relation (`profile`); picking a record sets its foreign key to this record and unlinks the one linked before, so that foreign key column must be nullable.
  - Every picker lists only what the related BREAD would show (its `scope` and the user's `browse` permission), and a submitted key outside that list is not stored.
  - The BREAD builder's *Relationships* tab adds a detected relationship to the form; the definition no longer keeps a separate `relationships` list.

## Reading definitions

```php
use Tardis\Bread\BreadManager;

app(BreadManager::class)->find('posts'); // ?BreadDefinition
app(BreadManager::class)->all();         // Collection of BreadDefinition
```

## Legacy PHP definitions

`config/bread/{slug}.php` files from earlier versions are no longer read at runtime. Convert them with `php artisan tardis:bread:import-legacy` (see the README).

## Permissions

Each page asks the enabled `AuthorizationPlugin` for `"{action} {slug}"` — `browse posts`, `read posts`, `add posts`, `edit posts`, `delete posts`. With no authorization plugin enabled every logged-in user is allowed; see README → Authentication and authorization.
