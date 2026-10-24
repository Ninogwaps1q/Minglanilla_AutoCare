<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Message;
use App\Services\Messaging\MessageStorage;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function counts(Request $request): JsonResponse
    {
        $shopId = $request->user()->shops()->value('id');
        try {
            $messages = $shopId === null ? 0 : Message::query()->whereNull('read_at')->where('sender_id', '!=', $request->user()->id)
                ->whereHas('thread', fn ($query) => $query->where('shop_id', $shopId))->count();
        } catch (QueryException $exception) {
            if (! MessageStorage::isUnavailable($exception)) {
                throw $exception;
            }

            $messages = null;
        }

        return response()->json([
            'notifications' => $request->user()->notifications()->whereNull('read_at')->count(),
            'messages' => $messages,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function index(Request $request): View
    {
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';

        return view('shop.notifications.index', [
            'notifications' => $request->user()->notifications()
                ->when($filter === 'unread', fn ($query) => $query->whereNull('read_at'))
                ->latest()->paginate(15)->withQueryString(),
            'readNotificationCount' => $request->user()->notifications()->whereNotNull('read_at')->count(),
            'filter' => $filter,
        ]);
    }

    public function updateReadState(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'state' => ['required', Rule::in(['read', 'unread'])],
        ]);

        if ($data['state'] === 'read') {
            $request->user()->notifications()->whereNull('read_at')->update(['read_at' => now()]);
        } else {
            $request->user()->notifications()->whereNotNull('read_at')->update(['read_at' => null]);
        }

        return back()->with('status', $data['state'] === 'read'
            ? 'All your notifications are marked as read.'
            : 'All your notifications are marked as unread.');
    }

    public function update(Request $request, Notification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        $notification->update(['read_at' => now()]);

        return back();
    }

    public function open(Request $request, Notification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
        $destination = $notification->destinationFor($request->user());
        $notification->update(['read_at' => now()]);

        return redirect()->to($destination);
    }
}
