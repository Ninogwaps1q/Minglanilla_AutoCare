<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\EmergencyRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\RepairJob;
use App\Services\AI\AiServiceStatus;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MonitoringController extends Controller
{
    public function index(Request $request, AiServiceStatus $aiServiceStatus): View
    {
        $modules = [
            'appointments' => ['Appointments', Appointment::class, 'shop', ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled']],
            'repairs' => ['Repairs', RepairJob::class, 'shop', ['pending', 'diagnosis', 'waiting_approval', 'approved', 'repairing', 'in_progress', 'waiting_parts', 'completed', 'released', 'cancelled']],
            'quotations' => ['Quotations', Quotation::class, 'shop', ['draft', 'requested', 'sent', 'approved', 'rejected', 'expired']],
            'invoices' => ['Invoices', Invoice::class, 'shop', ['unpaid', 'paid', 'cancelled']],
            'payments' => ['Payments', Payment::class, 'shop', ['pending', 'confirmed', 'rejected']],
            'emergencies' => ['Roadside assistance', EmergencyRequest::class, 'assignedShop', ['pending', 'accepted', 'en_route', 'completed', 'cancelled']],
        ];
        $referenceColumns = ['repairs' => 'job_number', 'quotations' => 'quotation_number', 'invoices' => 'invoice_number', 'payments' => 'reference_number'];
        $attentionStatuses = [
            'appointments' => ['pending'], 'repairs' => ['waiting_approval', 'waiting_parts'],
            'quotations' => ['requested', 'sent'], 'invoices' => ['unpaid'],
            'payments' => ['pending'], 'emergencies' => ['pending'],
        ];
        $detailRelations = [
            'appointments' => ['vehicle:id,plate_number', 'service:id,name', 'serviceCase:id,appointment_id,subject', 'repairJob:id,appointment_id,job_number', 'statusHistories.changedBy:id,name'],
            'repairs' => ['vehicle:id,plate_number', 'appointment:id,appointment_date,appointment_time', 'quotation:id,quotation_number,status', 'invoice:id,repair_job_id,invoice_number,status', 'statusHistories.changedBy:id,name'],
            'quotations' => ['repairJob:id,job_number', 'approvalRecords.customer:id,name'],
            'invoices' => ['repairJob:id,job_number', 'payments.reviewedBy:id,name'],
            'payments' => ['invoice:id,invoice_number,status', 'reviewedBy:id,name'],
            'emergencies' => ['vehicle:id,plate_number'],
        ];
        $filters = $request->validate([
            'module' => ['nullable', Rule::in(array_keys($modules))],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
            'attention' => ['nullable', 'boolean'],
        ]);
        $module = $filters['module'] ?? 'appointments';
        [$label, $model, $shopRelation, $statuses] = $modules[$module];
        $request->validate(['status' => ['nullable', Rule::in($statuses)]]);
        $records = $model::query()->with(['customer:id,name', $shopRelation.':id,name', ...$detailRelations[$module]])
            ->when((bool) ($filters['attention'] ?? false), fn ($query) => $query
                ->whereIn('status', $attentionStatuses[$module])
                ->where('created_at', '<=', now()->subMinutes($module === 'emergencies' ? 10 : 1440)))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function ($query, $search) use ($shopRelation, $referenceColumns, $module): void {
                $query->where(function ($query) use ($search, $shopRelation, $referenceColumns, $module): void {
                    $query->whereHas('customer', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas($shopRelation, fn ($query) => $query->where('name', 'like', '%'.$search.'%'));
                    if (isset($referenceColumns[$module])) {
                        $query->orWhere($referenceColumns[$module], 'like', '%'.$search.'%');
                    }
                    if (ctype_digit((string) $search)) {
                        $query->orWhere($query->getModel()->getQualifiedKeyName(), (int) $search);
                    }
                });
            })->latest('id')->paginate(20)->withQueryString();

        return view('admin.monitoring.index', [
            ...compact('modules', 'module', 'label', 'statuses', 'filters', 'records', 'shopRelation'),
            'aiService' => [
                ...$aiServiceStatus->snapshot(),
                'provider' => config('ai.provider'),
                'current_model' => config('ai.model'),
                'configured' => is_string(config('ai.api_key')) && trim(config('ai.api_key')) !== '',
            ],
        ]);
    }
}
