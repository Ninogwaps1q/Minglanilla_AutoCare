<x-layouts.app title="Messages">
    <div class="page-heading">
        <h1>Messages</h1>
        <p>Ask shops about your vehicle and keep track of their replies.</p>
        <span class="notification-summary">{{ $unreadMessageCount }} unread {{ \Illuminate\Support\Str::plural('message', $unreadMessageCount) }}</span>
    </div>

    <section class="surface mb-4" aria-label="Conversations">
        <div class="list-group list-group-flush">
            @forelse ($threads as $thread)
                <a class="list-group-item list-group-item-action py-3" href="{{ route('customer.messages.show', $thread->shop) }}">
                    <span class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <strong>{{ $thread->shop->name }}</strong>
                        @if ($thread->unread_messages_count > 0)
                            <span class="badge text-bg-primary">{{ $thread->unread_messages_count }} unread</span>
                        @endif
                    </span>
                    <span class="d-block text-secondary mt-1 message-thread-preview">{{ $thread->messages->first()?->message ?? 'Start a conversation' }}</span>
                </a>
            @empty
                <div class="p-4 text-secondary">No conversations yet. Choose a shop below to send your first message.</div>
            @endforelse
        </div>
    </section>

    <section class="surface p-4" aria-labelledby="start-conversation-heading">
        <h2 class="h5" id="start-conversation-heading">Start a conversation</h2>
        <div class="d-flex flex-wrap gap-2">
            @forelse ($shops as $shop)
                <a class="btn btn-outline-primary" href="{{ route('customer.messages.show', $shop) }}">Message {{ $shop->name }}</a>
            @empty
                <span class="text-secondary">No approved shops are available right now.</span>
            @endforelse
        </div>
    </section>
</x-layouts.app>
