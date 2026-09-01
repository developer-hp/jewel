<?php

namespace App\Http\Middleware;

use App\Services\ActivityRecorder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * One activity row per page a signed-in user actually looks at.
 *
 * By far the biggest source of rows in the log, which is why so much is excluded:
 * what is wanted is "who was on this screen", not a transcript of every HTTP request
 * the browser made.
 */
class LogPageView
{
    public function __invoke(Request $request, Closure $next): Response
    {
        return $this->handle($request, $next);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->shouldLog($request, $response)) {
            app(ActivityRecorder::class)->record(
                log: 'page',
                description: $request->route()?->getName() ?? $request->path(),
                properties: array_filter([
                    'path' => '/'.ltrim($request->path(), '/'),
                    'route' => $request->route()?->getName(),
                    'ip' => $request->ip(),
                    'status' => $response->getStatusCode(),
                ]),
                event: 'view',
            );
        }

        return $response;
    }

    /**
     * Logged after the response, so the status can be recorded — a 403 on a screen is
     * more interesting than a 200.
     */
    private function shouldLog(Request $request, Response $response): bool
    {
        if (! config('activity-log.enabled', true) || ! config('activity-log.log_page_views', true)) {
            return false;
        }

        // A page view is a person looking at a screen. A guest has no identity worth
        // recording, and the login screen would otherwise be the most-viewed page in
        // the log.
        if (! $request->user()) {
            return false;
        }

        // Writes already produce a `data` row that says far more than the URL does.
        if (! $request->isMethod('GET')) {
            return false;
        }

        // Every DataTables redraw and every lookup is its own GET. Logging them would
        // bury the real page views under ten times their number.
        if ($request->ajax() || $request->wantsJson()) {
            return false;
        }

        // A redirect is not a screen anybody read.
        if ($response->isRedirection()) {
            return false;
        }

        return ! $this->matchesSkipPath($request);
    }

    /**
     * Match against the path with the admin prefix taken off.
     *
     * config('activity-log.skip_paths') is written app-relative — `session/heartbeat`,
     * not `admin/session/heartbeat` — so moving the back office under a prefix must
     * not quietly stop the heartbeat being skipped.
     */
    private function matchesSkipPath(Request $request): bool
    {
        $paths = (array) config('activity-log.skip_paths', []);

        if ($paths === []) {
            return false;
        }

        $path = trim($request->path(), '/');
        $prefix = trim((string) config('app.admin_prefix'), '/');

        if ($prefix !== '' && str_starts_with($path, $prefix.'/')) {
            $path = substr($path, strlen($prefix) + 1);
        }

        foreach ($paths as $pattern) {
            if (Str::is($pattern, $path)) {
                return true;
            }
        }

        return false;
    }
}
