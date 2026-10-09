<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(): View
    {
        return view('customer.notifications.index', ['notifications' => auth()->user()->notifications()->latest()->paginate()]);
    }

    public function markAsRead(\App\Models\Notification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === auth()->id(), 404);
        $notification->update(['read_at' => now()]);

        return back();
    }
}
