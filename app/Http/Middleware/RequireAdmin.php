<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAdmin
{
    /**
     * Only Super Admin / Admin / Operations may reach admin endpoints.
     * Must run after AuthenticateToken.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required. Please sign in.'
            ], 401);
        }

        $adminRoles = ['super_admin', 'admin', 'operations'];

        if (!in_array($user->role, $adminRoles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Administrator privileges are required for this action.'
            ], 403);
        }

        return $next($request);
    }
}
