import './bootstrap';
import 'bootstrap';

const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');

function applyTheme(theme, persist = false) {
    const normalizedTheme = theme === 'dark' ? 'dark' : 'light';
    document.documentElement.dataset.theme = normalizedTheme;
    document.documentElement.dataset.bsTheme = normalizedTheme;

    if (persist) {
        try {
            localStorage.setItem('autocare-theme', normalizedTheme);
        } catch {
            // The selected theme still works for this page when storage is unavailable.
        }
    }

    const nextTheme = normalizedTheme === 'dark' ? 'light' : 'dark';
    const label = `Switch to ${nextTheme} mode`;
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.setAttribute('aria-label', label);
        button.setAttribute('title', label);
        const text = button.querySelector('[data-theme-label]');
        if (text) {
            text.textContent = label;
        }
    });

    window.dispatchEvent(new CustomEvent('autocare:theme-change', { detail: { theme: normalizedTheme } }));
}

applyTheme(document.documentElement.dataset.theme || (systemTheme.matches ? 'dark' : 'light'));

document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        applyTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark', true);
    });
});

systemTheme.addEventListener('change', (event) => {
    try {
        if (localStorage.getItem('autocare-theme')) {
            return;
        }
    } catch {
        // Follow the system preference when storage is unavailable.
    }

    applyTheme(event.matches ? 'dark' : 'light');
});

document.querySelectorAll('input[type="password"]').forEach((input) => {
    const wrapper = document.createElement('div');
    wrapper.className = 'auth-password';
    input.before(wrapper);
    wrapper.append(input);
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'password-toggle';
    button.textContent = 'Show';
    button.setAttribute('aria-label', 'Show password');
    button.setAttribute('aria-pressed', 'false');
    button.addEventListener('click', () => {
        const visible = input.type === 'password';
        input.type = visible ? 'text' : 'password';
        button.textContent = visible ? 'Hide' : 'Show';
        button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
        button.setAttribute('aria-pressed', String(visible));
    });
    wrapper.append(button);
});

document.querySelectorAll('.table-responsive').forEach((table) => {
    table.tabIndex = 0;
    table.setAttribute('role', 'region');
    table.setAttribute('aria-label', 'Scrollable table');
});

document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', (event) => {
        const method = form.querySelector('input[name="_method"]')?.value?.toUpperCase();
        const destructive = method === 'DELETE' || event.submitter?.dataset.confirm;
        const confirmation = event.submitter?.dataset.confirm || 'Remove this item? This action cannot be undone.';

        if (destructive && !window.confirm(confirmation)) {
            event.preventDefault();
            return;
        }

        if (event.defaultPrevented || !form.checkValidity()) {
            return;
        }

        const submitter = event.submitter;
        if (submitter instanceof HTMLButtonElement && !submitter.dataset.noLoading) {
            submitter.disabled = true;
            submitter.setAttribute('aria-busy', 'true');
            submitter.dataset.originalLabel = submitter.innerHTML;
            submitter.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>Saving…</span>';
        }
    });
});

document.querySelectorAll('input[type="file"]').forEach((input) => {
    input.addEventListener('change', () => {
        let status = input.parentElement?.querySelector('.file-selection');
        if (!status) {
            status = document.createElement('small');
            status.className = 'file-selection form-text';
            status.setAttribute('aria-live', 'polite');
            input.insertAdjacentElement('afterend', status);
        }
        status.textContent = input.files?.length ? `Selected: ${input.files[0].name}` : 'No file selected';
    });
});
