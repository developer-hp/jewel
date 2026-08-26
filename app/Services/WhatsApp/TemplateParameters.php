<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Str;

/**
 * Shared by every message builder: getting a value into a state Meta will accept.
 *
 * The Cloud API rejects a parameter that is empty, or that carries a newline or a
 * tab — a customer name pasted with a line break would fail the whole message, and
 * the failure reads as a template error rather than a data one. So everything is
 * flattened here, once, and a blank becomes a stand-in rather than a rejection.
 */
trait TemplateParameters
{
    /** Meta's own per-parameter ceiling is generous; this keeps a stray essay out. */
    private const MAX_LENGTH = 200;

    protected function text(?string $value, string $fallback = '-'): string
    {
        $clean = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');

        return Str::limit($clean === '' ? $fallback : $clean, self::MAX_LENGTH, '');
    }
}
