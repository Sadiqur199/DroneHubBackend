<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ResolveCustomerGroup
{
    /**
     * Handle an incoming request.
     * Ensures every request has a customer group attached (Guests default to 'retail').
     *
     * Runs before AuthenticateToken; when a valid bearer token is present the
     * authenticated user's own group overwrites this default.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->attributes->has('customer_group')) {
            $group = DB::table('customer_groups')->where('is_default', true)->first();

            if (!$group) {
                $group = (object) [
                    'id'          => 1,
                    'name'        => 'Retail Customer',
                    'slug'        => 'retail',
                    'is_default'  => true,
                ];
            }

            $request->attributes->set('customer_group', $group);
            $request->attributes->set('customer_group_slug', $group->slug);
        }

        return $next($request);
    }
}
