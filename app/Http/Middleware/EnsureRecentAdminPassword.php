<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRecentAdminPassword
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ((int) $request->session()->get('auth.password_confirmed_at', 0) < now()->subMinutes(15)->timestamp) {
            $name = $request->route()?->getName() ?? '';
            $returnRoute = match (true) {
                str_starts_with($name, 'admin.paymongo-') => 'admin.paymongo-settlements.index',
                str_starts_with($name, 'admin.subscription-billing.') => 'admin.subscription-billing.index',
                default => 'admin.shop-plans.index',
            };
            $request->session()->put('admin_financial_return', $returnRoute);

            return redirect()->route('password.confirm');
        }

        return $next($request);
    }
}
