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
            <input type="hidden" name="widget" value="1">
            @error('message')<p class="ai-widget-error" role="alert">{{ $message }}</p>@enderror
            <div>
                <label class="visually-hidden" for="ai-widget-message">Ask AutoCare AI</label>
                <textarea id="ai-widget-message" name="message" rows="1" maxlength="2000" placeholder="Type a message" aria-required="true">{{ old('message') }}</textarea>
                <button type="submit" aria-label="Send question" disabled><i class="bi bi-send-fill" aria-hidden="true"></i></button>
            </div>
            <small>Guidance only. Consult a technician for mechanical concerns.</small>
        </form>
    </section>

    <button class="ai-floating-button" type="button" aria-controls="ai-widget-panel" aria-expanded="false" aria-label="Open AutoCare AI assistant">
        <span class="ai-floating-icon"><i class="bi bi-stars" aria-hidden="true"></i></span>
        <span class="ai-floating-copy"><strong>Ask AutoCare AI</strong><small>Need help?</small></span>
    </button>
</div>

@pushOnce('scripts')
    <script>
        document.querySelectorAll('[data-ai-widget]').forEach((widget) => {
            const panel = widget.querySelector('.ai-widget-panel');
            const launcher = widget.querySelector('.ai-floating-button');
            const closeButton = widget.querySelector('.ai-widget-close');
            const messages = widget.querySelector('.ai-widget-messages');
            const input = widget.querySelector('textarea');
            const form = widget.querySelector('.ai-widget-composer');
            const sendButton = form.querySelector('button[type="submit"]');
            const typingIndicator = widget.querySelector('.ai-widget-typing');

            const updateComposer = () => {
                input.style.height = 'auto';
                input.style.height = `${Math.min(input.scrollHeight, 96)}px`;
                sendButton.disabled = input.value.trim() === '';
            };

            const setOpen = (open) => {
                panel.hidden = !open;
                launcher.setAttribute('aria-expanded', String(open));
                launcher.classList.toggle('is-hidden', open);
                if (open) {
                    messages.scrollTop = messages.scrollHeight;
                    input.focus({ preventScroll: true });
                }
            };

            launcher.addEventListener('click', () => setOpen(true));
            closeButton.addEventListener('click', () => {
                setOpen(false);
                launcher.focus();
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !panel.hidden) {
                    setOpen(false);
                    launcher.focus();
                }
            });

            input.addEventListener('input', updateComposer);
            input.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    if (!sendButton.disabled) {
                        form.requestSubmit();
                    }
                }
            });

            form.addEventListener('submit', (event) => {
                const message = input.value.trim();
                if (!message) {
                    event.preventDefault();
                    input.focus();
                    return;
                }

                sendButton.disabled = true;
                input.readOnly = true;
                typingIndicator.hidden = false;
                messages.append(typingIndicator);
                messages.scrollTo({ top: messages.scrollHeight, behavior: 'smooth' });
            });

            updateComposer();
            setOpen(widget.dataset.open === 'true');
        });
    </script>
@endPushOnce
