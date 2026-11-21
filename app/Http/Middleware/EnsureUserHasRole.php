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
        $user = $request->user();
        $status = $user?->status ?? $user?->fresh()?->status;
        abort_unless($user && $status === 'active' && in_array($user->role, $roles, true), 403);
        if (in_array('admin', $roles, true) && app(AdminTwoFactor::class)->isPending($request)) {
            return redirect()->route('admin.two-factor.create');
        }

        return $next($request);
    }
}
