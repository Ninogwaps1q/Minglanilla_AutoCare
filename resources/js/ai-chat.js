function actionLink(action) {
    if (!action?.label || !action?.url) return null;

    let url;
    try {
        url = new URL(action.url, window.location.origin);
    } catch {
        return null;
    }

    if (url.origin !== window.location.origin || !['http:', 'https:'].includes(url.protocol)) return null;

    const link = document.createElement('a');
    link.className = 'ai-command-action';
    link.href = url.href;
    link.textContent = action.label;

    const icon = document.createElement('i');
    icon.className = 'bi bi-arrow-right ms-auto';
    icon.setAttribute('aria-hidden', 'true');
    link.append(icon);
    return link;
}

function chatMessage(role, text, action, compact) {
    const article = document.createElement('article');
    const assistant = role === 'assistant';
    article.className = compact
        ? 'ai-widget-message ' + (assistant ? 'is-assistant' : 'is-user')
        : 'ai-message ' + (assistant ? 'ai-message-assistant' : 'ai-message-user');

    const body = document.createElement('div');
    if (!compact) {
        const avatar = document.createElement('span');
        avatar.className = 'ai-avatar';
        avatar.setAttribute('aria-hidden', 'true');
        const icon = document.createElement('i');
        icon.className = assistant ? 'bi bi-stars' : 'bi bi-person';
        avatar.append(icon);
        article.append(avatar);
        body.className = 'ai-message-content';
    }

    const meta = document.createElement(compact ? 'strong' : 'div');
    if (compact) {
        meta.textContent = assistant ? 'AutoCare AI' : 'You';
    } else {
        meta.className = 'ai-message-meta';
        const name = document.createElement('strong');
        name.textContent = assistant ? 'AutoCare AI' : 'You';
        meta.append(name);
    }
    body.append(meta);

    const paragraph = document.createElement('p');
    paragraph.textContent = text;
    body.append(paragraph);

    const link = actionLink(action);
    if (link) body.append(link);

    article.append(body);
    return article;
}

function mountComposer(root, compact) {
    const form = root.querySelector(compact ? '.ai-widget-composer' : '.ai-composer');
    const messages = root.querySelector(compact ? '.ai-widget-messages' : '.ai-message-list');
    const input = form?.querySelector('textarea');
    const sendButton = form?.querySelector('button[type="submit"]');
    const error = form?.querySelector(compact ? '.ai-widget-error' : '.ai-composer-error');
    const typing = messages?.querySelector(compact ? '.ai-widget-typing' : '.ai-message-typing');
    const empty = messages?.querySelector(compact ? '.ai-widget-empty' : '.ai-empty-state');
    if (!form || !messages || !input || !sendButton || !error || !typing) return;

    let sending = false;
    const scrollToBottom = () => { messages.scrollTop = messages.scrollHeight; };
    const updateComposer = () => {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, compact ? 96 : 160) + 'px';
        sendButton.disabled = sending || input.value.trim().length === 0;
    };

    input.addEventListener('input', () => {
        error.hidden = true;
        updateComposer();
    });
    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            if (!sending && input.value.trim()) form.requestSubmit(sendButton);
        }
    });

    form.querySelectorAll('[data-ai-prompt]').forEach((button) => {
        button.addEventListener('click', () => {
            if (sending) return;
            input.value = button.dataset.aiPrompt || '';
            input.dispatchEvent(new Event('input', { bubbles: true }));
            if (input.value.trim()) form.requestSubmit(sendButton);
        });
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (sending) return;

        const message = input.value.trim();
        if (!message) {
            input.focus();
            return;
        }

        sending = true;
        error.hidden = true;
        input.readOnly = true;
        updateComposer();

        const draft = chatMessage('user', message, null, compact);
        if (empty) empty.hidden = true;
        messages.append(draft);
        typing.hidden = false;
        messages.append(typing);
        scrollToBottom();

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const payload = response.headers.get('content-type')?.includes('application/json')
                ? await response.json()
                : null;

            if (!response.ok) {
                const message = response.status === 429
                    ? 'Too many questions. Please wait a minute and try again.'
                    : response.status === 419 || response.status === 401
                        ? 'Your session expired. Refresh the page and try again.'
                        : payload?.errors?.message?.[0] || (response.status === 503 ? payload?.message : null)
                            || 'Could not get a reply. Please try again.';
                throw new Error(message);
            }
            if (typeof payload?.reply !== 'string') {
                throw new Error('Could not get a reply. Refresh the page and try again.');
            }

            typing.before(chatMessage('assistant', payload.reply, payload.action, compact));
            input.value = '';
            empty?.remove();
            const history = [...messages.querySelectorAll(compact ? '.ai-widget-message:not(.ai-widget-typing)' : '.ai-message:not(.ai-message-typing)')];
            history.slice(0, -12).forEach((item) => item.remove());
        } catch (failure) {
            draft.remove();
            if (empty) empty.hidden = false;
            error.textContent = failure instanceof TypeError
                ? 'Could not send. Check your connection and try again.'
                : failure instanceof SyntaxError
                    ? 'Could not read the reply. Please try again.'
                    : failure instanceof Error
                        ? failure.message
                        : 'Could not send your question. Please try again.';
            error.hidden = false;
        } finally {
            typing.hidden = true;
            input.readOnly = false;
            sending = false;
            updateComposer();
            scrollToBottom();
            if (!compact || !root.querySelector('.ai-widget-panel').hidden) input.focus({ preventScroll: true });
        }
    });

    updateComposer();
    scrollToBottom();
}

document.querySelectorAll('[data-ai-widget]').forEach((widget) => {
    const panel = widget.querySelector('.ai-widget-panel');
    const launcher = widget.querySelector('.ai-floating-button');
    const closeButton = widget.querySelector('.ai-widget-close');
    const input = widget.querySelector('textarea');

    const setOpen = (open) => {
        panel.hidden = !open;
        launcher.setAttribute('aria-expanded', String(open));
        launcher.classList.toggle('is-hidden', open);
        if (open) {
            const messages = widget.querySelector('.ai-widget-messages');
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

    mountComposer(widget, true);
    setOpen(widget.dataset.open === 'true');
});

document.querySelectorAll('.ai-chat').forEach((chat) => mountComposer(chat, false));
