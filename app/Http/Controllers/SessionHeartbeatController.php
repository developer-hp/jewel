<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Keeps the server-side idle clock in step with real browser activity.
 *
 * The browser only calls this when the user has actually done something since the
 * last ping, so it extends the session on genuine activity and never on an idle
 * tab left open. EnforceIdleTimeout has already refreshed the timestamp by the
 * time this runs; the response just tells the page where it stands.
 */
class SessionHeartbeatController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $settings = AppSetting::resolved();

        return response()->json([
            'ok' => true,
            'timeout_seconds' => $settings->idleTimeoutEnabled() ? $settings->idleTimeoutSeconds() : null,
        ]);
    }
}
