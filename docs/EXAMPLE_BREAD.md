# Example BREAD Definition

A BREAD definition is a JSON file, `storage/tardis/bread/{slug}.json` (directory configurable with `tardis.bread.path`). The BREAD screens (`/admin/{slug}`, `/create`, `/{id}`, `/{id}/edit`) are rendered from it. Slugs may contain letters, digits, `-` and `_` only.

## Generate from a model

```bash
php artisan tardis:make-bread "App\Models\Post"
```

This writes `storage/tardis/bread/posts.json` with the fields detected from the model's `$fillable` list; a column that is `NOT NULL` in the schema becomes `required`. Pass a second argument to choose the slug: `tardis:make-bread "App\Models\Post" articles`.

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

- `fields[].type` must be one of the `Tardis\Bread\FieldType` values: `text`, `number`, `select`, `toggle`, `date`, `datetime`, `time`, `textarea`, `password`, `file`, `checkbox`, `radio`, `slider`, `slug`, `tags`, `markdown`, `code_editor`, `belongs_to_many`, `has_many`. Any other value is rejected when the definition is loaded.
- `validation` is a list of Laravel rules. A field without `required` is treated as nullable, so its other rules only apply when it has a value. Rules containing `|` (for example `regex:/^(a|b)$/`) are safe in the list form.
- `browse` / `read` / `edit` / `add` choose where a field appears; each defaults to `true`.
- Relation fields use `relation` (the Eloquent relation name); `translatable: true` stores a locale map (see `tardis.locales`).

## Reading definitions

```php
use Tardis\Bread\BreadManager;

app(BreadManager::class)->find('posts'); // ?BreadDefinition
app(BreadManager::class)->all();         // Collection of BreadDefinition
```

## Legacy PHP definitions

`config/bread/{slug}.php` files from earlier versions are no longer read at runtime. Convert them with `php artisan tardis:bread:migrate` (see the README).

## Permissions

Each page asks the enabled `AuthorizationPlugin` for `"{action} {slug}"` — `browse posts`, `read posts`, `add posts`, `edit posts`, `delete posts`. With no authorization plugin enabled every logged-in user is allowed; see README → Authentication and authorization.
