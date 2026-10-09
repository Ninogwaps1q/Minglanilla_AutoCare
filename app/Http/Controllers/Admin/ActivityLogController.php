<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.activity-logs.index', [
            'activityLogs' => ActivityLog::query()->with('user:id,name,email')->latest()->paginate(),
        ]);
    }
}
