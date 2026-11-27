<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\EmergencyRequest;
use App\Models\Shop;
use App\Services\EmergencyDispatchService;
use Illuminate\Http\JsonResponse;
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
        $shop = $this->shop($request);

        return view('shop.emergency-requests.index', ['shop' => $shop, 'canShareLocation' => $shop->isIndependentMechanic() && $shop->mechanicEntitlements()['live_tracking'], 'requests' => $shop->emergencyRequests()
            ->when($request->integer('focus') > 0, fn ($query) => $query->whereKey($request->integer('focus')))
            ->with(['customer', 'vehicle', 'barangay.municipality.province.region'])->latest('requested_at')->get()]);
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
            if ($attributes['status'] === 'cancelled') {
                $this->dispatchService->reassign($emergencyRequest, 'declined');

                return;
            }
            $timestamps = match ($attributes['status']) {
                'accepted' => ['responded_at' => $emergencyRequest->responded_at ?? now()],
                'completed' => ['completed_at' => now(), 'responder_location' => null],
                default => [],
            };
            $emergencyRequest->update([...$attributes, ...$timestamps]);
            if ($attributes['status'] === 'accepted') {
                DB::table('emergency_dispatch_attempts')
                    ->where('emergency_request_id', $emergencyRequest->id)
                    ->where('shop_id', $shop->id)
                    ->update(['status' => 'accepted', 'updated_at' => now()]);
            }
            $emergencyRequest->customer->notifications()->create(['type' => 'emergency_status', 'title' => 'Roadside assistance update', 'message' => 'Your emergency request is now '.str_replace('_', ' ', $attributes['status']).'.', 'data' => ['emergency_request_id' => $emergencyRequest->id]]);
        });

        return back()->with('status', 'Emergency request updated.');
    }

    public function location(Request $request, EmergencyRequest $emergencyRequest): JsonResponse
    {
        $shop = $this->shop($request);
        abort_unless($shop->isIndependentMechanic() && $shop->owner_id === $request->user()->id, 403);
        $attributes = $request->validate([
            'action' => ['required', 'in:share,stop,arrive'],
            'consent' => ['accepted_if:action,share'],
            'latitude' => ['required_if:action,share', 'numeric', 'between:-90,90'],
            'longitude' => ['required_if:action,share', 'numeric', 'between:-180,180'],
        ]);
        DB::transaction(function () use ($shop, $emergencyRequest, $attributes): void {
            $emergencyRequest = $shop->emergencyRequests()->lockForUpdate()->findOrFail($emergencyRequest->id);
            if ($attributes['action'] === 'stop') {
                $emergencyRequest->update(['responder_location' => null]);

                return;
            }
            abort_unless($emergencyRequest->status === 'en_route', 409);
            if ($attributes['action'] === 'arrive') {
                if ($emergencyRequest->arrived_at === null) {
                    $emergencyRequest->update(['arrived_at' => now(), 'responder_location' => null]);
                    $emergencyRequest->customer->notifications()->create([
                        'type' => 'emergency_status', 'title' => 'Roadside responder arrived',
                        'message' => 'The assigned responder has marked their arrival.',
                        'data' => ['emergency_request_id' => $emergencyRequest->id],
                    ]);
                }

                return;
            }
            abort_if($emergencyRequest->arrived_at !== null, 409);
            abort_unless($shop->mechanicEntitlements()['live_tracking'], 403);
            $emergencyRequest->update(['responder_location' => [
                'latitude' => round((float) $attributes['latitude'], 5),
                'longitude' => round((float) $attributes['longitude'], 5),
                'time' => now()->timestamp,
            ]]);
        });

        return response()->json(['success' => true])->header('Cache-Control', 'private, no-store');
    }

    private function shop(Request $request): Shop
    {
        return $request->user()->managedShopOrFail();
    }
}
