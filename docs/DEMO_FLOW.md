# Demo Flow

This is a clean, presentation-ready flow for showing Tardis in action.

## 1. Open the admin dashboard

Show the fixed Livewire admin shell and highlight the sections:

- Overview
- Management
- Access

This demonstrates the package shell and the sidebar grouping logic.

## 2. Show the menu system

Explain that menu items come from the `MenuManager` and plugin providers.

Key points:

- default entries are registered centrally
- plugin items can be added without editing core files
- active route detection works for nested admin URLs

## 3. Show BREAD management

Navigate to the BREAD management screen and explain the difference between:

- fixed admin screens (dedicated Livewire pages)
- dynamic resource screens (one set of Livewire pages rendered from a BREAD definition)

This is a key architectural advantage of Tardis.

## 4. Create a resource

Use the BREAD builder (`/admin/bread/create`) or `php artisan tardis:make-bread "App\Models\Post"` to create a `posts` resource.

Explain:

- model mapping
- field metadata
- validation rules
- layout configuration

## 5. Browse the resource

Open `/admin/posts` and show:

- list table
- search
- create button
- read/edit/delete actions

This emphasizes that dynamic resources are truly generated from metadata rather than hardcoded admin pages.

## 6. Show plugin extension

Display a custom plugin with its own menu item and route.

This demonstrates the extension story: the core admin can grow without monolithic changes.

## 7. Show access control

Open Roles and Permissions, grant `browse posts` to a role, and show the sidebar and `/admin/posts` follow it. Mention that an authorization plugin must be enabled for this to be enforced (see the README).

## 8. Close with architecture summary

Use this message as the closing summary:

> Tardis separates fixed admin screens from dynamic, definition-driven CRUD. That gives the framework a clean blend of admin UX and flexibility for custom business entities.

## Suggested demo script

```text
This is a Laravel admin framework built on Livewire 4.
The shell is fixed and framework-driven, while entity screens are dynamic.
That is why the admin screens have explicit routes while BREAD resources share a wildcard route driven by metadata.
Menu items come from the menu manager and plugins, and the admin remains modular and extensible.
```
