<?php

declare(strict_types=1);

namespace Tardis\Auth\TwoFactor;

/**
 * Time-based one-time passwords (RFC 6238, the codes authenticator apps
 * show): HMAC-SHA1, 30-second steps, six digits. The secret is base32, as
 * the apps expect it.
 */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public const STEP = 30;

    public const DIGITS = 6;

    /** A new random secret: 20 bytes (160 bits), base32. */
    public static function secret(): string
    {
        return self::base32(random_bytes(20));
    }

    /** The code for a time step (floor(time / 30)). */
    public static function code(string $seed, int $step): string
    {
        $hash = hash_hmac('sha1', pack('N*', 0, $step), self::decode($seed), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    public static function currentStep(?int $time = null): int
    {
        return intdiv($time ?? time(), self::STEP);
    }

    /**
     * The step a code belongs to, within one step either way for clock drift,
     * or null. A step at or before $lastUsed is refused, so a code cannot be
     * used twice.
     */
    public static function verify(string $seed, string $code, ?int $lastUsed = null, ?int $time = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (preg_match('/^\d{'.self::DIGITS.'}$/', $code) !== 1) {
            return null;
        }

        $now = self::currentStep($time);

        foreach ([$now, $now - 1, $now + 1] as $step) {
            if (($lastUsed === null || $step > $lastUsed) && hash_equals(self::code($seed, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    /** The otpauth:// link an authenticator app reads (from a QR code). */
    public static function uri(string $issuer, string $account, string $seed): string
    {
        $query = http_build_query([
            'secret' => $seed,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::STEP,
        ], '', '&', PHP_QUERY_RFC3986);

        return 'otpauth://totp/'.rawurlencode($issuer.':'.$account).'?'.$query;
    }

    private static function base32(string $bytes): string
    {
        $bits = '';

        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $out = '';

        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    private static function decode(string $seed): string
    {
        $bits = '';

        foreach (str_split(strtoupper(rtrim($seed, '='))) as $char) {
            $index = strpos(self::ALPHABET, $char);

            if ($index !== false) {
                $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
            }
        }

        $out = '';

        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr((int) bindec($byte));
            }
        }

        return $out;
    }
}
