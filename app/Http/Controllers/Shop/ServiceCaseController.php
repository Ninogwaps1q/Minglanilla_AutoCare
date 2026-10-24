<?php

namespace App\Http\Controllers\Shop;

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
        $shop = $request->user()->shops()->firstOrFail();

        return view('shop.service-cases.index', [
            'cases' => ServiceCase::query()->where('shop_id', $shop->id)
                ->when($request->integer('focus') > 0, fn ($query) => $query->whereKey($request->integer('focus')))
                ->with(['customer', 'repairJob.vehicle', 'events.actor'])->latest()->paginate(12)->withQueryString(),
        ]);
    }

    public function update(Request $request, ServiceCase $serviceCase): RedirectResponse
    {
        abort_unless($serviceCase->shop_id === $request->user()->shops()->firstOrFail()->id, 404);
        $data = $request->validate([
            'shop_response' => ['required', 'string', 'min:5', 'max:3000'],
            'status' => ['nullable', Rule::in(['reviewing', 'scheduled', 'resolved', 'declined'])],
        ]);

        DB::transaction(function () use ($serviceCase, $request, $data): void {
            $case = ServiceCase::query()->lockForUpdate()->findOrFail($serviceCase->id);
            if (in_array($case->status, ['resolved', 'declined', 'dismissed'], true)) {
                throw ValidationException::withMessages(['status' => 'This request is closed. Open a new case if more help is needed.']);
            }
            $nextStatus = $data['status'] ?? ($case->type === 'complaint' ? 'reviewing' : $case->status);
            if ($case->type === 'complaint' && $nextStatus !== 'reviewing') {
                throw ValidationException::withMessages(['status' => 'The admin team resolves complaints. You can respond and mark this case as reviewing.']);
            }
            $previousStatus = $case->status;
            $case->update(['shop_response' => $data['shop_response'], 'status' => $nextStatus,
                'resolved_at' => in_array($nextStatus, ['resolved', 'declined'], true) ? now() : null,
            ]);
            $case->events()->create([
                'actor_id' => $request->user()->id, 'from_status' => $previousStatus,
                'to_status' => $nextStatus, 'note' => $data['shop_response'],
            ]);
            $case->customer->notifications()->create([
                'type' => 'service_case', 'title' => 'Update on your aftercare request',
                'message' => $case->subject.': '.str_replace('_', ' ', $nextStatus),
                'data' => ['service_case_id' => $case->id],
            ]);
        });

        return back()->with('status', 'Response saved and customer notified.');
    }
}
