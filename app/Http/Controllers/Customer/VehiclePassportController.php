<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class VehiclePassportController extends Controller
{
    public function show(Vehicle $vehicle): View
    {
        $this->authorize('view', $vehicle);

        $activeShare = $vehicle->passportShares()->whereNull('revoked_at')->where('expires_at', '>', now())->latest()->first();

        return view('customer.vehicles.passport', [
            'vehicle' => $vehicle,
            'records' => $vehicle->serviceRecords()->with('shop:id,name')->latest('service_date')->paginate(20),
            'activeShare' => $activeShare,
            'shareUrl' => $activeShare ? URL::temporarySignedRoute('shop.passports.shared', $activeShare->expires_at, ['passportShare' => $activeShare]) : null,
        ]);
    }

    public function qr(Vehicle $vehicle): Response
    {
        $this->authorize('view', $vehicle);
        $writer = new Writer(new ImageRenderer(new RendererStyle(320, 32), new SvgImageBackEnd));

        return response($writer->writeString(route('customer.vehicles.passport', $vehicle)), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => 'inline; filename="vehicle-passport.svg"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
