<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\RepairJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RepairWarrantyController extends Controller
{
    public function store(Request $request, RepairJob $repairJob): RedirectResponse
    {
        abort_unless($repairJob->shop_id === $request->user()->shops()->firstOrFail()->id, 404);
        $data = $request->validate([
            'coverage' => ['required', 'string', 'min:10', 'max:2000'],
            'duration_days' => ['required', 'integer', 'between:1,730'],
        ]);

        DB::transaction(function () use ($request, $repairJob, $data): void {
            $job = RepairJob::query()->lockForUpdate()->findOrFail($repairJob->id);
            if (! in_array($job->status, ['completed', 'released'], true) || $job->warranty()->exists()) {
                throw ValidationException::withMessages(['coverage' => 'Issue a warranty once, after the repair is completed.']);
            }
            $job->warranty()->create([
                'issued_by' => $request->user()->id,
                'coverage' => $data['coverage'],
                'valid_from' => today(),
                'valid_until' => today()->addDays((int) $data['duration_days']),
            ]);
            $job->customer->notifications()->create([
                'type' => 'repair_warranty', 'title' => 'Repair warranty issued',
                'message' => $job->job_number.': review your warranty coverage and expiry in My services.',
                'data' => ['repair_job_id' => $job->id],
            ]);
        });

        return back()->with('status', 'Warranty issued and shared with the customer.');
    }
}
