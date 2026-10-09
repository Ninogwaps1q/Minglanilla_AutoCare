<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\EmergencyRequest;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmergencyRequestController extends Controller
{
    public function index(Request $request): View
    {
        return view('shop.emergency-requests.index', ['requests' => $this->shop($request)->emergencyRequests()->with(['customer', 'vehicle'])->latest('requested_at')->get()]);
    }

    public function update(Request $request, EmergencyRequest $emergencyRequest): RedirectResponse
    {
        $shop = $this->shop($request);
        abort_unless($emergencyRequest->assigned_shop_id === $shop->id, 404);
        abort_unless($shop->verification_status === 'approved' && $shop->shop_status === 'active', 403);
        $attributes = $request->validate(['status' => ['required', 'in:accepted,en_route,completed,cancelled']]);
        DB::transaction(function () use ($emergencyRequest, $shop, $attributes): void {
            $emergencyRequest = $shop->emergencyRequests()->lockForUpdate()->findOrFail($emergencyRequest->id);
            abort_if(in_array($emergencyRequest->status, ['completed', 'cancelled'], true), 409);
            if (! in_array($attributes['status'], $emergencyRequest->allowedStatuses(), true)) {
                throw ValidationException::withMessages(['status' => 'This status change is not allowed for the current roadside assistance stage.']);
            }
            $timestamps = match ($attributes['status']) {
                'accepted' => ['responded_at' => $emergencyRequest->responded_at ?? now()],
                'completed' => ['completed_at' => now()],
                default => [],
            };
            $emergencyRequest->update([...$attributes, ...$timestamps]);
            $emergencyRequest->customer->notifications()->create(['type' => 'emergency_status', 'title' => 'Roadside assistance update', 'message' => 'Your emergency request is now '.str_replace('_', ' ', $attributes['status']).'.', 'data' => ['emergency_request_id' => $emergencyRequest->id]]);
        });

        return back()->with('status', 'Emergency request updated.');
    }

    private function shop(Request $request): Shop
    {
        return $request->user()->shops()->firstOrFail();
    }
}
