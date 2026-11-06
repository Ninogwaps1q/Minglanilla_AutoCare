const endpoint = document.body?.dataset.activityCountsUrl;

if (endpoint) {
    let loading = false;

    const setBadge = (badge, count) => {
        if (!badge) return;
        const visibleCount = count > 99 ? '99+' : String(count);
        if (badge.firstChild?.nodeType === Node.TEXT_NODE) {
            badge.firstChild.textContent = visibleCount;
        } else {
            badge.prepend(document.createTextNode(visibleCount));
        }
        badge.classList.toggle('is-empty', count === 0);
    };

    const refresh = async () => {
        if (loading || document.hidden) return;
        loading = true;
        try {
            const response = await fetch(endpoint, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
            if (!response.ok) return;
            const counts = await response.json();
            const notifications = Number(counts.notifications);
            if (Number.isSafeInteger(notifications) && notifications >= 0) {
                document.querySelectorAll('.portal-notifications-count')
                    .forEach((badge) => setBadge(badge, notifications));
                const topLink = document.querySelector('.portal-notifications-link');
                topLink?.setAttribute('aria-label', `Notifications, ${notifications} unread`);
                const summary = document.querySelector('.notification-center .notification-summary');
                if (summary) {
                    const previousCount = Number.parseInt(summary.textContent, 10) || 0;
                    summary.textContent = `${notifications} unread`;
                    if (notifications > previousCount && !document.querySelector('.notification-new-update')) {
                        const notice = document.createElement('div');
                        notice.className = 'notification-new-update';
                        notice.setAttribute('role', 'status');
                        notice.append('New updates are available. ');
                        const link = document.createElement('a');
                        link.href = window.location.href;
                        link.textContent = 'Refresh the list';
                        notice.append(link);
                        document.querySelector('.notification-toolbar')?.after(notice);
                    }
                }
            }

            const messages = Number(counts.messages);
            if (counts.messages !== null && Number.isSafeInteger(messages) && messages >= 0) {
                document.querySelectorAll('.portal-messages-count')
                    .forEach((badge) => setBadge(badge, messages));
                document.querySelector('.portal-messages-link')
                    ?.setAttribute('aria-label', `Messages, ${messages} unread`);
            }
        } catch {
            // Keep the last visible count while the connection is unavailable.
        } finally {
            loading = false;
        }
    };

    window.setInterval(refresh, 30000);
    window.addEventListener('focus', refresh);
}
