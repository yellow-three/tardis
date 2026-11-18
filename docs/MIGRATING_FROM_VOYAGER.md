# Moving from Voyager to Tardis

Tardis covers the same ground as Voyager 1.x (BREAD screens generated from your models, a menu builder, settings, media, roles) on Laravel 13 and Livewire 4. This guide takes a Voyager site across.

## 1. Install Tardis next to Voyager

Both can live in the same application while you move: Voyager under its prefix (`/admin` by default), Tardis under its own. Give Tardis another prefix while both are installed:

```php
// config/tardis.php (php artisan vendor:publish --tag=tardis-config)
'admin' => ['prefix' => 'panel'],
```

```bash
composer require yellow-three/tardis:^0.1
php artisan tardis:install --email=you@example.com
```

Tardis's own tables are prefixed `tardis_`, so they never collide with Voyager's.

## 2. Import

```bash
php artisan tardis:import:voyager --dry-run
```

Read the report, then run it without `--dry-run`. `--only=bread,menus,settings,roles` imports part of it; `--connection=voyager` reads another database. What it does is described in the README (*Moving from Voyager*); below is what to check afterwards.

**Relation method names.** Voyager does not record the method name of a `hasOne`, `hasMany` or `belongsToMany` relationship. The import uses the usual name (`categories` for `belongsToMany` to `Category`) and lists every one in the report. Open the BREAD in the builder and correct `relation` where your model calls it something else. `belongsTo` relationships become a `belongs_to` field on the foreign key column and need no check.

**Unmapped items.** The report lists everything that has no Tardis equivalent:

| Voyager | In Tardis |
|---|---|
| `multiple_images` field | a `media_picker` field (several files from the media library), or `has_many` to an images table |
| image thumbnail by `scale` | a box of the same width; adjust `thumbnails` if you need the exact percentage |
| `morphTo` / `morphMany` relationships | not supported as fields; show them with a custom field type or a replaced screen |
| `browse_hooks`, `browse_compass` permissions | no hooks; `view system` covers the diagnostics |
| the `admin` menu | the panel builds its sidebar in code; hide, rename and add links in *Menus* |

**Models.** Definitions point at the model classes your data types named. If they used `TCG\Voyager\Models\…` (`Post`, `Page`, `Category`), copy those models into your application (`App\Models\Post`) and change `model` in the BREAD, since Voyager's classes go away with the package.

**Users and roles.** Roles arrive with their permissions mapped to Tardis abilities, and every user keeps their roles (from `user_roles` and `users.role_id`). Tardis does not use `users.role_id`; roles live in `tardis_role_user`. Check that your administrators can open the panel, or make one with `php artisan tardis:admin you@example.com`.

## 3. Replace Voyager calls in your code

| Voyager | Tardis |
|---|---|
| `setting('site.title')` | `setting('site.title')` (or `tardis_setting()`), same `group.key` |
| `menu('main')` (HTML) | `<x-tardis::site-menu name="main" />` |
| `menu('main', '_json')` | `menu('main')`: the items as a tree |
| `Voyager::image($path)` | `Storage::disk('public')->url($path)` |
| thumbnail `Voyager::image($post->getThumbnail($post->image, 'small'))` | `Storage::disk('public')->url(\Tardis\Formfields\Types\ImageField::thumbnailPath($post->image, 'small'))` |
| `Voyager::can('browse_posts')` | `app(\Tardis\Auth\BreadAuthorization::class)->allows('browse', 'posts')` |
| `TCG\Voyager\Traits\Translatable` | a field with `"translatable": true` stores a locale map in the column; read it with `\Tardis\Support\Translation::value($post->title)` |
| `voyager.*` route names | `tardis.*`: `tardis.bread.index` (with `slug`), `tardis.bread.edit`, `tardis.dashboard` |
| custom BREAD controller | `components` in the definition replaces a screen; actions add buttons ([CUSTOMIZATION.md](CUSTOMIZATION.md)) |
| `voyager-*` icons | Heroicon names (`document-text`, `photo`, …) in the builder's icon picker |
| BREAD events (`BreadDataAdded` …) | `Tardis\Events\BreadRecordCreated`, `BreadRecordUpdated`, `BreadRecordDeleted` |

Translated content needs a data migration: Voyager keeps translations in its `translations` table, while Tardis keeps a locale map in the column itself. Copy each translation into the column (`{"en": "…", "tr": "…"}`) before you remove Voyager.

## 4. Things that work differently

- **BREAD definitions are files**, `storage/tardis/bread/{slug}.json`, with a timestamped backup on every change, not rows in `data_types`. Keep that directory in your deploys and backups (or set `tardis.bread.path`). With `route:cache`, rebuild the route cache after creating a BREAD.
- **Permissions are ability strings** (`browse posts`, `manage settings`); a BREAD's five are created when it is saved. `policy` in a definition lets several BREADs share them.
- **The media library** stores files on `tardis.media.disk` under `tardis.media.path` and records them in `tardis_media`. Point the disk at Voyager's folder to reuse its files.
- **No database manager writes migrations for you.** The Database screen edits tables directly; keep your migrations as the source of truth.

## 5. Remove Voyager

When the import is checked and your code no longer calls Voyager:

```bash
composer remove tcg/voyager
```

Move Tardis to the `/admin` prefix if you want it there, and drop the Voyager tables (`data_types`, `data_rows`, `menus`, `menu_items`, `settings`, `translations`, `permissions`, `permission_role`, `roles`, `user_roles`) with a migration of your own once you no longer need them for reference.
