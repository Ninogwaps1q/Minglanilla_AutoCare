<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\VehicleInspection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InspectionController extends Controller
{
    public function index(Request $request): View
    {
        return view('customer.inspections.index', [
            'inspections' => VehicleInspection::query()
                ->whereHas('vehicle', fn ($query) => $query->where('customer_id', $request->user()->id))
                ->with(['vehicle', 'shop', 'items'])
                ->orderByDesc('inspection_date')->orderByDesc('id')->paginate(),
        ]);
    }
}
