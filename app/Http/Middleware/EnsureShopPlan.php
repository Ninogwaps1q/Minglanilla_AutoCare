<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureShopPlan
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $minimum = 'basic'): Response
    {
        $shop = $request->user()->managedShopOrFail();
        if (! $shop->hasPlanAtLeast($minimum) && ! ($shop->isIndependentMechanic() && $request->routeIs('shop.repair-jobs.create'))) {
            if ($request->expectsJson()) {
                abort(403, "An active {$minimum} plan is required.");
            }

            return redirect()->route('shop.pricing')->withErrors(['plan' => "An active {$minimum} plan is required for this feature."]);
        }

        return $next($request);
    }
}
