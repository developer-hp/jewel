<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets the ordinary controllers answer an AJAX call without being rewritten.
 *
 * Every destroy() in this app ends the same way — `redirect()->route(...)` carrying a
 * success or error in the session — which is exactly right for a form post and useless
 * to a fetch. Rather than teach twenty controllers to branch on the request, this turns
 * that redirect into JSON on the way out, for callers that asked for JSON.
 *
 * The flash is *pulled*, not read: leaving it in the session would show the same
 * message again on whatever page the user loads next.
 */
class AnswerAjaxRedirectsWithJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response instanceof RedirectResponse) {
            return $response;
        }

        // Only for callers that explicitly asked; a browser form post must still
        // follow its redirect as it always has.
        if (! $request->ajax() && ! $request->wantsJson()) {
            return $response;
        }

        $session = $request->session();
        $error = $session->pull('error');
        $success = $session->pull('success');

        return response()->json([
            'ok' => $error === null,
            'message' => $error ?? $success,
            'redirect' => $response->getTargetUrl(),
        ], $error === null ? 200 : 422);
    }
}
