<?php

use App\Support\PhoneNumber;

/**
 * WhatsApp wants a full international number and nothing else. The counter types
 * bare ten-digit mobiles, so most of the work is completing them — and refusing
 * anything that cannot be completed safely.
 */
it('completes a bare local mobile with the country code', function () {
    expect(PhoneNumber::toE164('9925747799'))->toBe('919925747799')
        ->and(PhoneNumber::toE164('9601263350'))->toBe('919601263350');
});

it('ignores how the number was punctuated', function (string $typed) {
    expect(PhoneNumber::toE164($typed))->toBe('919712406367');
})->with([
    '9712406367',
    '9712 406367',
    '9712-406367',
    '(9712) 406367',
    '+91 97124 06367',
    '97124 06367',
]);

it('leaves a number that already carries the country code alone', function () {
    expect(PhoneNumber::toE164('919925747799'))->toBe('919925747799')
        ->and(PhoneNumber::toE164('+91 99257 47799'))->toBe('919925747799');
});

it('strips the international and trunk prefixes', function () {
    // 00 is the international access prefix, a single 0 the national trunk one.
    expect(PhoneNumber::toE164('00919925747799'))->toBe('919925747799')
        ->and(PhoneNumber::toE164('09925747799'))->toBe('919925747799');
});

it('refuses anything it cannot complete safely', function (?string $typed) {
    // Sending to the wrong person is worse than not sending.
    expect(PhoneNumber::toE164($typed))->toBeNull();
})->with([
    null,
    '',
    '   ',
    'abc',
    '12345',
    '1234567890123456',
    // Twelve digits that are not an Indian number — guessing here would message
    // a stranger.
    '441234567890',
]);

it('takes a country code other than the default', function () {
    expect(PhoneNumber::toE164('551234567', '971'))->toBeNull()
        ->and(PhoneNumber::toE164('5512345678', '971'))->toBe('9715512345678')
        ->and(PhoneNumber::toE164('9715512345678', '971'))->toBe('9715512345678');
});
