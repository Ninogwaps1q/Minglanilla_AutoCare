<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMessageRequest;
use App\Models\MessageThread;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessageController extends Controller
{
    public function index(Request $request): View
    {
        return view('customer.messages.index', [
            'threads' => $request->user()->messageThreads()->with(['shop', 'messages' => fn ($query) => $query->latest()->limit(1)])->latest()->get(),
            'shops' => Shop::query()->where('verification_status', 'approved')->where('shop_status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, Shop $shop): View
    {
        abort_unless($shop->verification_status === 'approved' && $shop->shop_status === 'active', 404);
        $thread = MessageThread::query()->firstOrCreate(['customer_id' => $request->user()->id, 'shop_id' => $shop->id]);
        $thread->messages()->where('sender_id', '!=', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return view('customer.messages.show', ['thread' => $thread->load(['shop', 'messages.sender'])]);
    }

    public function store(StoreMessageRequest $request, MessageThread $thread): RedirectResponse
    {
        abort_unless($thread->customer_id === $request->user()->id, 404);
        $message = $thread->messages()->create(['sender_id' => $request->user()->id, 'message' => $request->validated('message')]);
        $thread->shop->owner->notifications()->create([
            'type' => 'message_received',
            'title' => 'New customer message',
            'message' => "{$request->user()->name}: {$message->message}",
            'data' => ['thread_id' => $thread->id],
        ]);

        return back()->with('status', 'Message sent.');
    }
}
