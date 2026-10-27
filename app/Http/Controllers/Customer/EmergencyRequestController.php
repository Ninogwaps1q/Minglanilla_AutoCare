<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmergencyRequest;
use App\Models\EmergencyRequest;
use App\Services\EmergencyDispatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmergencyRequestController extends Controller
{
    public function __construct(private readonly EmergencyDispatchService $dispatchService) {}

    public function index(Request $request): View
    {
        $requests = $request->user()->emergencyRequests()
            ->when($request->integer('focus') > 0, fn ($query) => $query->whereKey($request->integer('focus')))
            ->with(['vehicle', 'assignedShop'])->latest('requested_at')->get();
        $dispatchAttempts = DB::table('emergency_dispatch_attempts')
            ->join('shops', 'shops.id', '=', 'emergency_dispatch_attempts.shop_id')
            ->whereIn('emergency_request_id', $requests->modelKeys())
            ->orderBy('assigned_at')
            ->get(['emergency_request_id', 'shops.name as shop_name', 'emergency_dispatch_attempts.status', 'assigned_at', 'ended_at'])
            ->groupBy('emergency_request_id');

        return view('customer.emergency-requests.index', [
            'requests' => $requests,
            'vehicles' => $request->user()->vehicles()->orderBy('plate_number')->get(),
            'dispatchAttempts' => $dispatchAttempts,
        ]);
    }

    public function store(StoreEmergencyRequest $request): RedirectResponse
    {
        $attributes = $request->validated();
        if (isset($attributes['vehicle_id']) && ! $request->user()->vehicles()->whereKey($attributes['vehicle_id'])->exists()) {
            throw ValidationException::withMessages(['vehicle_id' => 'Select one of your own vehicles.']);
        }
        $emergencyRequest = DB::transaction(function () use ($attributes, $request): EmergencyRequest {
            $customer = $request->user()->newQuery()->lockForUpdate()->findOrFail($request->user()->id);
            if ($customer->emergencyRequests()->whereIn('status', ['pending', 'accepted', 'en_route'])->exists()) {
                throw ValidationException::withMessages(['emergency_type' => 'You already have an active roadside request. Track or cancel it before requesting help again.']);
            }
            $emergencyRequest = $request->user()->emergencyRequests()->create([...$attributes, 'status' => 'pending', 'requested_at' => now()]);
            $this->dispatchService->assignNext($emergencyRequest);

            return $emergencyRequest;
        });

        return back()->with('status', $emergencyRequest->assigned_shop_id === null ? 'Your request was recorded. No eligible nearby shop is currently available.' : 'Your request was sent to the nearest eligible shop.');
    }

    public function destroy(Request $request, EmergencyRequest $emergencyRequest): RedirectResponse
    {
        abort_unless($emergencyRequest->customer_id === $request->user()->id, 404);
        DB::transaction(function () use ($emergencyRequest, $request): void {
            $emergencyRequest = $request->user()->emergencyRequests()->lockForUpdate()->findOrFail($emergencyRequest->id);
            if (! in_array($emergencyRequest->status, ['pending', 'accepted'], true)) {
                throw ValidationException::withMessages(['request' => 'This request can no longer be cancelled online. Contact the assigned shop for help.']);
            }
            $shop = $emergencyRequest->assignedShop;
            $emergencyRequest->update(['status' => 'cancelled', 'completed_at' => now()]);
            if ($shop !== null) {
                DB::table('emergency_dispatch_attempts')->where('emergency_request_id', $emergencyRequest->id)
                    ->where('shop_id', $shop->id)->whereIn('status', ['assigned', 'pending', 'accepted'])
                    ->update(['status' => 'cancelled', 'ended_at' => now(), 'updated_at' => now()]);
                $shop->owner->notifications()->create([
                    'type' => 'emergency_status', 'title' => 'Roadside request cancelled',
                    'message' => "{$request->user()->name} cancelled the roadside assistance request.",
                    'data' => ['emergency_request_id' => $emergencyRequest->id],
                ]);
            }
        });

        return back()->with('status', 'Roadside assistance request cancelled.');
    }
}
