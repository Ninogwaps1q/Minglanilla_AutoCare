<x-layouts.app title="Messages">
    <div class="page-heading message-page-heading">
        <h1>Messages</h1>
        <p>Talk with service shops and find their replies in one place.</p>
        <span class="notification-summary">{{ $unreadMessageCount }} unread {{ \Illuminate\Support\Str::plural('message', $unreadMessageCount) }}</span>
    </div>

    <div class="message-center message-center-index">
        <section class="message-directory" aria-labelledby="customer-conversations-heading">
            <header class="message-directory-header">
                <h2 id="customer-conversations-heading">Conversations</h2>
                <span>{{ $threads->count() }} chats</span>
            </header>
            <x-message-contact-list :threads="$threads" context="customer" />
        </section>

        <section class="message-discovery" aria-labelledby="start-conversation-heading">
            <div class="message-discovery-heading">
                <span class="message-discovery-icon"><i class="bi bi-chat-dots" aria-hidden="true"></i></span>
                <h2 id="start-conversation-heading">Start a conversation</h2>
                <p>Choose a shop to ask about services, availability, or your vehicle.</p>
            </div>
            <div class="message-shop-list">
                @forelse ($shops as $shop)
                    <a class="message-shop-link" href="{{ route('customer.messages.show', $shop) }}">
                        <x-uploaded-image class="message-avatar" :path="$shop->logo" :alt="$shop->name.' logo'" fallback-icon="shop" />
                        <span><strong>{{ $shop->name }}</strong><small>Open chat</small></span>
                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </a>
                @empty
                    <p class="message-discovery-empty">No other approved shops to message right now. Your existing chats are on the left.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.app>
