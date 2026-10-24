<div class="ai-widget" data-ai-widget data-open="{{ session('ai_widget_open') ? 'true' : 'false' }}">
    <section class="ai-widget-panel" id="ai-widget-panel" aria-label="AutoCare AI chat" hidden>
        <header class="ai-widget-header">
            <span class="ai-floating-icon"><i class="bi bi-stars" aria-hidden="true"></i></span>
            <div>
                <h2>AutoCare AI</h2>
                <p><span aria-hidden="true"></span>Ready to help</p>
            </div>
            <button type="button" class="ai-widget-close" aria-label="Close AutoCare AI chat">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </header>

        <div class="ai-widget-messages" role="log" aria-live="polite">
            @forelse ($history as $message)
                <article class="ai-widget-message {{ $message['role'] === 'assistant' ? 'is-assistant' : 'is-user' }}">
                    <strong>{{ $message['role'] === 'assistant' ? 'AutoCare AI' : 'You' }}</strong>
                    <p>{{ $message['message'] }}</p>
                    @if ($action = $message['action'] ?? null)
                        <a class="ai-command-action" href="{{ $action['url'] }}">
                            <i class="bi bi-{{ $action['icon'] }}" aria-hidden="true"></i>{{ $action['label'] }}<i class="bi bi-arrow-right ms-auto" aria-hidden="true"></i>
                        </a>
                    @endif
                </article>
            @empty
                <div class="ai-widget-empty">
                    <i class="bi bi-chat-heart" aria-hidden="true"></i>
                    <h3>How can I help?</h3>
                    <p>Ask about bookings, repair updates, or caring for your vehicle.</p>
                </div>
            @endforelse
            <article class="ai-widget-message is-assistant ai-widget-typing" role="status" aria-label="AutoCare AI is typing" hidden>
                <strong>AutoCare AI</strong>
                <span class="ai-typing-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                <span class="visually-hidden">AutoCare AI is typing</span>
            </article>
        </div>

        <form class="ai-widget-composer" method="POST" action="{{ route('customer.ai-assistant.store') }}">
            @csrf
            <div class='ai-suggestions' role='group' aria-label='Suggested questions'>
                <button type='button' data-ai-prompt='Show my latest repair status'>Repair status</button>
                <button type='button' data-ai-prompt='Book a service'>Book service</button>
                <button type='button' data-ai-prompt='Show my latest invoice status'>My bill</button>
            </div>
            <input type="hidden" name="widget" value="1">
            <p class="ai-widget-error" role="alert" @unless ($errors->has('message')) hidden @endunless>{{ $errors->first('message') }}</p>
            <div>
                <label class="visually-hidden" for="ai-widget-message">Ask AutoCare AI</label>
                <textarea id="ai-widget-message" name="message" rows="1" maxlength="2000" placeholder="Type a message" required>{{ old('message') }}</textarea>
                <button type="submit" aria-label="Send question"><i class="bi bi-send-fill" aria-hidden="true"></i></button>
            </div>
            <small>Guidance only. Consult a technician for mechanical concerns.</small>
        </form>
    </section>

    <button class="ai-floating-button" type="button" aria-controls="ai-widget-panel" aria-expanded="false" aria-label="Open AutoCare AI assistant">
        <span class="ai-floating-icon"><i class="bi bi-stars" aria-hidden="true"></i></span>
        <span class="ai-floating-copy"><strong>Ask AutoCare AI</strong><small>Need help?</small></span>
    </button>
</div>
