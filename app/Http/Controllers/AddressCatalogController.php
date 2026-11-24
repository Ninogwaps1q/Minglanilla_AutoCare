<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\Municipality;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressCatalogController extends Controller
{
    public function regions(): JsonResponse
    {
        return response()->json(Region::query()->orderBy('name')->get(['id', 'name', 'psgc_code']));
    }

    public function provinces(Request $request): JsonResponse
    {
        $filters = $request->validate(['region_id' => ['required', 'integer', 'exists:regions,id']]);

        return response()->json(Province::query()->where('region_id', $filters['region_id'])
            ->orderBy('name')->get(['id', 'name', 'psgc_code']));
    }

    public function municipalities(Request $request): JsonResponse
    {
        $filters = $request->validate(['province_id' => ['required', 'integer', 'exists:provinces,id']]);

        return response()->json(Municipality::query()->where('province_id', $filters['province_id'])
            ->orderBy('name')->get(['id', 'name', 'psgc_code']));
    }

    public function barangays(Request $request): JsonResponse
    {
        $filters = $request->validate(['municipality_id' => ['required', 'integer', 'exists:municipalities,id']]);

        return response()->json(Barangay::query()->where('municipality_id', $filters['municipality_id'])
            ->orderBy('name')->get(['id', 'name', 'psgc_code']));
    }

    public function path(Request $request): JsonResponse
    {
        $filters = $request->validate(['barangay_id' => ['required', 'integer', 'exists:barangays,id']]);
        $barangay = Barangay::query()->with('municipality.province.region')->findOrFail($filters['barangay_id']);

        return response()->json([
            'region_id' => $barangay->municipality->province->region_id,
            'province_id' => $barangay->municipality->province_id,
            'municipality_id' => $barangay->municipality_id,
            'barangay_id' => $barangay->id,
        ]);
    }
}
