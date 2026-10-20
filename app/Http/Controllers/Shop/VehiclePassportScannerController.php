<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\VehiclePassportShare;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

class VehiclePassportScannerController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureShopCanViewPassports($request);

        return response()->view('shop.passports.scan')
            ->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function open(Request $request): RedirectResponse
    {
        $this->ensureShopCanViewPassports($request);

        $validated = $request->validate([
            'share_url' => ['required', 'url', 'max:2048'],
        ], [
            'share_url.required' => 'Scan a customer passport QR or paste its sharing link.',
            'share_url.url' => 'Enter the full sharing link shown on the customer passport.',
            'share_url.max' => 'This sharing link is too long. Ask the customer to create a new one.',
        ]);

        $shareUrl = trim($validated['share_url']);
        $parts = parse_url($shareUrl);
        $passportShareId = is_array($parts) ? basename($parts['path'] ?? '') : '';
        $sameOrigin = is_array($parts)
            && strtolower($parts['scheme'] ?? '') === strtolower($request->getScheme())
            && strtolower($parts['host'] ?? '') === strtolower($request->getHost())
            && ($parts['port'] ?? (strtolower($parts['scheme'] ?? '') === 'https' ? 443 : 80)) === $request->getPort();

        if (! $sameOrigin || ! ctype_digit($passportShareId)
            || ($parts['path'] ?? '') !== route('shop.passports.shared', ['passportShare' => $passportShareId], false)
            || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass'])
            || ! URL::hasValidSignature(Request::create($shareUrl, 'GET'))) {
            return back()->withErrors(['share_url' => 'This is not a valid shop-sharing QR for this website. Ask the customer for a new link.']);
        }

        $passportShare = VehiclePassportShare::query()->find($passportShareId);

        if ($passportShare === null || $passportShare->revoked_at !== null || ! $passportShare->expires_at->isFuture()) {
            return back()->withErrors(['share_url' => 'This passport link has expired or was revoked. Ask the customer to create a new one.']);
        }

        return redirect()->to($shareUrl);
    }

    private function ensureShopCanViewPassports(Request $request): void
    {
        $shop = $request->user()->shops()->firstOrFail();

        abort_unless($shop->verification_status === 'approved' && $shop->shop_status === 'active', 403);
    }
}
