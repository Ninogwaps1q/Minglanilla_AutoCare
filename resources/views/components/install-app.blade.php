@props(['menu' => false])

<div data-install-app hidden>
    <button type="button" class="{{ $menu ? 'dropdown-item' : 'btn btn-outline-light align-self-start' }}" data-install-button hidden>Install app</button>
    <p class="{{ $menu ? 'small text-secondary px-3 py-2 mb-0' : 'small mb-0' }}" data-install-ios hidden>On iPhone or iPad, tap Share, then Add to Home Screen.</p>
</div>
