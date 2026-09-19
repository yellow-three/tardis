# Tardis Project Status

## Overview

Tardis is in a stable, package-ready state as a Laravel admin framework built around Livewire 4, DaisyUI, Vite, and a dynamic BREAD system.

## Current architecture

### 1. Fixed admin screens
These screens are rendered as Livewire pages and route directly through the admin route map:

- dashboard
- plugins
- settings
- permissions
- roles
- bread management
- bread create page

### 2. Dynamic BREAD resources
Dynamic CRUD pages are organized as Livewire page components, with larger screens using the MFC pattern and simpler ones remaining in SFC. This keeps the admin UI consistent with the official Livewire 4 component model while preserving flexible CRUD behavior:

- /admin/{slug}
- /admin/{slug}/create
- /admin/{slug}/{id}
- /admin/{slug}/{id}/edit

### 3. Menu and navigation
Menu items are managed through the MenuManager and sidebar grouping logic. Active route detection supports nested admin URLs and keeps the sidebar aligned with the current section.

### 4. Theme system
Theme discovery is handled through the Vite manifest and the Tardis theme manager. Available themes are exposed to the frontend from the generated manifest.

## Completed work

- Livewire 4 admin shell stabilized
- dynamic BREAD CRUD routes cleaned up
- route ambiguity removed
- active sidebar state fixed
- BREAD list/create/edit/read pages implemented
- page-header component standardized
- README refreshed to match the actual architecture
- regression suite kept green

## Validation

The package was verified with the full test suite:

```bash
composer test
```

Result:

- 140 tests passed
- 231 assertions

## Current status

The project is in a final stabilized state and ready for handoff or further feature expansion.
