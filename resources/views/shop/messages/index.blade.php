<x-layouts.app title="Customer messages">
    <div class="page-heading message-page-heading">
        <h1>Customer messages</h1>
        <p>Reply to drivers from your shop account.</p>
        <span class="notification-summary">{{ $unreadMessageCount }} unread {{ \Illuminate\Support\Str::plural('message', $unreadMessageCount) }}</span>
    </div>

    <div class="message-center message-center-index">
        <section class="message-directory" aria-labelledby="shop-conversations-heading">
            <header class="message-directory-header">
                <h2 id="shop-conversations-heading">Conversations</h2>
                <span>{{ $threads->count() }} chats</span>
            </header>
            <x-message-contact-list :threads="$threads" context="shop" />
        </section>

        <section class="message-discovery message-discovery-intro" aria-labelledby="shop-message-intro">
            <span class="message-discovery-icon"><i class="bi bi-chat-dots" aria-hidden="true"></i></span>
            <h2 id="shop-message-intro">Stay close to your customers</h2>
            <p>Select a conversation to read the customer's question and send a reply. New conversations appear when a
                customer messages your shop.</p>
        </section>
    </div>
</x-layouts.app>
