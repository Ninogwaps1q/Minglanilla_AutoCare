@props(['threads', 'context', 'activeThreadId' => null])

<div class="message-contact-list">
    @forelse ($threads as $thread)
        @php
            $isCustomer = $context === 'customer';
            $counterpart = $isCustomer ? $thread->shop : $thread->customer;
            $name = $counterpart?->name ?? 'Unavailable account';
            $avatarPath = $isCustomer ? $counterpart?->logo : $counterpart?->profile_photo;
            $lastMessage = $thread->messages->first();
            $destination = $isCustomer ? route('customer.messages.show', $thread->shop) : route('shop.messages.show', $thread);
        @endphp
        <a class="message-contact {{ $activeThreadId === $thread->id ? 'is-active' : '' }}" href="{{ $destination }}" @if ($activeThreadId === $thread->id) aria-current="page" @endif>
            <x-uploaded-image class="message-avatar" :path="$avatarPath" :alt="$isCustomer ? $name.' logo' : $name.' profile photo'" :fallback-icon="$isCustomer ? 'shop' : 'person'" />
            <span class="message-contact-content">
                <span class="message-contact-top">
                    <strong class="message-contact-name">{{ $name }}</strong>
                    @if ($lastMessage?->created_at)
                        <time datetime="{{ $lastMessage->created_at->toIso8601String() }}">{{ $lastMessage->created_at->format('M j') }}</time>
                    @endif
                </span>
                <span class="message-contact-bottom">
                    <span class="message-thread-preview">{{ $lastMessage ? ($lastMessage->sender_id === auth()->id() ? 'You: ' : '').\Illuminate\Support\Str::limit($lastMessage->message, 84) : 'Start a conversation' }}</span>
                    @if ($thread->unread_messages_count > 0)
                        <span class="message-contact-unread">{{ $thread->unread_messages_count }} unread</span>
                    @endif
                </span>
            </span>
        </a>
    @empty
        <p class="message-contact-empty">No conversations yet. Open a shop to start one.</p>
    @endforelse
</div>
