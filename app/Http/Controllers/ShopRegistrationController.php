<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShopRegistrationRequest;
use App\Models\Service;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ShopRegistrationController extends Controller
{
    public function create(): View
    {
        return view('shop.register', [
            'services' => Service::query()->with('category')->orderBy('name')->get(),
        ]);
    }

    public function store(ShopRegistrationRequest $request): RedirectResponse
    {
        $attributes = $request->safe()->except(['service_ids', 'logo', 'cover_photo', 'legal_consent']);
        $services = Service::query()->whereKey($request->validated('service_ids', []))->get(['id', 'default_price']);

        foreach (['logo' => 'shop-logos', 'cover_photo' => 'shop-covers'] as $field => $directory) {
            if ($request->hasFile($field)) {
                $attributes[$field] = $request->file($field)->store($directory, 'public');
            }
        }

        try {
            DB::transaction(function () use ($attributes, $request, $services): void {
                $owner = $request->user()->newQuery()->lockForUpdate()->findOrFail($request->user()->id);
                if ($owner->shops()->exists()) {
                    throw ValidationException::withMessages(['name' => 'You already have a provider profile. Update it from your dashboard.']);
                }
                $isMechanic = $owner->role === 'mechanic';
                $owner->recordLegalAcceptance();
                $shop = Shop::query()->create([
                    ...$attributes,
                    'owner_id' => $owner->id,
                    'provider_type' => $isMechanic ? 'independent_mechanic' : 'shop',
                    'booking_capacity' => 1,
                    'roadside_available' => false,
                    'roadside_radius_km' => $isMechanic ? 20 : null,
                ]);

                $shop->services()->createMany($services->map(fn (Service $service): array => [
                    'service_id' => $service->id,
                    'price' => $service->default_price ?? 0,
                    'availability' => true,
                ])->all());
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete(array_filter([$attributes['logo'] ?? null, $attributes['cover_photo'] ?? null]));

            throw $exception;
        }

        return redirect()->route($request->user()->role === 'mechanic' ? 'shop.dashboard' : 'dashboard')
            ->with('status', 'Your provider registration was submitted for verification. Upload the required documents from your profile.');
    }
}
