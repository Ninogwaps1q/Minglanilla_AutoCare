<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMessageRequest;
use App\Models\MessageThread;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MessageController extends Controller
{
    public function index(Request $request): View
    {
        $threads = $this->threads($request);

        return view('customer.messages.index', [
            'threads' => $threads,
            'shops' => Shop::query()->where('verification_status', 'approved')->where('shop_status', 'active')
                ->whereNotIn('id', $threads->pluck('shop_id'))->orderBy('name')->get(['id', 'name', 'logo']),
        ]);
    }

    public function show(Request $request, Shop $shop): View
    {
        abort_unless($shop->verification_status === 'approved' && $shop->shop_status === 'active', 404);
        $thread = MessageThread::query()->firstOrCreate(['customer_id' => $request->user()->id, 'shop_id' => $shop->id]);
        $messages = $thread->messages()->latest('id')->limit(50)->get()->reverse()->values();
        $thread->messages()->whereKey($messages->pluck('id'))->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')->update(['read_at' => now()]);
        $thread->setRelation('messages', $messages)->load('shop:id,name,logo');

        return view('customer.messages.show', [
            'thread' => $thread,
            'threads' => $this->threads($request),
            'hasOlderMessages' => $messages->isNotEmpty() && $thread->messages()->where('id', '<', $messages->first()->id)->exists(),
        ]);
    }

    public function store(StoreMessageRequest $request, MessageThread $thread): RedirectResponse|JsonResponse
    {
        abort_unless($thread->customer_id === $request->user()->id, 404);
        $message = $thread->messages()->create(['sender_id' => $request->user()->id, 'message' => $request->validated('message')]);
        $thread->shop->owner->notifications()->create([
            'type' => 'message_received',
            'title' => 'New customer message',
            'message' => "{$request->user()->name}: {$message->message}",
            'data' => ['thread_id' => $thread->id],
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => [
                'id' => $message->id, 'body' => $message->message,
                'time' => $message->created_at->format('M j, g:i A'), 'mine' => true,
            ]], 201);
        }

        return back();
    }

    public function updates(Request $request, MessageThread $thread): JsonResponse
    {
        abort_unless($thread->customer_id === $request->user()->id, 404);
        $data = $request->validate([
            'after_id' => ['required_without:before_id', 'integer', 'min:0'],
            'before_id' => ['required_without:after_id', 'integer', 'min:1'],
        ]);
        $older = isset($data['before_id']);
        $messages = $older
            ? $thread->messages()->where('id', '<', $data['before_id'])->latest('id')->limit(50)->get()->reverse()->values()
            : $thread->messages()->where('id', '>', $data['after_id'])->oldest('id')->limit(50)->get();
        $incomingIds = $messages->where('sender_id', '!=', $request->user()->id)->pluck('id');
        if ($incomingIds->isNotEmpty()) {
            $thread->messages()->whereKey($incomingIds)->whereNull('read_at')->update(['read_at' => now()]);
        }

        return response()->json(['messages' => $messages->map(fn ($message): array => [
            'id' => $message->id, 'body' => $message->message,
            'time' => $message->created_at->format('M j, g:i A'), 'mine' => $message->sender_id === $request->user()->id,
        ])->all(), 'has_more' => $older && $messages->isNotEmpty() && $thread->messages()->where('id', '<', $messages->first()->id)->exists()])
            ->header('Cache-Control', 'private, no-store');
    }

    /** @return Collection<int, MessageThread> */
    private function threads(Request $request): Collection
    {
        return $request->user()->messageThreads()
            ->with(['shop:id,name,logo', 'messages' => fn ($query) => $query->latest()->limit(1)])
            ->withCount(['messages as unread_messages_count' => fn ($query) => $query->whereNull('read_at')->where('sender_id', '!=', $request->user()->id)])
            ->latest()->get();
    }
}
