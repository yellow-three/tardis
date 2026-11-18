# Security policy

## Supported versions

Tardis is in pre-release (`0.x`). Security fixes go into the latest `0.x` release only; upgrade to it before reporting.

## Reporting a vulnerability

Please do not open a public issue for a security problem. Report it privately through GitHub: on the repository's **Security** tab, choose **Report a vulnerability**. Only the maintainers see the report.

Include what you found, the steps to reproduce it, the affected version, and the impact you expect. You will get an answer within a week; once a fix is released, the advisory is published with credit to you, unless you prefer otherwise.

## Scope

In scope: the package code (`src/`, `resources/`, `routes/`, `database/`, `stubs/`). Problems in a host application's own code, or in Laravel, Livewire and other dependencies, belong with those projects.

The panel is meant to be reached by trusted, signed-in staff. Still in scope: anything that lets a user reach a screen, record or ability their roles do not grant, and anything a signed-out visitor can do beyond the sign-in and password reset pages.
