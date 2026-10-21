<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the customer group for public endpoints.
 *
 * When a valid bearer token is present the caller's own group (retail /
 * wholesale / agent) is used, so the storefront can never hand a wholesale
 * buyer a retail price sheet. Without a token the caller is treated as a guest
 * on the default retail group.
 */
class ResolveOptionalCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);

        if ($token) {
            $record = DB::table('api_tokens')
                ->where('token_hash', hash('sha256', $token))
                ->first();

            if ($record && (!$record->expires_at || now()->lte($record->expires_at))) {
                $user = DB::table('users')
                    ->where('id', $record->user_id)
                    ->whereNull('deleted_at')
                    ->first();

                if ($user && $user->status === 'active') {
                    $request->setUserResolver(fn () => $user);

                    $group = DB::table('customer_groups')
                        ->where('id', $user->customer_group_id ?: 1)
                        ->first();

                    if ($group) {
                        $request->attributes->set('customer_group', $group);
                        $request->attributes->set('customer_group_slug', $group->slug);
                    }
                }
            }
        }

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
}
