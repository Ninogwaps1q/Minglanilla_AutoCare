<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ServiceCaseController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $status = in_array($status, ['open', 'reviewing', 'resolved', 'dismissed'], true) ? $status : null;

        return view('admin.service-cases.index', [
            'cases' => ServiceCase::query()->where('type', 'complaint')
                ->when($status, fn ($query) => $query->where('status', $status))
                ->with(['customer', 'shop', 'repairJob.vehicle', 'repairJob.photos', 'events.actor', 'resolvedBy'])
                ->latest()->paginate(15)->withQueryString(),
            'selectedStatus' => $status,
            'openCount' => ServiceCase::query()->where('type', 'complaint')
                ->whereIn('status', ['open', 'reviewing'])->count(),
        ]);
    }

    public function update(Request $request, ServiceCase $serviceCase): RedirectResponse
    {
        abort_unless($serviceCase->type === 'complaint', 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(['reviewing', 'resolved', 'dismissed'])],
            'admin_resolution' => ['required_if:status,resolved,dismissed', 'nullable', 'string', 'min:10', 'max:3000'],
        ]);

        DB::transaction(function () use ($serviceCase, $request, $data): void {
            $case = ServiceCase::query()->lockForUpdate()->findOrFail($serviceCase->id);
            if (in_array($case->status, ['resolved', 'dismissed'], true)) {
                throw ValidationException::withMessages(['status' => 'This complaint is already closed.']);
            }
            $previousStatus = $case->status;
            $case->update([
                'status' => $data['status'],
                'admin_resolution' => $data['admin_resolution'] ?? null,
                'resolved_by' => in_array($data['status'], ['resolved', 'dismissed'], true) ? $request->user()->id : null,
                'resolved_at' => in_array($data['status'], ['resolved', 'dismissed'], true) ? now() : null,
            ]);
            $case->events()->create([
                'actor_id' => $request->user()->id, 'from_status' => $previousStatus,
                'to_status' => $data['status'],
                'note' => $data['admin_resolution'] ?? 'Complaint is under admin review.',
            ]);
            foreach ([$case->customer, $case->shop->owner] as $recipient) {
                $recipient->notifications()->create([
                    'type' => 'service_case', 'title' => 'Complaint status updated',
                    'message' => $case->subject.': '.$data['status'],
                    'data' => ['service_case_id' => $case->id],
                ]);
            }
        });

        return back()->with('status', 'Complaint status and resolution saved.');
    }
}
