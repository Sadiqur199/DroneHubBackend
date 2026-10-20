<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EnsureVisible
{
    /**
     * Handle an incoming request.
     * Non-negotiable rule: Returns 404 (not 403) so hidden wholesale or agent pages remain completely secret to unauthorized groups.
     */
    public function handle(Request $request, Closure $next, string ...$allowedGroups): Response
    {
        $customerGroup = $request->attributes->get('customer_group');
        $groupSlug = $customerGroup->slug ?? 'retail';

        if (!empty($allowedGroups) && !in_array($groupSlug, $allowedGroups, true)) {
            // Abort with 404 Not Found to prevent leaking page existence
            abort(404, 'The requested resource was not found.');
        }

        return $next($request);
    }
}
