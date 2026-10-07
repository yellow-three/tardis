<?php

declare(strict_types=1);

namespace Tardis\Theme;

use Tardis\Manager\SettingsManager;
use Tardis\Manager\ThemeManager;
use Tardis\Support\UserPreferences;

/**
 * Which theme a user sees: their own saved choice, else the administrator's
 * default (Settings → appearance), else the built-in light/dark pair.
 *
 * The three parts are independent: a user can pick only a mode and still
 * inherit the default light and dark themes. Anything stored that is no longer
 * valid (a deleted theme, a light theme in the dark slot) quietly falls back.
 */
class ThemePreference
{
    public const MODES = ['light', 'dark', 'system'];

    public function __construct(
        protected ThemeManager $themes,
        protected UserPreferences $preferences,
        protected SettingsManager $settings,
    ) {}

    /**
     * @return array{mode: string, light: string, dark: string}
     */
    public function resolve(int|string|null $userId): array
    {
        $stored = $this->preferences->get($userId, 'theme', []);
        $stored = is_array($stored) ? $stored : [];

        return [
            'mode' => $this->pickMode([$stored['mode'] ?? null, $this->setting('mode')]),
            'light' => $this->pickTheme('light', [$stored['light'] ?? null, $this->setting('theme_light')]),
            'dark' => $this->pickTheme('dark', [$stored['dark'] ?? null, $this->setting('theme_dark')]),
        ];
    }

    /**
     * Store a choice; every part that is present must be valid or nothing is saved.
     *
     * @param  array<string, mixed>  $choice  any of mode, light, dark
     */
    public function save(int|string|null $userId, array $choice): bool
    {
        $clean = [];

        if (array_key_exists('mode', $choice)) {
            if (! in_array($choice['mode'], self::MODES, true)) {
                return false;
            }

            $clean['mode'] = $choice['mode'];
        }

        foreach (['light', 'dark'] as $slot) {
            if (! array_key_exists($slot, $choice)) {
                continue;
            }

            $theme = is_string($choice[$slot]) ? $this->themes->find($choice[$slot]) : null;

            if ($theme === null || $theme->scheme !== $slot) {
                return false;
            }

            $clean[$slot] = $theme->name;
        }

        if ($clean === [] || $userId === null) {
            return false;
        }

        $current = $this->preferences->get($userId, 'theme', []);

        $this->preferences->set($userId, 'theme', array_merge(is_array($current) ? $current : [], $clean));

        return true;
    }

    /**
     * @param  array<int, mixed>  $candidates
     */
    protected function pickMode(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, self::MODES, true)) {
                return $candidate;
            }
        }

        return 'dark';
    }

    /**
     * @param  array<int, mixed>  $candidates
     */
    protected function pickTheme(string $scheme, array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $theme = is_string($candidate) ? $this->themes->find($candidate) : null;

            if ($theme !== null && $theme->scheme === $scheme) {
                return $theme->name;
            }
        }

        return $scheme === 'light' ? 'tardis-light' : 'tardis-dark';
    }

    protected function setting(string $key): mixed
    {
        try {
            return $this->settings->get('appearance.'.$key);
        } catch (\Throwable) {
            return null;
        }
    }
}
