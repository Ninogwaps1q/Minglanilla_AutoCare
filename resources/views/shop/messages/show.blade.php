<x-layouts.app :title="'Message ' . $thread->customer->name">
    <div class="message-center message-center-chat">
        <aside class="message-directory" aria-label="Other customer conversations">
            <header class="message-directory-header">
                <h2>Messages</h2>
                <a href="{{ route('shop.messages.index') }}">All chats</a>
            </header>
            <x-message-contact-list :threads="$threads" context="shop" :active-thread-id="$thread->id" />
        </aside>
        <x-message-conversation :thread="$thread" context="shop" :has-older-messages="$hasOlderMessages" />
    </div>
</x-layouts.app>