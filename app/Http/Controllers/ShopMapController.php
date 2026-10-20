<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShopMapController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'barangay' => ['nullable', 'integer', 'exists:barangays,id'],
            'service' => ['nullable', 'integer', 'exists:services,id'],
            'sort' => ['nullable', 'in:name,rating,distance'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
        ]);
        if (isset($filters['latitude'], $filters['longitude']) && ! isset($filters['sort'])) {
            $filters['sort'] = 'distance';
        }

        return redirect()->to(route('shops.index', array_filter($filters, fn ($value): bool => $value !== null && $value !== '')).'#shop-map');
    }
}
