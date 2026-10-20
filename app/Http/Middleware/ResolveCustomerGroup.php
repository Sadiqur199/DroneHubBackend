<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCustomerGroup
{
    /**
     * Handle an incoming request.
     * Ensures every request has a customer group attached (Guests default to 'retail').
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->customer_group_id) {
            $customerGroup = $user->customerGroup;
        } else {
            // Default to retail for guests
            $customerGroup = (object) [
                'id' => 1,
                'name' => 'Retail',
                'slug' => 'retail',
                'is_default' => true,
            ];
        }

        $request->attributes->set('customer_group', $customerGroup);

        return $next($request);
    }
}
