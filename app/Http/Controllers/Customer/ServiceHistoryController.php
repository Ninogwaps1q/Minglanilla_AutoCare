<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\VehicleServiceRecord;
use Illuminate\View\View;

class ServiceHistoryController extends Controller
{
    public function index(): View
    {
        return view('customer.service-history.index', ['records' => VehicleServiceRecord::query()->whereBelongsTo(auth()->user(), 'customer')->with(['vehicle', 'shop', 'repairJob.warranty', 'repairJob.invoice'])->latest('service_date')->paginate(20)]);
    }
}
