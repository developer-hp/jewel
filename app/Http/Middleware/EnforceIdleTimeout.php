<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-side enforcement of the idle logout.
 *
 * The browser countdown is only a courtesy: it can be paused by a sleeping laptop
 * or a closed tab. This runs on every authenticated request and is what actually
 * ends the session, so a stale tab picked up an hour later cannot keep working.
 */
class EnforceIdleTimeout
{
    public const LAST_ACTIVITY_KEY = 'last_activity_at';

    public function handle(Request $request, Closure $next): Response
    {
        $settings = AppSetting::resolved();

        if (! Auth::guard('web')->check() || ! $settings->idleTimeoutEnabled()) {
            return $next($request);
        }

        $timeout = $settings->idleTimeoutSeconds();
        $last = (int) $request->session()->get(self::LAST_ACTIVITY_KEY, 0);
        $now = now()->getTimestamp();

        if ($last > 0 && ($now - $last) > $timeout) {
            return $this->timeOut($request);
        }

        $request->session()->put(self::LAST_ACTIVITY_KEY, $now);

        return $next($request);
    }

    private function timeOut(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = 'You were signed out after a period of inactivity.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'redirect' => route('login')], 401);
        }

        return redirect()->route('login')->with('status', $message);
    }
}
