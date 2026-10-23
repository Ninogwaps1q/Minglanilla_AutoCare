<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\ServiceCase;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TodayWorkController extends Controller
{
    public function __invoke(Request $request): View
    {
        $shop = $request->user()->shops()->firstOrFail();

        return view('shop.today-work.index', [
            'appointments' => $shop->appointments()->whereDate('appointment_date', today())
                ->with(['customer', 'vehicle', 'service', 'repairJob'])
                ->orderBy('appointment_time')->get(),
            'repairs' => $shop->repairJobs()->whereNotIn('status', ['completed', 'released', 'cancelled'])
                ->with(['customer', 'vehicle', 'quotation'])->latest()->get(),
            'cases' => ServiceCase::query()->where('shop_id', $shop->id)
                ->whereNotIn('status', ['resolved', 'declined', 'dismissed'])
                ->with(['customer', 'repairJob'])->latest()->get(),
        ]);
    }
}
