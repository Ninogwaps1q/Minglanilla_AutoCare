@props(['prefix' => 'language'])

<form method="POST" action="{{ route('language.update') }}" class="d-flex flex-wrap align-items-center gap-2">@csrf
    <label class="visually-hidden" for="{{ $prefix }}">{{ __('ui.Language') }}</label>
    <select class="form-select w-auto" id="{{ $prefix }}" name="locale">
        @foreach (\App\Http\Middleware\SetLocale::LANGUAGES as $locale => $label)
            <option value="{{ $locale }}" @selected(app()->getLocale() === $locale)>{{ $label }}</option>
        @endforeach
    </select>
    <button class="btn btn-outline-primary">{{ __('ui.Change language') }}</button>
</form>
