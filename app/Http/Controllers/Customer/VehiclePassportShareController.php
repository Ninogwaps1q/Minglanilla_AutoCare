<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehiclePassportShare;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class VehiclePassportShareController extends Controller
{
    public function store(Vehicle $vehicle): RedirectResponse
    {
        $this->authorize('view', $vehicle);

        DB::transaction(function () use ($vehicle): void {
            Vehicle::query()->whereKey($vehicle->id)->lockForUpdate()->firstOrFail();
            $vehicle->passportShares()->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $vehicle->passportShares()->create(['expires_at' => now()->addDay()]);
        });

        return redirect()->route('customer.vehicles.passport', $vehicle)
            ->with('status', 'A new 24-hour shop sharing link is ready. Your previous link was revoked.');
    }

    public function destroy(Vehicle $vehicle, VehiclePassportShare $passportShare): RedirectResponse
    {
        $this->authorize('view', $vehicle);
        abort_unless($passportShare->vehicle_id === $vehicle->id, 404);

        $passportShare->update(['revoked_at' => now()]);

        return redirect()->route('customer.vehicles.passport', $vehicle)
            ->with('status', 'Shop sharing link revoked.');
    }

    public function qr(Vehicle $vehicle, VehiclePassportShare $passportShare): Response
    {
        $this->authorize('view', $vehicle);
        abort_unless($passportShare->vehicle_id === $vehicle->id && $passportShare->revoked_at === null && $passportShare->expires_at->isFuture(), 404);

        $link = URL::temporarySignedRoute('shop.passports.shared', $passportShare->expires_at, ['passportShare' => $passportShare]);
        $writer = new Writer(new ImageRenderer(new RendererStyle(320, 32), new SvgImageBackEnd));

        return response($writer->writeString($link), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => 'inline; filename="shared-vehicle-passport.svg"',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
