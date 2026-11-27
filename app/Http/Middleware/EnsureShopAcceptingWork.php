<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class EnsureShopAcceptingWork
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $minimum = 'basic'): Response
    {
        $shop = $request->user()?->managedShop();
        if ($shop === null || $shop->verification_status !== 'approved' || $shop->shop_status !== 'active') {
            throw ValidationException::withMessages(['shop' => 'Your shop must be approved and active before accepting new work. Existing invoices and customer requests remain available.']);
        }
        if (! $shop->isIndependentMechanic() && ! $shop->hasPlanAtLeast($minimum)) {
            throw ValidationException::withMessages(['shop' => "An active {$minimum} plan is needed for this action. Your existing records remain available."]);
        }

        return $next($request);
    }
}
