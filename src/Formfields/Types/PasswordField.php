<?php

namespace Tardis\Formfields\Types;

use Illuminate\Support\Facades\Hash;
use Tardis\Formfields\Formfield;

class PasswordField extends Formfield
{
    public function transform(mixed $value): mixed
    {
        if (blank($value)) {
            return null;
        }

        return Hash::make($value);
    }

    public function skipWhenBlank(): bool
    {
        return true;
    }

    public function type(): string
    {
        return 'password';
    }

    public function render(): string
    {
        return 'tardis::formfields.password';
    }
}
