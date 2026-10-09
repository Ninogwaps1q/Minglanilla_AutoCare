<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateShopStatusRequest;
use App\Models\ActivityLog;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ShopVerificationController extends Controller
{
    public function index(): View
    {
        return view('admin.shops.index', ['shops' => Shop::query()->with(['owner', 'barangay'])->latest()->paginate(20)]);
    }

    public function approve(Shop $shop, Request $request): RedirectResponse
    {
        $this->changeVerification($shop, $request, 'approved', 'active');

        return back()->with('status', 'Shop approval saved. Manage any subsequent suspension through its operational status.');
    }

    public function reject(Shop $shop, Request $request): RedirectResponse
    {
        if ($shop->verification_status === 'approved') {
            throw ValidationException::withMessages(['shop' => 'Suspend an approved shop through its operational status instead.']);
        }
        $this->changeVerification($shop, $request, 'rejected', 'inactive');

        return back()->with('status', 'Shop registration rejected.');
    }

    public function updateStatus(UpdateShopStatusRequest $request, Shop $shop): RedirectResponse
    {
        DB::transaction(function () use ($request, $shop): void {
            $shop = Shop::query()->lockForUpdate()->findOrFail($shop->id);
            if ($shop->verification_status !== 'approved') {
                throw ValidationException::withMessages(['shop_status' => 'Approve the registration before changing its operational status.']);
            }
            $status = $request->validated('shop_status');
            if ($shop->shop_status === $status) {
                return;
            }
            $shop->update(['shop_status' => $status]);
            $this->recordDecision($shop, $request, "Shop is now {$status}.", $request->validated('reason'));
        });

        return back()->with('status', 'Shop operational status updated.');
    }

    private function changeVerification(Shop $shop, Request $request, string $verification, string $status): void
    {
        DB::transaction(function () use ($shop, $request, $verification, $status): void {
            $shop = Shop::query()->lockForUpdate()->findOrFail($shop->id);
            if ($shop->verification_status === $verification) {
                return;
            }
            $shop->update(['verification_status' => $verification, 'shop_status' => $status]);
            $this->recordDecision($shop, $request, "Shop registration {$verification}.");
        });
    }

    private function recordDecision(Shop $shop, Request $request, string $message, ?string $reason = null): void
    {
        $description = $message.($reason ? ' Reason: '.$reason : '');
        ActivityLog::query()->create([
            'user_id' => $request->user()->id, 'action' => 'updated_shop_status', 'module' => 'shop_verification',
            'description' => "Shop #{$shop->id}: {$description}", 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent(),
        ]);
        $shop->owner->notifications()->create([
            'type' => 'shop_status', 'title' => 'Shop status updated', 'message' => $description,
            'data' => ['shop_id' => $shop->id, 'verification_status' => $shop->verification_status, 'shop_status' => $shop->shop_status],
        ]);
    }
}
