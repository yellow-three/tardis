<?php

declare(strict_types=1);

namespace Tardis\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Laravel notification channel "tardis": a notification whose via() returns
 * 'tardis' and that has toTardis($notifiable) returning
 * ['title' => ..., 'body' => ..., 'url' => ..., 'level' => ...] lands in the
 * panel's notification centre.
 */
class TardisChannel
{
    public function __construct(protected PanelNotifications $notifications) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toTardis')) {
            throw new \LogicException(sprintf('%s needs a toTardis() method for the "tardis" channel.', $notification::class));
        }

        $message = (array) $notification->toTardis($notifiable);

        $this->notifications->send(
            $notifiable,
            (string) ($message['title'] ?? ''),
            isset($message['body']) ? (string) $message['body'] : null,
            isset($message['url']) ? (string) $message['url'] : null,
            (string) ($message['level'] ?? 'info'),
        );
    }
}
