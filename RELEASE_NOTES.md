# Tardis Release Notes

## Current status

Tardis is in a stable, package-ready state for Laravel admin work built on Livewire 4.

### Included capabilities

- Livewire-based fixed admin screens
- Dynamic BREAD CRUD resource routing
- Sectioned sidebar/navigation management
- Plugin-aware menu registration
- Theme manifest support with DaisyUI integration
- Centralized admin route grouping
- Regression coverage for BREAD and admin pages

## Key architecture decision

The package intentionally follows the Livewire 4 page-first convention:

1. simpler screens use SFC
2. more complex screens use MFC
3. page routes remain Livewire routes rather than controller-per-page routes

This keeps the admin shell predictable, matches the docs’ recommended structure, and gives the package a clean extension point for custom resources.

## Verified quality

The package passes the full test suite:

```bash
composer test
```

Result:

- 140 tests passed
- 231 assertions

## Optional next improvements

These are not required for core stability, but can improve package maturity:

- richer CRUD field-type system for more advanced forms
- polished media library integration examples
- flexible admin page templates for package consumers
- additional docs for plugin creation and custom menu registration
- sample app or demo site for visual validation

## Handoff summary

The package is ready for continued development, contributor work, or external integration.
