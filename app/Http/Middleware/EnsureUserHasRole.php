<?php

namespace App\Http\Middleware;

use App\AdminTwoFactor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless($request->user() && in_array($request->user()->role, $roles, true), 403);
        if (in_array('admin', $roles, true) && app(AdminTwoFactor::class)->isPending($request)) {
            return redirect()->route('admin.two-factor.create');
        }

        return $next($request);
    }
}
