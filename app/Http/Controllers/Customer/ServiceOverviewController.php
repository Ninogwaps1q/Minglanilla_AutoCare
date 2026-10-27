<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\ServiceCase;
use App\Models\VehicleServiceRecord;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceOverviewController extends Controller
{
    public function __invoke(Request $request): View
    {
        $customer = $request->user();

        return view('customer.services.index', [
            'appointments' => $customer->appointments()
                ->with(['shop', 'vehicle', 'service'])
                ->whereIn('status', ['pending', 'confirmed', 'in_progress'])
                ->orderBy('appointment_date')->limit(6)->get(),
            'repairs' => $customer->repairJobs()
                ->with(['shop', 'vehicle', 'warranty', 'quotation', 'pendingSupplementalQuotation', 'invoice.payments', 'appointment'])
                ->whereNotIn('status', ['completed', 'released', 'cancelled'])
                ->latest()->limit(6)->get(),
            'records' => VehicleServiceRecord::query()
                ->where('customer_id', $customer->id)
                ->with(['shop', 'vehicle', 'repairJob.warranty'])
                ->latest('service_date')->limit(6)->get(),
            'openCaseCount' => ServiceCase::query()->where('customer_id', $customer->id)
                ->whereNotIn('status', ['resolved', 'declined', 'dismissed'])
                ->count(),
        ]);
    }
}
