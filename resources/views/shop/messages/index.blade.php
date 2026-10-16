<x-layouts.app title="Customer messages">
    <div class="page-heading">
        <h1>Customer messages</h1>
        <p>Read questions from drivers and reply from each conversation.</p>
        <span class="notification-summary">{{ $unreadMessageCount }} unread {{ \Illuminate\Support\Str::plural('message', $unreadMessageCount) }}</span>
    </div>

    <section class="surface" aria-label="Customer conversations">
        <div class="list-group list-group-flush">
            @forelse ($threads as $thread)
                <a class="list-group-item list-group-item-action py-3" href="{{ route('shop.messages.show', $thread) }}">
                    <span class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <strong>{{ $thread->customer->name }}</strong>
                        @if ($thread->unread_messages_count > 0)
                            <span class="badge text-bg-primary">{{ $thread->unread_messages_count }} unread</span>
                        @endif
                    </span>
                    <span class="d-block text-secondary mt-1 message-thread-preview">{{ $thread->messages->first()?->message ?? 'No messages yet' }}</span>
                </a>
            @empty
                <div class="p-4 text-secondary">No customer conversations yet. New messages will appear here.</div>
            @endforelse
        </div>
    </section>
</x-layouts.app>
