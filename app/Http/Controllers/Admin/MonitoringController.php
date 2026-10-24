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
        $filters = $request->validate([
            'module' => ['nullable', Rule::in(array_keys($modules))],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:30'],
        ]);
        $module = $filters['module'] ?? 'appointments';
        [$label, $model, $shopRelation, $statuses] = $modules[$module];
        $request->validate(['status' => ['nullable', Rule::in($statuses)]]);
        $records = $model::query()->with(['customer:id,name', $shopRelation.':id,name'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function ($query, $search) use ($shopRelation): void {
                $query->where(function ($query) use ($search, $shopRelation): void {
                    $query->whereHas('customer', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas($shopRelation, fn ($query) => $query->where('name', 'like', '%'.$search.'%'));
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
