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
                ->when($request->integer('repair_job_id') > 0, fn ($query) => $query->whereKey($request->integer('repair_job_id')))
                ->with(['shop', 'vehicle', 'appointment.service', 'items', 'photos', 'warranty', 'serviceCases', 'quotation.items', 'quotation.approvalRecords.customer', 'pendingSupplementalQuotation', 'supplementalQuotations', 'invoice.issuedBy', 'invoice.payments.customer', 'invoice.payments.reviewedBy', 'statusHistories.changedBy', 'technicianAssignments.technician'])
                ->latest()->paginate()->withQueryString(),
        ]);
    }
}
