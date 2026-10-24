const history = document.querySelector('[data-message-history][data-updates-url]');

if (history) {
    const form = document.querySelector('.message-compose');
    const input = form?.querySelector('textarea[name="message"]');
    const button = form?.querySelector('button[type="submit"]');
    const status = form?.querySelector('[data-message-status]');
    const counterpartName = document.querySelector('#message-conversation-title')?.textContent?.trim() || 'Other person';
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    let lastId = Math.max(0, ...Array.from(history.querySelectorAll('[data-message-id]'), (row) => Number(row.dataset.messageId) || 0));
    let polling = false;
    let sending = false;

    const showStatus = (message, failed = false) => {
        if (!status) return;
        status.hidden = false;
        status.textContent = message;
        status.classList.toggle('text-danger', failed);
        status.classList.toggle('text-success', !failed);
    };

    const renderMessage = (entry) => {
        const id = Number(entry.id);
        if (!Number.isSafeInteger(id) || id < 1 || history.querySelector(`[data-message-id="${id}"]`)) return;
        const nearBottom = history.scrollHeight - history.scrollTop - history.clientHeight < 100;
        history.querySelector('.message-history-empty')?.remove();

        const row = document.createElement('div');
        row.className = `message-row ${entry.mine ? 'is-own' : 'is-incoming'}`;
        row.dataset.messageId = String(id);
        if (!entry.mine) {
            const avatar = document.querySelector('.message-conversation-header .message-avatar')?.cloneNode(true);
            if (avatar) {
                avatar.classList.remove('message-avatar-header');
                avatar.classList.add('message-avatar-small');
                row.append(avatar);
            }
        }

        const stack = document.createElement('div');
        stack.className = 'message-bubble-stack';
        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';
        const paragraph = document.createElement('p');
        paragraph.textContent = entry.body;
        bubble.append(paragraph);
        const byline = document.createElement('div');
        byline.className = 'message-byline';
        const author = document.createElement('span');
        author.textContent = entry.mine ? 'You · Sent' : counterpartName;
        const time = document.createElement('time');
        time.textContent = entry.time;
        byline.append(author, time);
        stack.append(bubble, byline);
        row.append(stack);
        history.append(row);
        lastId = Math.max(lastId, id);
        if (nearBottom || entry.mine) history.scrollTop = history.scrollHeight;
    };

    const poll = async () => {
        if (polling || document.hidden || !token) return;
        polling = true;
        try {
            const response = await fetch(history.dataset.updatesUrl, {
                method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                credentials: 'same-origin', cache: 'no-store', body: JSON.stringify({ after_id: lastId }),
            });
            if (!response.ok) return;
            const data = await response.json();
            data.messages?.forEach(renderMessage);
        } catch {
            // Keep the current conversation visible until the next attempt.
        } finally {
            polling = false;
        }
    };

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (sending || !input?.reportValidity() || !token) return;
        sending = true;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        showStatus('Sending...');
        try {
            const response = await fetch(form.action, {
                method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token },
                credentials: 'same-origin', body: new FormData(form),
            });
            const data = await response.json();
            if (!response.ok || !data.message) {
                showStatus(data.errors?.message?.[0] || 'Could not send the message. Please try again.', true);
                return;
            }
            renderMessage(data.message);
            input.value = '';
            showStatus('Sent');
            input.focus();
        } catch {
            showStatus('Could not send the message. Check your connection and try again.', true);
        } finally {
            sending = false;
            button.disabled = false;
            button.removeAttribute('aria-busy');
        }
    });

    window.setInterval(poll, 12000);
    window.addEventListener('focus', poll);
}
