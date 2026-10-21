<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateToken
{
    /**
     * Authenticate the request via `Authorization: Bearer <token>`.
     *
     * The token itself is a 64 char random hex string; only its SHA-256 hash is
     * persisted, so a database leak cannot be replayed as a valid session.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required. Please sign in.'
            ], 401);
        }

        $record = DB::table('api_tokens')
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired session token.'
            ], 401);
        }

        if ($record->expires_at && now()->gt($record->expires_at)) {
            DB::table('api_tokens')->where('id', $record->id)->delete();

            return response()->json([
                'success' => false,
                'message' => 'Your session has expired. Please sign in again.'
            ], 401);
        }

        $user = DB::table('users')
            ->where('id', $record->user_id)
            ->whereNull('deleted_at')
            ->first();

        if (!$user || $user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Account is not active.'
            ], 403);
        }

        DB::table('api_tokens')->where('id', $record->id)->update([
            'last_used_at' => now(),
        ]);

        $request->setUserResolver(fn () => $user);
        $request->attributes->set('auth_token_id', $record->id);

        $this->resolveCustomerGroup($request, $user);

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if ($header && str_starts_with($header, 'Bearer ')) {
            return trim(substr($header, 7));
        }

        return null;
    }

    /**
     * Attach the customer group (retail / wholesale / agent) to the request.
     * Guests fall back to the default retail group.
     */
    private function resolveCustomerGroup(Request $request, object $user): void
    {
        $group = DB::table('customer_groups')
            ->where('id', $user->customer_group_id ?: 1)
            ->first();

        if (!$group) {
            $group = DB::table('customer_groups')->where('is_default', true)->first();
        }

        if (!$group) {
            $group = (object) ['id' => 1, 'name' => 'Retail Customer', 'slug' => 'retail', 'is_default' => true];
        }

        $request->attributes->set('customer_group', $group);
        $request->attributes->set('customer_group_slug', $group->slug);
    }
}
