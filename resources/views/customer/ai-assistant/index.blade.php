<x-layouts.app title="Ask AutoCare AI">
    <div class="page-heading ai-page-heading">
        <div>
            <h1>Ask AutoCare AI</h1>
            <p>Get clear guidance about bookings, repairs, and caring for your vehicle.</p>
        </div>
        <span class="ai-availability"><span aria-hidden="true"></span>Ready to help</span>
    </div>

    <section class="ai-chat" aria-labelledby="ai-conversation-title">
        <header class="ai-chat-header">
            <span class="ai-avatar ai-avatar-assistant"><i class="bi bi-stars" aria-hidden="true"></i></span>
            <div>
                <h2 id="ai-conversation-title">AutoCare Assistant</h2>
                <p>Helpful guidance based on your AutoCare records</p>
            </div>
        </header>

        <div class="ai-message-list" role="log" aria-live="polite" aria-relevant="additions">
            @forelse ($history as $message)
                <article class="ai-message {{ $message['role'] === 'assistant' ? 'ai-message-assistant' : 'ai-message-user' }}">
                    <span class="ai-avatar" aria-hidden="true">
                        <i class="bi bi-{{ $message['role'] === 'assistant' ? 'stars' : 'person' }}"></i>
                    </span>
                    <div class="ai-message-content">
                        <div class="ai-message-meta">
                            <strong>{{ $message['role'] === 'assistant' ? 'AutoCare AI' : 'You' }}</strong>
                        </div>
                        <p>{{ $message['message'] }}</p>
                        @if ($action = $message['action'] ?? null)
                            <a class="ai-command-action" href="{{ $action['url'] }}">
                                <i class="bi bi-{{ $action['icon'] }}" aria-hidden="true"></i>{{ $action['label'] }}<i class="bi bi-arrow-right ms-auto" aria-hidden="true"></i>
                            </a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="ai-empty-state">
                    <span class="ai-avatar ai-avatar-assistant"><i class="bi bi-stars" aria-hidden="true"></i></span>
                    <div>
                        <h3>How can I help with your vehicle today?</h3>
                        <p>Try asking how to book a service, understand a repair status, or prepare for maintenance.</p>
                    </div>
                </div>
            @endforelse
            <article class="ai-message ai-message-assistant ai-message-typing" role="status" aria-label="AutoCare AI is typing" hidden>
                <span class="ai-avatar" aria-hidden="true"><i class="bi bi-stars"></i></span>
                <div class="ai-message-content">
                    <div class="ai-message-meta"><strong>AutoCare AI</strong></div>
                    <span class="ai-typing-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                    <span class="visually-hidden">AutoCare AI is typing</span>
                </div>
            </article>
            <div id="conversation-end" aria-hidden="true"></div>
        </div>

        <form class="ai-composer" method="POST" action="{{ route('customer.ai-assistant.store') }}">
            @csrf
            <div class='ai-suggestions' role='group' aria-label='Suggested questions'>
                <button type='button' data-ai-prompt='Show my latest repair status'>Repair status</button>
                <button type='button' data-ai-prompt='Book a service'>Book service</button>
                <button type='button' data-ai-prompt='Show my latest invoice status'>My bill</button>
                <button type='button' data-ai-prompt='I need a follow-up for my repair'>Follow-up</button>
            </div>
            <x-form-errors />
            <p class="ai-composer-error" role="alert" hidden></p>
            <label class="form-label" for="message">Your question for AutoCare AI</label>
            <div class="ai-composer-control">
                <textarea class="form-control" id="message" name="message" rows="2" maxlength="2000" placeholder="Ask about your booking, repair, or vehicle care…" required>{{ old('message') }}</textarea>
                <button class="btn btn-accent" type="submit" aria-label="Send question">
                    <i class="bi bi-send-fill" aria-hidden="true"></i><span>Send</span>
                </button>
            </div>
            <p class="ai-disclaimer"><i class="bi bi-info-circle" aria-hidden="true"></i>For guidance only. A qualified technician should inspect safety or mechanical concerns.</p>
        </form>
    </section>

</x-layouts.app>
