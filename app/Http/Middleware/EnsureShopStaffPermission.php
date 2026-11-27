<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureShopStaffPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user?->role === 'mechanic') {
            $route = $request->route()?->getName() ?? '';
            abort_if(str_starts_with($route, 'shop.inventory.')
                || str_starts_with($route, 'shop.reports.')
                || str_starts_with($route, 'shop.promotions.')
                || in_array($route, ['shop.exports.index', 'shop.repair-job-items.consume', 'shop.repair-job-items.reserve', 'shop.repair-job-items.return'], true), 403);
        }
        if ($user?->role === 'shop_staff') {
            $permissions = [
                'receptionist' => ['dashboard', 'today-work.', 'appointments.', 'service-cases.', 'messages.', 'emergency-requests.', 'quotations.', 'reviews.', 'repair-jobs.index', 'repair-jobs.create', 'repair-jobs.store'],
                'technician' => ['dashboard', 'today-work.', 'repair-jobs.', 'repair-job-items.', 'repair-photos.', 'inspections.', 'inventory.', 'service-cases.index'],
                'cashier' => ['dashboard', 'invoices.', 'payments.', 'reports.'],
            ];
            $route = str($request->route()?->getName() ?? '')->after('shop.')->toString();
            $allowed = collect($permissions[$user->staff_role] ?? [])->contains(fn (string $prefix): bool => str_starts_with($route, $prefix));
            abort_unless($user->status === 'active' && $user->staffShop && $user->staffShop->owner_id && $allowed, 403);
        }

        return $next($request);
    }
}
