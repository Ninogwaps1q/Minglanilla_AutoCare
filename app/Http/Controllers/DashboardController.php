<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\EmergencyRequest;
use App\Models\Payment;
use App\Models\RepairJob;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($request->routeIs('dashboard') && $user->role === 'shop_owner') {
            return redirect()->route('shop.dashboard');
        }

        if ($request->routeIs('dashboard') && $user->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        $viewData = ['user' => $user, 'metrics' => match ($user->role) {
            'admin' => ['Users' => User::query()->count(), 'Shops' => Shop::query()->count(), 'Pending verifications' => Shop::query()->where('verification_status', 'pending')->count(), 'Vehicles' => Vehicle::query()->count()],
            'shop_owner' => ['Registered shops' => $user->shops()->count(), 'Pending verification' => $user->shops()->where('verification_status', 'pending')->count()],
            default => ['My vehicles' => $user->vehicles()->count(), 'Upcoming visits' => $user->appointments()->whereIn('status', ['pending', 'confirmed'])->whereDate('appointment_date', '>=', today())->count(), 'Active repairs' => $user->repairJobs()->whereNotIn('status', ['completed', 'released', 'cancelled'])->count(), 'Saved shops' => $user->favorites()->count()],
        }];

        if ($user->role === 'customer') {
            $viewData += [
                'nextAppointment' => $user->appointments()->with(['shop:id,name', 'service:id,name', 'vehicle:id,plate_number'])->whereIn('status', ['pending', 'confirmed'])->whereDate('appointment_date', '>=', today())->orderBy('appointment_date')->orderBy('appointment_time')->first(),
                'activeRepair' => $user->repairJobs()->with(['shop:id,name', 'vehicle:id,plate_number'])->whereNotIn('status', ['completed', 'released', 'cancelled'])->latest()->first(),
                'primaryVehicle' => $user->vehicles()->latest()->first(),
                'unpaidInvoiceCount' => $user->invoices()->where('status', 'unpaid')->count(),
            ];
        }

        if ($user->role === 'admin') {
            $viewData += [
                'pendingShops' => Shop::query()->with('owner:id,name')->where('verification_status', 'pending')->latest()->limit(5)->get(),
                'todayAppointmentCount' => Appointment::query()->whereDate('appointment_date', today())->count(),
                'activeRepairCount' => RepairJob::query()->whereNotIn('status', ['completed', 'released', 'cancelled'])->count(),
                'pendingPaymentCount' => Payment::query()->where('status', 'pending')->count(),
                'activeEmergencyCount' => EmergencyRequest::query()->whereNotIn('status', ['completed', 'cancelled'])->count(),
            ];
        }

        return view('dashboard', $viewData);
    }
}
