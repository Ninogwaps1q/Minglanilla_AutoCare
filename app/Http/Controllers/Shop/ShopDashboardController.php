<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $shop = $request->user()->managedShop();

        if ($request->user()->role === 'mechanic') {
            return view('shop.mechanic.dashboard', [
                'shop' => $shop,
                'todayAppointments' => $shop?->appointments()->with(['customer:id,name', 'vehicle:id,plate_number'])->whereDate('appointment_date', today(Appointment::TIMEZONE))->orderBy('appointment_time')->limit(5)->get(),
                'pendingCount' => $shop?->appointments()->where('status', 'pending')->count(),
                'upcomingCount' => $shop?->appointments()->whereDate('appointment_date', '>=', today(Appointment::TIMEZONE))->where('status', 'confirmed')->whereNotNull('service_address')->count(),
                'activeRepairs' => $shop?->repairJobs()->whereNotIn('status', ['completed', 'released', 'cancelled'])->count(),
                'completed' => $shop?->repairJobs()->whereIn('status', ['completed', 'released'])->count(),
                'emergencies' => $shop?->emergencyRequests()->whereNotIn('status', ['completed', 'cancelled', 'rejected'])->count(),
                'confirmedReceipts' => $shop ? Payment::query()->where('shop_id', $shop->id)->where('status', 'confirmed')->where('payment_date', '>=', now(Appointment::TIMEZONE)->startOfMonth()->utc())->sum('amount') : 0,
                'reminders' => $request->user()->notifications()->whereNull('read_at')->whereIn('type', ['appointment_reminder', 'quotation_reminder', 'shop_plan_reminder'])->latest()->limit(3)->get(),
            ]);
        }

        if ($shop === null) {
            return view('shop.dashboard', ['shop' => null, 'metrics' => []]);
        }
        if ($request->user()->role === 'shop_staff') {
            return view('shop.staff.dashboard', ['shop' => $shop, 'staffRole' => $request->user()->staff_role]);
        }

        return view('shop.dashboard', [
            'shop' => $shop,
            'metrics' => [
                'Today’s visits' => $shop->appointments()->whereDate('appointment_date', today(Appointment::TIMEZONE))->count(),
                'Waiting approval' => $shop->appointments()->where('status', 'pending')->count(),
                'Active repairs' => $shop->repairJobs()->whereNotIn('status', ['completed', 'released', 'cancelled'])->count(),
                ...($shop->hasPlanAtLeast('premium') ? ['Low-stock parts' => $shop->spareParts()->whereColumn('quantity', '<=', 'minimum_stock')->count()] : []),
            ],
            'todayAppointments' => $shop->appointments()->with(['customer:id,name', 'vehicle:id,plate_number', 'service:id,name', 'serviceCase:id,appointment_id'])->whereDate('appointment_date', today(Appointment::TIMEZONE))->orderBy('appointment_time')->limit(5)->get(),
            'activeRepairs' => $shop->repairJobs()->with(['customer:id,name', 'vehicle:id,plate_number'])->whereNotIn('status', ['completed', 'released', 'cancelled'])->latest()->limit(4)->get(),
            'pendingPaymentCount' => Payment::query()->where('shop_id', $shop->id)->where('status', 'pending')->count(),
            'confirmedAppointmentCount' => $shop->appointments()->where('status', 'confirmed')->whereDoesntHave('repairJob')->count(),
            'approvedRepairCount' => $shop->repairJobs()->where('status', 'approved')->count(),
        ]);
    }
}
