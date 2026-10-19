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
        $requests = $request->user()->emergencyRequests()->with(['vehicle', 'assignedShop'])->latest('requested_at')->get();
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
            $emergencyRequest = $request->user()->emergencyRequests()->create([...$attributes, 'status' => 'pending', 'requested_at' => now()]);
            $this->dispatchService->assignNext($emergencyRequest);

            return $emergencyRequest;
        });

        return back()->with('status', $emergencyRequest->assigned_shop_id === null ? 'Your request was recorded. No eligible nearby shop is currently available.' : 'Your request was sent to the nearest eligible shop.');
    }
}
