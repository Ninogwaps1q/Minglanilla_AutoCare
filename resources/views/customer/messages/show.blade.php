<x-layouts.app :title="'Message '.$thread->shop->name">
    <div class="page-heading">
        <h1>{{ $thread->shop->name }}</h1>
        <p>Conversation with this shop.</p>
        <a href="{{ route('customer.messages.index') }}">Back to messages</a>
    </div>
    <div class="surface p-4">
        <div class="vstack gap-3 mb-4" aria-label="Conversation history">
            @forelse ($thread->messages as $message)
                <div class="{{ $message->sender_id === auth()->id() ? 'text-end' : '' }}">
                    <div class="d-inline-block p-3 rounded text-break {{ $message->sender_id === auth()->id() ? 'bg-primary text-white' : 'bg-light' }}">
                        <strong class="small d-block">{{ $message->sender->name }}</strong>{{ $message->message }}
                    </div>
                </div>
            @empty
                <p class="text-secondary mb-0">No messages yet. Send your question to start the conversation.</p>
            @endforelse
        </div>
        <form method="POST" action="{{ route('customer.messages.store', $thread) }}">
            @csrf
            <label class="form-label" for="message">Your message to {{ $thread->shop->name }}</label>
            <div class="d-flex flex-column flex-sm-row gap-2">
                <input id="message" class="form-control @error('message') is-invalid @enderror" name="message" value="{{ old('message') }}" maxlength="2000" placeholder="Ask about a service or repair" required>
                <button class="btn btn-primary flex-shrink-0" type="submit">Send message</button>
            </div>
            @error('message')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </form>
    </div>
</x-layouts.app>
