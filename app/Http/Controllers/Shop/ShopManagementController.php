<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShopServiceRequest;
use App\Http\Requests\UpdateShopProfileRequest;
use App\Models\Barangay;
use App\Models\Service;
use App\Models\Shop;
use App\Models\ShopService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ShopManagementController extends Controller
{
    public function editProfile(Request $request): View
    {
        return view('shop.profile.edit', ['shop' => $this->shop($request)->load('documents'), 'barangays' => Barangay::query()->where('status', 'active')->orderBy('name')->get()]);
    }

    public function updateProfile(UpdateShopProfileRequest $request): RedirectResponse
    {
        $shop = $this->shop($request);
        $attributes = $request->safe()->except(['logo', 'cover_photo']);
        $newPaths = [];
        $previousPaths = [];

        try {
            foreach (['logo' => 'shop-logos', 'cover_photo' => 'shop-covers'] as $field => $directory) {
                if (! $request->hasFile($field)) {
                    continue;
                }

                $attributes[$field] = $request->file($field)->store($directory, 'public');
                if (! $attributes[$field]) {
                    throw new \RuntimeException('Could not save the shop image. Please try again.');
                }
                $newPaths[] = $attributes[$field];
                if ($shop->{$field}) {
                    $previousPaths[] = $shop->{$field};
                }
            }

            if (! $shop->update($attributes)) {
                throw new \RuntimeException('Could not update the shop profile. Please try again.');
            }
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($newPaths);

            throw $exception;
        }

        Storage::disk('public')->delete($previousPaths);

        return back()->with('status', 'Shop profile updated.');
    }

    public function services(Request $request): View
    {
        return view('shop.services.index', ['shopServices' => $this->shop($request)->services()->with('service.category')->latest()->get(), 'services' => Service::query()->with('category')->orderBy('name')->get()]);
    }

    public function storeService(StoreShopServiceRequest $request): RedirectResponse
    {
        $this->shop($request)->services()->updateOrCreate(['service_id' => $request->integer('service_id')], $request->validated());

        return back()->with('status', 'Service saved.');
    }

    public function destroyService(ShopService $shopService, Request $request): RedirectResponse
    {
        abort_unless($shopService->shop_id === $this->shop($request)->id, 404);
        $shopService->delete();

        return back()->with('status', 'Service removed.');
    }

    private function shop(Request $request): Shop
    {
        return $request->user()->shops()->firstOrFail();
    }
}
