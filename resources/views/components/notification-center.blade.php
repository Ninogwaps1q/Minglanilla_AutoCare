@props(['notifications', 'unreadCount', 'readCount', 'filter', 'indexRoute', 'bulkRoute', 'readRoute', 'openRoute', 'description'])

<div class="notification-center">
    <div class="page-heading notification-page-heading">
        <div><h1>{{ __('ui.' . 'Notifications') }}</h1><p>{{ $description }}</p></div>
        <span class="notification-summary" data-count-pattern="{{ __('ui.:count unread', ['count' => '{count}']) }}">{{ __('ui.:count unread', ['count' => $unreadCount]) }}</span>
    </div>

    <div class="notification-toolbar" aria-label="Notification controls">
        <nav class="notification-filters" aria-label="Filter notifications">
            <a href="{{ route($indexRoute) }}" class="notification-filter {{ $filter === 'all' ? 'is-active' : '' }}" @if ($filter === 'all') aria-current="page" @endif>{{ __('ui.' . 'All') }}</a>
            <a href="{{ route($indexRoute, ['filter' => 'unread']) }}" class="notification-filter {{ $filter === 'unread' ? 'is-active' : '' }}" @if ($filter === 'unread') aria-current="page" @endif>{{ __('ui.' . 'Unread') }} <span class="notification-filter-count">{{ $unreadCount }}</span></a>
        </nav>
        <div class="notification-bulk-actions">
            <form method="POST" action="{{ route($bulkRoute) }}">
                @csrf @method('PATCH')
                <input type="hidden" name="state" value="read">
                <button class="btn btn-sm btn-primary" type="submit" @disabled($unreadCount === 0)>{{ __('ui.' . 'Mark all as read') }}</button>
            </form>
            <form method="POST" action="{{ route($bulkRoute) }}">
                @csrf @method('PATCH')
                <input type="hidden" name="state" value="unread">
                <button class="btn btn-sm btn-outline-primary" type="submit" @disabled($readCount === 0)>{{ __('ui.' . 'Mark all as unread') }}</button>
            </form>
        </div>
    </div>

    <div class="notification-list" aria-label="Notifications">
        @forelse ($notifications as $notification)
            <article class="notification-item {{ $notification->read_at ? 'is-read' : 'is-unread' }}">
                <span class="notification-indicator" aria-hidden="true"></span>
                <div class="notification-item-content">
                    <div class="notification-item-heading">
                        <h2>{{ $notification->localizedTitle() }}</h2>
                        <time datetime="{{ $notification->created_at->toIso8601String() }}">{{ $notification->created_at->diffForHumans() }}</time>
                    </div>
                    <p>{{ $notification->localizedMessage() }}</p>
                    <div class="notification-item-footer">
                        <span class="notification-state">{{ $notification->read_at ? __('ui.Read') : __('ui.Unread') }}</span>
                        <form method="POST" action="{{ route($openRoute, $notification) }}">
                            @csrf
                            <button class="notification-read-action" type="submit">{{ __('ui.' . 'Open update') }}</button>
                        </form>
                        @unless ($notification->read_at)
                            <form method="POST" action="{{ route($readRoute, $notification) }}">
                                @csrf @method('PATCH')
                                <button class="notification-read-action" type="submit">{{ __('ui.' . 'Mark as read') }}</button>
                            </form>
                        @endunless
                    </div>
                </div>
            </article>
        @empty
            <div class="notification-empty">
                <h2>{{ $filter === 'unread' ? __('ui.All caught up') : __('ui.No notifications yet') }}</h2>
                <p>{{ $filter === 'unread' ? __('ui.You have no unread updates. Check All to see earlier notifications.') : __('ui.Updates about your activity will appear here.') }}</p>
                @if ($filter === 'unread')<a class="btn btn-sm btn-outline-primary" href="{{ route($indexRoute) }}">{{ __('ui.' . 'View all notifications') }}</a>@endif
            </div>
        @endforelse
    </div>
    <div class="mt-4">{{ $notifications->links() }}</div>
</div>
