<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\RepairJob;
use Illuminate\Database\Eloquent\Builder;
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
                ->with(['shop', 'vehicle', 'appointment.service', 'appointment.bookedServices.service', 'inspections.items', 'items', 'photos', 'warranty', 'serviceCases', 'quotation.items', 'quotation.approvalRecords.customer', 'pendingSupplementalQuotation', 'supplementalQuotations', 'invoice.issuedBy', 'invoice.payments.customer', 'invoice.payments.reviewedBy', 'statusHistories.changedBy', 'technicianAssignments.technician'])
                ->withExists([
                    'quotation as estimate_needs_response' => fn (Builder $query) => $query->where('status', 'sent')->where(fn (Builder $valid) => $valid->whereNull('valid_until')->orWhereDate('valid_until', '>=', today())),
                    'supplementalQuotations as revision_needs_response' => fn (Builder $query) => $query->where('is_supplemental', true)->where('status', 'sent')->where(fn (Builder $valid) => $valid->whereNull('valid_until')->orWhereDate('valid_until', '>=', today())),
                    'invoice as has_open_bill' => fn (Builder $query) => $query->where('status', '!=', 'paid'),
                ])
                ->orderByDesc('estimate_needs_response')->orderByDesc('revision_needs_response')->orderByDesc('has_open_bill')
                ->orderByRaw("CASE WHEN status IN ('released', 'cancelled') THEN 1 ELSE 0 END")
                ->latest()->paginate()->withQueryString(),
        ]);
    }
}
