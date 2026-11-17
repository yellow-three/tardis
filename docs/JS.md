# `window.Tardis` — the JavaScript surface for plugins

Plugin and host scripts talk to the panel through `window.Tardis`. It is versioned (`Tardis.version`); anything not listed here (the Alpine stores, the boot payload) is internal and may change between releases. A breaking change to this page only ships in a major version.

The core script (`resources/compiled/assets/app.js`) is loaded **before** Livewire starts Alpine and before any plugin script, so `window.Tardis` always exists when your code runs.

| Member | What it does |
|---|---|
| `Tardis.component(name, definition)` | Registers an Alpine component, the same as `Alpine.data(name, definition)`, whether or not Alpine has started: `Tardis.component('chart', () => ({ ... }))`, then `x-data="chart"` in your view. |
| `Tardis.on(event, listener)` | Subscribes to `ready`, `navigate` (after a `wire:navigate` page change) or `theme-changed` (`{ mode, applied }`). `ready` fires immediately when the page is already loaded. |
| `Tardis.toast(message, options)` | Shows a toast. `options`: `type` (`success`, `error`, `warning`, `info`), `timeout` in ms (0 keeps it). From PHP: `$this->dispatch('tardis-toast', message: '…', type: 'success')`. |
| `Tardis.theme` | The `data-theme` currently applied (read only). |
| `Tardis.csrf()` | The CSRF token, for `fetch` calls. |

## Shipping assets from a plugin

Return `Asset` objects from `provideCSS()` / `provideJS()` (or scaffold everything with `tardis:make-plugin blog --with-assets`):

```php
use Tardis\Assets\Asset;

public function provideCSS(): Asset
{
    return Asset::file(__DIR__.'/../dist/plugin.css');
}

public function provideJS(): array
{
    return [
        Asset::file(__DIR__.'/../dist/plugin.js')->routes('tardis.bread.*')->ability('edit posts'),
    ];
}
```

- Tardis serves the file at `/admin/_assets/{hash}.css|js` with a one-year `immutable` cache header. The hash comes from the file's content, so an update changes the URL; nothing is published into the host's `public/`.
- **Do not put secrets in an asset.** The route needs no login (the sign-in page needs assets too) and anyone can request a registered file.
- `->scope('admin' | 'auth' | 'both')`, `->routes('pattern.*')` and `->ability('…')` declare where the asset is wanted; elsewhere it is not written at all.
- A formfield type loads its own assets by overriding `Formfield::assets()`; they are written only on pages that render the field.
- Inline text (`provideCSS(): string`) still works and is written in a `<style>`/`<script>` that carries the CSP nonce when one is configured (`tardis.csp.nonce`, or Laravel's `Vite::useCspNonce()`).

## Styling

The package CSS is precompiled: use DaisyUI components and the theme variables (`--color-primary`, `--color-base-300`, …). Put your own rules in `@layer tardis.plugins { … }` so they cannot override core components by accident, and prefix your classes with `tp-{plugin}-`.

## Settings screen

Implement `Tardis\Contracts\Plugins\Features\Provider\SettingsComponent` and return the name of a Livewire component; the Plugins page shows a **Settings** button for the enabled plugin and opens the component in a dialog. Guard the component yourself (an `Abilities`/`BreadAuthorization` check in `boot()`).

## Routes

A plugin that implements `Provider\Routes` gets `provideRoutes(Router $router)` called inside the panel group: the admin prefix, the `tardis.` name prefix and the `web`, locale and `tardis.admin` middleware. Public routes are not part of this contract; register them in your own service provider.
