@props(['menu' => false])

<button class="theme-toggle {{ $menu ? 'theme-toggle-menu dropdown-item' : '' }}" type="button" data-theme-toggle aria-label="Switch color theme" title="Switch color theme">
    <i class="bi bi-moon-stars theme-icon-dark" aria-hidden="true"></i>
    <i class="bi bi-sun theme-icon-light" aria-hidden="true"></i>
    <span class="{{ $menu ? '' : 'visually-hidden' }}" data-theme-label>Switch color theme</span>
</button>
