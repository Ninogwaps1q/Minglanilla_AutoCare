<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\RepairJob;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RepairJobController extends Controller
{
    public function index(Request $request): View
    {
        return view('customer.repairs.index', [
            'repairJobs' => RepairJob::query()
                ->whereBelongsTo($request->user(), 'customer')
                ->with(['shop', 'vehicle'])
                ->latest()->paginate()->withQueryString(),
        ]);
    }
}
