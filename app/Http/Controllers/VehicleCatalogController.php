<?php

namespace App\Http\Controllers;

use App\Models\VehicleMake;
use App\Models\VehicleModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleCatalogController extends Controller
{
    public function makes(Request $request): JsonResponse
    {
        $filters = $request->validate(['type' => ['required', 'in:car,motorcycle']]);

        return response()->json(VehicleMake::query()->where('vehicle_type', $filters['type'])
            ->orderBy('name')->get(['id', 'name']));
    }

    public function models(Request $request): JsonResponse
    {
        $filters = $request->validate(['make_id' => ['required', 'integer', 'exists:vehicle_makes,id']]);

        return response()->json(VehicleModel::query()->where('vehicle_make_id', $filters['make_id'])
            ->orderBy('name')->get(['id', 'name', 'variants']));
    }
}
