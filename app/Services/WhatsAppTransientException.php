<?php

namespace App\Services;

use RuntimeException;

/**
 * A WhatsApp send that failed for a reason that might not fail again — the network,
 * or Meta returning a 5xx.
 *
 * This exists so the job's retries mean something. A bad template name or an
 * opted-out recipient fails identically however many times it is tried, so those are
 * logged and swallowed; only this is thrown, and only this is retried.
 */
class WhatsAppTransientException extends RuntimeException {}
