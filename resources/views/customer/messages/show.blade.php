<x-layouts.app :title="'Message '.$thread->shop->name">
    <div class="message-center message-center-chat">
        <aside class="message-directory" aria-label="Other shop conversations">
            <header class="message-directory-header">
                <h2>Messages</h2>
                <a href="{{ route('customer.messages.index') }}">All chats</a>
            </header>
            <x-message-contact-list :threads="$threads" context="customer" :active-thread-id="$thread->id" />
        </aside>
        <x-message-conversation :thread="$thread" context="customer" />
    </div>
</x-layouts.app>
