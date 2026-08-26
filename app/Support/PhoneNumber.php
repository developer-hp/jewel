<?php

namespace App\Support;

/**
 * Numbers in the form WhatsApp wants: a full international number, digits only,
 * no plus sign.
 *
 * Deliberately separate from Customer::phoneKey(). That one backs a unique index on
 * rows that already exist and must keep returning exactly what it always has — and
 * its docblock is explicit that it will not guess a region. This one is allowed to
 * guess, because the alternative is never sending anything, and it is allowed to
 * refuse, because sending to the wrong person is worse than not sending.
 */
class PhoneNumber
{
    /** E.164 allows fifteen digits at most, country code included. */
    private const MAX_DIGITS = 15;

    /**
     * The number to send to, or null when it cannot be trusted.
     */
    public static function toE164(?string $phone, string $countryCode = '91'): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        // "00" is the international access prefix and "0" the national trunk
        // prefix; neither belongs in the number itself.
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        $countryCode = preg_replace('/\D+/', '', $countryCode) ?: '91';

        // The counter's norm: a bare local mobile.
        if (strlen($digits) === 10) {
            return $countryCode.$digits;
        }

        // Already complete. Anything longer than the local number but not carrying
        // the country code is something this cannot safely interpret.
        if (str_starts_with($digits, $countryCode)
            && strlen($digits) > 10
            && strlen($digits) <= self::MAX_DIGITS) {
            return $digits;
        }

        return null;
    }
}
