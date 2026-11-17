<?php

declare(strict_types=1);

namespace Tardis\Auth\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;

/**
 * Laravel's reset mail, pointing at the panel's own reset page.
 *
 * The stock notification links to the host's `password.reset` route, which a
 * bare host does not have. Changing that through ResetPassword::createUrlUsing()
 * would redirect every reset mail the host sends, including its own users', so
 * the panel sends this subclass instead and leaves the global callback alone.
 */
class ResetPasswordNotification extends ResetPassword
{
    protected function resetUrl($notifiable)
    {
        return url(route('tardis.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));
    }
}
