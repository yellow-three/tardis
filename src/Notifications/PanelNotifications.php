<?php

declare(strict_types=1);

namespace Tardis\Notifications;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Tardis\Models\PanelNotification;

/**
 * Messages for panel users, shown under the bell in the header:
 * Tardis::notify($user, 'Import finished', url: route(...)). A host's
 * Laravel notification can use the "tardis" channel (TardisChannel) instead.
 */
class PanelNotifications
{
    /** Bound per request (scoped), so a worker notices the table once it exists. */
    protected ?bool $available = null;

    /**
     * @param  Authenticatable|int|string|iterable<Authenticatable|int|string>  $users
     * @return int how many were stored
     */
    public function send(mixed $users, string $title, ?string $body = null, ?string $url = null, string $level = 'info'): int
    {
        if (! $this->available()) {
            return 0;
        }

        $level = in_array($level, PanelNotification::LEVELS, true) ? $level : 'info';
        // A root-relative path or an http(s) link; never "//host" or javascript:.
        $url = $url !== null && ((str_starts_with($url, '/') && ! str_starts_with($url, '//')) || preg_match('#^https?://#i', $url) === 1) ? $url : null;
        $count = 0;

        foreach (is_iterable($users) ? $users : [$users] as $user) {
            $id = $user instanceof Authenticatable ? $user->getAuthIdentifier() : $user;

            if ($id === null || $id === '') {
                continue;
            }

            PanelNotification::query()->create([
                'user_id' => $id,
                'title' => mb_substr($title, 0, 255),
                'body' => $body,
                'url' => $url === null ? null : mb_substr($url, 0, 2048),
                'level' => $level,
            ]);
            $count++;
        }

        return $count;
    }

    /** @return Collection<int, PanelNotification> */
    public function latest(int|string|null $userId, int $limit = 10): Collection
    {
        if ($userId === null || ! $this->available()) {
            return collect();
        }

        return PanelNotification::query()->where('user_id', $userId)->latest('id')->limit($limit)->get();
    }

    public function unreadCount(int|string|null $userId): int
    {
        if ($userId === null || ! $this->available()) {
            return 0;
        }

        return PanelNotification::query()->where('user_id', $userId)->whereNull('read_at')->count();
    }

    /** Only the owner's notification is touched; another id does nothing. */
    public function markRead(int|string|null $userId, int $id): ?PanelNotification
    {
        $notification = $userId === null ? null : PanelNotification::query()->where('user_id', $userId)->find($id);

        if ($notification !== null && $notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return $notification;
    }

    public function markAllRead(int|string|null $userId): int
    {
        if ($userId === null || ! $this->available()) {
            return 0;
        }

        return PanelNotification::query()->where('user_id', $userId)->whereNull('read_at')->update(['read_at' => now()]);
    }

    /** Before `php artisan migrate` the bell is empty rather than broken. */
    protected function available(): bool
    {
        return $this->available ??= Schema::hasTable(PanelNotification::TABLE);
    }
}
