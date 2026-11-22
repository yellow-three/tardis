<?php

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Auth\TwoFactor\Totp;
use Tardis\Auth\TwoFactor\TwoFactorAuthentication;
use Tardis\Auth\UserAccounts;
use Tardis\Bread\ActivityLogger;
use Tardis\Support\PanelUser;

new #[Title('tardis::shell.titles.profile')] #[Layout('tardis::layouts.admin')] class extends Component
{
    /** @var array{name: string, email: string, current_password: string, password: string, password_confirmation: string} */
    public array $userForm = ['name' => '', 'email' => '', 'current_password' => '', 'password' => '', 'password_confirmation' => ''];

    /** The code typed to confirm or turn off two-factor authentication. */
    public string $twoFactorCode = '';

    /** @var list<string> recovery codes, shown once right after they are made */
    public array $recoveryCodes = [];

    public function mount(): void
    {
        $this->userForm = [...$this->userForm, ...$this->accounts()->describe($this->user())];
    }

    public function save(): void
    {
        $user = $this->user();
        $accounts = $this->accounts();
        $before = $accounts->describe($user);

        $this->validate($accounts->rules($user->getKey(), passwordRequired: false));

        // Changing the email (where reset links go) or the password takes the
        // current password, so an unattended session cannot take the account.
        $sensitive = $this->userForm['password'] !== '' || trim($this->userForm['email']) !== $before['email'];

        if ($sensitive && ! $accounts->passwordMatches($user, $this->userForm['current_password'])) {
            $this->addError('userForm.current_password', __('tardis::profile.current_password_wrong'));

            return;
        }

        $accounts->update($user, [
            'name' => $this->userForm['name'],
            'email' => $this->userForm['email'],
            'password' => $this->userForm['password'],
        ]);

        app(ActivityLogger::class)->audit('tardis.profile', 'profile_updated', [
            'email_changed' => trim($this->userForm['email']) !== $before['email'],
            'password_changed' => $this->userForm['password'] !== '',
        ], $user->getKey());

        $this->userForm = [...$this->userForm, 'current_password' => '', 'password' => '', 'password_confirmation' => ''];
        session()->flash('message', __('tardis::profile.saved'));
    }

    public function twoFactor(): TwoFactorAuthentication
    {
        return app(TwoFactorAuthentication::class);
    }

    /** Begin setting up: a new secret to scan, not in force until confirmed. */
    public function startTwoFactor(): void
    {
        abort_unless($this->twoFactor()->available(), 404);
        abort_if($this->twoFactor()->enabledFor($this->user()), 409);

        $this->twoFactor()->start($this->user());
        $this->reset('twoFactorCode', 'recoveryCodes');
    }

    public function confirmTwoFactor(): void
    {
        $this->resetErrorBag('twoFactorCode');

        $codes = $this->twoFactor()->confirm($this->user(), $this->twoFactorCode);
        $this->twoFactorCode = '';

        if ($codes === null) {
            $this->addError('twoFactorCode', __('tardis::auth.two_factor_wrong'));

            return;
        }

        $this->recoveryCodes = $codes;
        app(ActivityLogger::class)->audit('tardis.profile', 'two_factor_enabled', [], $this->user()->getKey());
    }

    /** Turning it off takes a current code (or a recovery code). */
    public function disableTwoFactor(): void
    {
        $this->resetErrorBag('twoFactorCode');

        if (! $this->twoFactor()->verify($this->user(), $this->twoFactorCode)) {
            $this->twoFactorCode = '';
            $this->addError('twoFactorCode', __('tardis::auth.two_factor_wrong'));

            return;
        }

        $this->twoFactor()->disable($this->user());
        $this->reset('twoFactorCode', 'recoveryCodes');
        app(ActivityLogger::class)->audit('tardis.profile', 'two_factor_disabled', [], $this->user()->getKey());
    }

    public function regenerateRecoveryCodes(): void
    {
        $this->resetErrorBag('twoFactorCode');

        if (! $this->twoFactor()->verify($this->user(), $this->twoFactorCode)) {
            $this->twoFactorCode = '';
            $this->addError('twoFactorCode', __('tardis::auth.two_factor_wrong'));

            return;
        }

        $this->twoFactorCode = '';
        $this->recoveryCodes = $this->twoFactor()->regenerateRecoveryCodes($this->user());
    }

    /** The QR code for the pending secret, when bacon/bacon-qr-code is installed. */
    public function qrCode(string $seed): ?string
    {
        if (! class_exists(Writer::class)) {
            return null;
        }

        $renderer = new ImageRenderer(
            new RendererStyle(192, 1),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($this->otpauthUri($seed));
    }

    public function otpauthUri(string $seed): string
    {
        $account = (string) (data_get($this->user(), app(UserAccounts::class)->column('email') ?? 'email') ?? $this->user()->getKey());

        return Totp::uri((string) (config('tardis.two_factor.issuer') ?: config('app.name') ?: 'Tardis'), $account, $seed);
    }

    protected function user(): Model
    {
        $user = PanelUser::current();

        abort_unless($user instanceof Model && $user instanceof ($this->accounts()->model()), 404);

        return $user;
    }

    protected function accounts(): UserAccounts
    {
        return app(UserAccounts::class);
    }
};
