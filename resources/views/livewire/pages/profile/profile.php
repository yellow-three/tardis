<?php

use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Tardis\Auth\UserAccounts;
use Tardis\Bread\ActivityLogger;
use Tardis\Support\PanelUser;

new #[Title('tardis::shell.titles.profile')] #[Layout('tardis::layouts.admin')] class extends Component
{
    /** @var array{name: string, email: string, current_password: string, password: string, password_confirmation: string} */
    public array $userForm = ['name' => '', 'email' => '', 'current_password' => '', 'password' => '', 'password_confirmation' => ''];

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
