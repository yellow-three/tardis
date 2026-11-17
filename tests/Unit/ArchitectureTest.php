<?php

declare(strict_types=1);

// Livewire component files under resources/views are templates, not classes,
// and keep their own rules (.claude/AGENTS.md); this covers the package code.
arch('package classes declare strict types')
    ->expect('Tardis')
    ->toUseStrictTypes();
