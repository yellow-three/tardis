<?php

declare(strict_types=1);

namespace Tardis\Auth;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * A user's open sessions, and signing them out everywhere else.
 *
 * Only Laravel's database session driver keeps sessions where they can be
 * listed (the sessions table records the user, address and browser); with any
 * other driver the profile says so instead.
 */
final class BrowserSessions
{
    public function available(): bool
    {
        return config('session.driver') === 'database' && Schema::hasTable($this->table());
    }

    /**
     * @return Collection<int, array{id: string, ip: mixed, agent: string, last_active: string, current: bool}>
     */
    public function forUser(Model $user): Collection
    {
        if (! $this->available()) {
            return collect();
        }

        return DB::table($this->table())
            ->where('user_id', $user->getKey())
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($row) => [
                'id' => (string) $row->id,
                'ip' => $row->ip_address,
                'agent' => $this->describe((string) $row->user_agent),
                'last_active' => Carbon::createFromTimestamp((int) $row->last_activity)->diffForHumans(),
                'current' => $row->id === session()->getId(),
            ]);
    }

    /**
     * End every other session of the user. Their "remember me" token is
     * replaced too, or a closed browser would sign itself back in.
     */
    public function logoutOthers(Model $user): int
    {
        $ended = $this->available()
            ? DB::table($this->table())->where('user_id', $user->getKey())->where('id', '!=', session()->getId())->delete()
            : 0;

        if (method_exists($user, 'getRememberTokenName') && $user->getRememberTokenName() !== ''
            && Schema::hasColumn($user->getTable(), $user->getRememberTokenName())) {
            $user->forceFill([$user->getRememberTokenName() => Str::random(60)])->save();
        }

        return $ended;
    }

    /** "Firefox on Linux" from a user agent, roughly; the raw string otherwise. */
    public function describe(string $agent): string
    {
        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => null,
        };
        $os = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        if ($browser === null && $os === null) {
            return $agent !== '' ? Str::limit($agent, 60) : '—';
        }

        return trim(($browser ?? '').($browser && $os ? ' · ' : '').($os ?? ''));
    }

    private function table(): string
    {
        return (string) config('session.table', 'sessions');
    }
}
