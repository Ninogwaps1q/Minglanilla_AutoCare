@props(['thread', 'context', 'hasOlderMessages' => false])

@php
    $isCustomer = $context === 'customer';
    $counterpart = $isCustomer ? $thread->shop : $thread->customer;
    $name = $counterpart?->name ?? 'Unavailable account';
    $avatarPath = $isCustomer ? $counterpart?->logo : $counterpart?->profile_photo;
    $backRoute = $isCustomer ? route('customer.messages.index') : route('shop.messages.index');
    $storeRoute = $isCustomer ? route('customer.messages.store', $thread) : route('shop.messages.store', $thread);
@endphp

<section class="message-conversation {{ $hasOlderMessages ? 'has-older-messages' : '' }}" aria-labelledby="message-conversation-title">
    <header class="message-conversation-header">
        <a class="message-back" href="{{ $backRoute }}" aria-label="Back to all messages"><i class="bi bi-arrow-left" aria-hidden="true"></i></a>
        <x-uploaded-image class="message-avatar message-avatar-header" :path="$avatarPath" :alt="$isCustomer ? $name.' logo' : $name.' profile photo'" :fallback-icon="$isCustomer ? 'shop' : 'person'" />
        <div class="message-conversation-identity">
            <h1 id="message-conversation-title">{{ $name }}</h1>
            <p>{{ $isCustomer ? 'Service shop' : 'Customer' }}</p>
        </div>
    </header>

    @if ($hasOlderMessages)<button type="button" class="btn btn-sm btn-outline-secondary message-load-older" data-message-load-older>Load earlier messages</button>@endif
    <div class="message-history" role="log" aria-label="Conversation with {{ $name }}" data-message-history data-updates-url="{{ route($isCustomer ? 'customer.messages.updates' : 'shop.messages.updates', $thread) }}">
        @forelse ($thread->messages as $entry)
            @php $isMine = $entry->sender_id === auth()->id(); @endphp
            <div class="message-row {{ $isMine ? 'is-own' : 'is-incoming' }}" data-message-id="{{ $entry->id }}">
                @unless ($isMine)
                    <x-uploaded-image class="message-avatar message-avatar-small" :path="$avatarPath" :alt="$isCustomer ? $name.' logo' : $name.' profile photo'" :fallback-icon="$isCustomer ? 'shop' : 'person'" />
                @endunless
                <div class="message-bubble-stack">
                    <div class="message-bubble"><p>{{ $entry->message }}</p></div>
                    <div class="message-byline">
                        <span>{{ $isMine ? 'You · Sent' : $name }}</span>
                        <time datetime="{{ $entry->created_at?->toIso8601String() }}">{{ $entry->created_at?->format('M j, g:i A') }}</time>
                    </div>
                </div>
            </div>
        @empty
            <div class="message-history-empty">
                <i class="bi bi-chat-dots" aria-hidden="true"></i>
                <p>No messages yet. Say hello to {{ $name }} below.</p>
            </div>
        @endforelse
    </div>

    <form class="message-compose" method="POST" action="{{ $storeRoute }}">
        @csrf
        <div class="message-compose-inner">
            <label for="message">Your message<span class="visually-hidden"> to {{ $name }}</span></label>
            <div class="message-compose-control">
                <textarea id="message" class="form-control @error('message') is-invalid @enderror" name="message" rows="1" maxlength="2000" placeholder="Write a message..." required>{{ old('message') }}</textarea>
                <button class="btn btn-primary" type="submit"><i class="bi bi-send-fill" aria-hidden="true"></i><span>Send</span></button>
            </div>
            @error('message')<p class="text-danger small mt-2 mb-0">{{ $message }}</p>@enderror
            <p class="message-compose-status small mb-0" role="status" aria-live="polite" data-message-status hidden></p>
        </div>
    </form>
</section>

@pushOnce('scripts')
    <script>
        document.querySelectorAll('[data-message-history]').forEach((history) => {
            history.scrollTop = history.scrollHeight;
        });
    </script>
@endPushOnce
