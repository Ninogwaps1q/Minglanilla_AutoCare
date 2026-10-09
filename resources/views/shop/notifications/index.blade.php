<x-layouts.app title="Notifications">
    <div class="page-heading">
        <h1>Notifications</h1>
        <p>Keep up with payments, feedback, and customer messages.</p>
    </div>
    <div class="surface list-group list-group-flush">
        @forelse ($notifications as $notification)
            <article class="list-group-item p-4 {{ $notification->read_at ? '' : 'list-group-item-primary' }}">
                <div class="d-flex flex-wrap justify-content-between gap-2">
                    <h2 class="h5 mb-0">{{ $notification->title }}</h2>
                    <time class="text-secondary small" datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->diffForHumans() }}</time>
                </div>
                <p class="mt-2 mb-2">{{ $notification->message }}</p>
                @unless ($notification->read_at)
                    <form method="POST" action="{{ route('shop.notifications.update', $notification) }}">
                        @csrf @method('PATCH')
                        <button class="btn btn-sm btn-outline-primary" type="submit">Mark as read</button>
                    </form>
                @endunless
            </article>
        @empty
            <div class="list-group-item p-4 text-secondary">No notifications yet. Updates will appear here as work progresses.</div>
        @endforelse
    </div>
    <div class="mt-4">{{ $notifications->links() }}</div>
</x-layouts.app>
