<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\RepairJob;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RepairJobController extends Controller
{
    public function index(Request $request): View
    {
        return view('customer.repairs.index', [
            'repairJobs' => RepairJob::query()
                ->whereBelongsTo($request->user(), 'customer')
                ->when($request->integer('appointment_id') > 0, fn ($query) => $query->where('appointment_id', $request->integer('appointment_id')))
                ->with(['shop', 'vehicle', 'items', 'quotation.items', 'technicianAssignments.technician'])
                ->latest()->paginate()->withQueryString(),
        ]);
    }
}
