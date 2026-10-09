<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShopRegistrationRequest;
use App\Models\Barangay;
use App\Models\Service;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ShopRegistrationController extends Controller
{
    public function create(): View
    {
        return view('shop.register', [
            'barangays' => Barangay::query()->where('status', 'active')->orderBy('name')->get(),
            'services' => Service::query()->with('category')->orderBy('name')->get(),
        ]);
    }

    public function store(ShopRegistrationRequest $request): RedirectResponse
    {
        $attributes = $request->safe()->except(['service_ids', 'logo', 'cover_photo']);
        $services = Service::query()->whereKey($request->validated('service_ids', []))->get(['id', 'default_price']);

        foreach (['logo' => 'shop-logos', 'cover_photo' => 'shop-covers'] as $field => $directory) {
            if ($request->hasFile($field)) {
                $attributes[$field] = $request->file($field)->store($directory, 'public');
            }
        }

        try {
            DB::transaction(function () use ($attributes, $request, $services): void {
                $shop = Shop::query()->create([...$attributes, 'owner_id' => $request->user()->id]);

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

        return redirect()->route('dashboard')->with('status', 'Your shop registration was submitted for verification. You can update service prices after approval.');
    }
}
