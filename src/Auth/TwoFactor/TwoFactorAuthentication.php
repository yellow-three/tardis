<?php

declare(strict_types=1);

namespace Tardis\Auth\TwoFactor;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tardis\Models\TwoFactor;

/**
 * Turning two-factor authentication on and off for a user, and checking the
 * code they type at sign-in (a TOTP code or a one-time recovery code).
 */
final class TwoFactorAuthentication
{
    public const RECOVERY_CODES = 8;

    public function available(): bool
    {
        return (bool) config('tardis.two_factor.enabled', true) && Schema::hasTable(TwoFactor::TABLE);
    }

    public function enabledFor(Model $user): bool
    {
        return $this->available() && $this->record($user)?->confirmed_at !== null;
    }

    /**
     * Start (or restart) setting it up: a new secret, not yet in force until
     * confirm() sees a code from it. Returns the secret.
     */
    public function start(Model $user): string
    {
        $seed = Totp::secret();

        TwoFactor::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            ['secret' => $seed, 'recovery_codes' => null, 'confirmed_at' => null, 'last_used_step' => null],
        );

        return $seed;
    }

    /** The secret being set up, for the QR code; null once confirmed. */
    public function pendingSecret(Model $user): ?string
    {
        $record = $this->record($user);

        return $record !== null && $record->confirmed_at === null ? $record->secret : null;
    }

    /**
     * Put it in force when $code comes from the pending secret. Returns the
     * recovery codes, to show once, or null when the code is wrong.
     *
     * @return list<string>|null
     */
    public function confirm(Model $user, string $code): ?array
    {
        $record = $this->record($user);

        if ($record === null || $record->confirmed_at !== null) {
            return null;
        }

        $step = Totp::verify($record->secret, $code);

        if ($step === null) {
            return null;
        }

        $codes = $this->newRecoveryCodes();
        $record->forceFill([
            'confirmed_at' => now(),
            'last_used_step' => $step,
            'recovery_codes' => array_map(fn (string $code) => Hash::make($code), $codes),
        ])->save();

        return $codes;
    }

    /**
     * Whether $code proves the second factor: a TOTP code not used before,
     * or an unused recovery code (which is then spent).
     */
    public function verify(Model $user, string $code): bool
    {
        $record = $this->record($user);

        if ($record === null || $record->confirmed_at === null) {
            return false;
        }

        $step = Totp::verify($record->secret, $code, $record->last_used_step);

        if ($step !== null) {
            $record->forceFill(['last_used_step' => $step])->save();

            return true;
        }

        $typed = strtoupper(trim($code));
        $codes = (array) ($record->recovery_codes ?? []);

        foreach ($codes as $index => $hash) {
            if (is_string($hash) && Hash::check($typed, $hash)) {
                unset($codes[$index]);
                $record->forceFill(['recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    /** @return list<string> new recovery codes; the old ones stop working */
    public function regenerateRecoveryCodes(Model $user): array
    {
        $record = $this->record($user);
        abort_if($record === null || $record->confirmed_at === null, 404);

        $codes = $this->newRecoveryCodes();
        $record->forceFill(['recovery_codes' => array_map(fn (string $code) => Hash::make($code), $codes)])->save();

        return $codes;
    }

    public function remainingRecoveryCodes(Model $user): int
    {
        $record = $this->record($user);

        return $record === null ? 0 : count((array) $record->recovery_codes);
    }

    public function disable(Model $user): void
    {
        TwoFactor::query()->where('user_id', $user->getKey())->delete();
    }

    private function record(Model $user): ?TwoFactor
    {
        if (! Schema::hasTable(TwoFactor::TABLE)) {
            return null;
        }

        return TwoFactor::query()->where('user_id', $user->getKey())->first();
    }

    /** @return list<string> */
    private function newRecoveryCodes(): array
    {
        return array_map(fn () => strtoupper(Str::random(5).'-'.Str::random(5)), range(1, self::RECOVERY_CODES));
    }
}
