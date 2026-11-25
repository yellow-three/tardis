<?php

use Livewire\Attributes\Computed;
use Livewire\Component;
use Tardis\Notifications\PanelNotifications;
use Tardis\Support\SafeUrl;

/**
 * The bell in the header: the signed-in user's latest notifications. Checks
 * again every minute while the tab is visible.
 */
new class extends Component
{
    #[Computed]
    public function items()
    {
        return app(PanelNotifications::class)->latest(auth()->id(), 10);
    }

    #[Computed]
    public function unread(): int
    {
        return app(PanelNotifications::class)->unreadCount(auth()->id());
    }

    public function open(int $id): mixed
    {
        $notification = app(PanelNotifications::class)->markRead(auth()->id(), $id);

        if (! $notification?->url || ! SafeUrl::isAllowed($notification->url)) {
            // The row may predate the stricter check, or have been written by
            // another code path; never redirect through a stored bad link.
            return null;
        }

        // A link elsewhere is a full page load; a panel link keeps the SPA navigation.
        if (! str_starts_with($notification->url, '/')) {
            return redirect()->away($notification->url);
        }

        $this->redirect($notification->url, navigate: true);

        return null;
    }

    public function markAllRead(): void
    {
        app(PanelNotifications::class)->markAllRead(auth()->id());
    }
}; ?>

@php
    $levelBadges = ['info' => 'badge-info', 'success' => 'badge-success', 'warning' => 'badge-warning', 'error' => 'badge-error'];
    $unreadCount = $this->unread;
    $bellLabel = $unreadCount > 0 ? __('tardis::shell.notification_center.unread', ['count' => $unreadCount]) : __('tardis::shell.notifications');
@endphp

<div class="dropdown dropdown-end" wire:poll.60s.visible>
    <div tabindex="0" role="button" class="btn btn-ghost btn-circle btn-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary" aria-label="{{ $bellLabel }}">
        <div class="indicator">
            <x-tardis::icon name="ui.bell" class="w-5 h-5" />
            @if ($unreadCount > 0)
                <span class="badge badge-primary badge-xs indicator-item">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
            @endif
        </div>
    </div>
    <div tabindex="0" class="dropdown-content z-40 mt-3 w-80 rounded-box border border-base-300 bg-base-100 shadow-lg">
        <div class="flex items-center justify-between border-b border-base-300 px-4 py-2">
            <span class="text-sm font-semibold">{{ __('tardis::shell.notifications') }}</span>
            @if ($unreadCount > 0)
                <button type="button" wire:click="markAllRead" class="btn btn-ghost btn-xs">{{ __('tardis::shell.notification_center.mark_all_read') }}</button>
            @endif
        </div>
        <ul class="max-h-96 overflow-y-auto">
            @forelse ($this->items as $notification)
                <li wire:key="notification-{{ $notification->id }}">
                    <button type="button" wire:click="open({{ $notification->id }})" class="flex w-full items-start gap-3 px-4 py-3 text-start hover:bg-base-200 {{ $notification->read_at ? 'opacity-60' : '' }}">
                        <span class="badge {{ $levelBadges[$notification->level] ?? 'badge-info' }} badge-xs mt-1.5 shrink-0" aria-hidden="true"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium">{{ $notification->title }}</span>
                            @if ($notification->body)
                                <span class="block text-xs text-base-content/70">{{ \Illuminate\Support\Str::limit($notification->body, 140) }}</span>
                            @endif
                            <span class="block text-xs text-base-content/50">{{ $notification->created_at?->diffForHumans() }}</span>
                        </span>
                    </button>
                </li>
            @empty
                <li class="px-4 py-6 text-center text-sm text-base-content/60">{{ __('tardis::shell.notification_center.empty') }}</li>
            @endforelse
        </ul>
    </div>
</div>
