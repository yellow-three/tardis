<?php

declare(strict_types=1);

use Tardis\Auth\TwoFactor\Totp;

// RFC 6238 appendix B, SHA-1 secret "12345678901234567890" (last six digits).
const RFC_SEED = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

test('codes match the RFC 6238 test vectors', function (int $time, string $code) {
    expect(Totp::code(RFC_SEED, Totp::currentStep($time)))->toBe($code);
})->with([
    [59, '287082'],
    [1111111109, '081804'],
    [1234567890, '005924'],
    [2000000000, '279037'],
]);

test('a code is accepted one step either side, and never twice', function () {
    $time = 1_700_000_000;
    $step = Totp::currentStep($time);
    $code = Totp::code(RFC_SEED, $step);

    expect(Totp::verify(RFC_SEED, $code, null, $time))->toBe($step)
        ->and(Totp::verify(RFC_SEED, $code, null, $time + 30))->toBe($step)
        ->and(Totp::verify(RFC_SEED, $code, null, $time + 90))->toBeNull()
        ->and(Totp::verify(RFC_SEED, $code, $step, $time))->toBeNull()
        ->and(Totp::verify(RFC_SEED, '12345', null, $time))->toBeNull();
});

test('a new secret is base32 and the uri names issuer and account', function () {
    $seed = Totp::secret();

    $uri = Totp::uri('Acme Admin', 'ada@example.test', $seed);
    parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);

    expect($seed)->toMatch('/^[A-Z2-7]{32}$/')
        ->and($uri)->toStartWith('otpauth://totp/Acme%20Admin%3Aada%40example.test?')
        ->and($query)->toMatchArray(['issuer' => 'Acme Admin', 'digits' => '6', 'period' => '30'])
        ->and($query)->toHaveKey('secret')
        ->and(in_array($seed, $query, true))->toBeTrue();
});
