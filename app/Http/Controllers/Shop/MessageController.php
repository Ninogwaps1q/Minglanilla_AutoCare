<?php

namespace App\Http\Controllers\Shop;

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
        return view('shop.messages.index', [
            'threads' => $this->shop($request)->messageThreads()
                ->with(['customer', 'messages' => fn ($query) => $query->latest()->limit(1)])
                ->withCount(['messages as unread_messages_count' => fn ($query) => $query->whereNull('read_at')->where('sender_id', '!=', $request->user()->id)])
                ->latest()
                ->get(),
        ]);
    }

    public function show(Request $request, MessageThread $thread): View
    {
        abort_unless($thread->shop_id === $this->shop($request)->id, 404);
        $thread->messages()->where('sender_id', '!=', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return view('shop.messages.show', ['thread' => $thread->load(['customer', 'messages.sender'])]);
    }

    public function store(StoreMessageRequest $request, MessageThread $thread): RedirectResponse
    {
        abort_unless($thread->shop_id === $this->shop($request)->id, 404);
        $message = $thread->messages()->create(['sender_id' => $request->user()->id, 'message' => $request->validated('message')]);
        $thread->customer->notifications()->create([
            'type' => 'message_received',
            'title' => 'New shop message',
            'message' => "{$thread->shop->name}: {$message->message}",
            'data' => ['thread_id' => $thread->id],
        ]);

        return back()->with('status', 'Message sent.');
    }

    private function shop(Request $request): Shop
    {
        return $request->user()->shops()->firstOrFail();
    }
}
