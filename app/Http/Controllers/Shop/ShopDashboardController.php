<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $shop = $request->user()->shops()->first();

        if ($shop === null) {
            return view('shop.dashboard', ['shop' => null, 'metrics' => []]);
        }

        return view('shop.dashboard', [
            'shop' => $shop,
            'metrics' => [
                'Today’s visits' => $shop->appointments()->whereDate('appointment_date', today())->count(),
                'Waiting approval' => $shop->appointments()->where('status', 'pending')->count(),
                'Active repairs' => $shop->repairJobs()->whereNotIn('status', ['completed', 'released', 'cancelled'])->count(),
                'Low-stock parts' => $shop->spareParts()->whereColumn('quantity', '<=', 'minimum_stock')->count(),
            ],
            'todayAppointments' => $shop->appointments()->with(['customer:id,name', 'vehicle:id,plate_number', 'service:id,name', 'serviceCase:id,appointment_id'])->whereDate('appointment_date', today())->orderBy('appointment_time')->limit(5)->get(),
            'activeRepairs' => $shop->repairJobs()->with(['customer:id,name', 'vehicle:id,plate_number'])->whereNotIn('status', ['completed', 'released', 'cancelled'])->latest()->limit(4)->get(),
            'pendingPaymentCount' => Payment::query()->where('shop_id', $shop->id)->where('status', 'pending')->count(),
            'confirmedAppointmentCount' => $shop->appointments()->where('status', 'confirmed')->whereDoesntHave('repairJob')->count(),
            'approvedRepairCount' => $shop->repairJobs()->where('status', 'approved')->count(),
        ]);
    }
}
