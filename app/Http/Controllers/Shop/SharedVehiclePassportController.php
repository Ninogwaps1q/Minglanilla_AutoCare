<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\VehiclePassportShare;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

class SharedVehiclePassportController extends Controller
{
    public function __invoke(Request $request, VehiclePassportShare $passportShare): Response
    {
        $shop = $request->user()->shops()->firstOrFail();
        abort_unless($shop->verification_status === 'approved' && $shop->shop_status === 'active', 403);
        abort_unless($passportShare->revoked_at === null && $passportShare->expires_at->isFuture(), 404);

        $vehicle = $passportShare->vehicle;
        $records = $vehicle->serviceRecords()->with('shop:id,name')->latest('service_date')->paginate(20);
        $confirmedAppointment = $shop->appointments()->where('vehicle_id', $vehicle->id)
            ->where('status', 'confirmed')->orderBy('appointment_date')->first();

        return response()->view('shop.passports.shared', [
            'vehicle' => $vehicle,
            'records' => $records,
            'expiresAt' => $passportShare->expires_at,
            'confirmedAppointment' => $confirmedAppointment,
            'previousUrl' => $records->currentPage() > 1
                ? URL::temporarySignedRoute('shop.passports.shared', $passportShare->expires_at, ['passportShare' => $passportShare, 'page' => $records->currentPage() - 1])
                : null,
            'nextUrl' => $records->hasMorePages()
                ? URL::temporarySignedRoute('shop.passports.shared', $passportShare->expires_at, ['passportShare' => $passportShare, 'page' => $records->currentPage() + 1])
                : null,
        ])->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
