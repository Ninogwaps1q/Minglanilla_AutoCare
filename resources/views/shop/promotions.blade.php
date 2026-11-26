<x-layouts.app :title="__('extensions.promo.title')">
    <div class="page-heading"><h1>{{ __('extensions.promo.title') }}</h1></div>
    <x-form-errors />
    <form method="POST" action="{{ route('shop.promotions.store') }}" class="surface p-4 mb-4 row g-3 mx-0">
        @csrf
        <h2 class="h5">{{ __('extensions.promo.create') }}</h2>
        <div class="col-md-6">
            <label class="form-label" for="promo-name">{{ __('extensions.promo.name') }}</label><input name="name" id="promo-name" class="form-control" value="{{ old('name') }}" maxlength="100" required>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="promo-code">{{ __('extensions.promo.booking_code') }}</label><input name="code" id="promo-code" class="form-control" value="{{ old('code') }}" maxlength="32" aria-describedby="promo-code-help">
            <p class="form-text" id="promo-code-help">{{ __('extensions.promo.code_hint') }}</p>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="promo-kind">{{ __('extensions.promo.kind') }}</label>
            <select class="form-select" id="promo-kind" name="kind">
                <option value="percent">{{ __('extensions.promo.percent') }}</option>
                <option value="fixed" @selected(old('kind') === 'fixed')>{{ __('extensions.promo.fixed') }}</option>
            </select>
        </div>
        @foreach (['value', 'minimum', 'usage_limit', 'customer_limit'] as $field)
            <div class="col-md-6">
                <label class="form-label" for="promo-{{ $field }}">{{ __('extensions.promo.'.$field) }}</label>
                <input class="form-control" id="promo-{{ $field }}" type="number" min="{{ match ($field) { 'value' => '0.01', 'minimum' => '0', default => '1' } }}" step="{{ in_array($field, ['value', 'minimum']) ? '0.01' : '1' }}" name="{{ $field }}" value="{{ old($field) }}" @required($field === 'value')>
            </div>
        @endforeach
        @foreach (['starts_on', 'ends_on'] as $field)
            <div class="col-md-6">
                <label class="form-label" for="promo-{{ $field }}">{{ __('extensions.promo.'.$field) }}</label>
                <input class="form-control" id="promo-{{ $field }}" type="date" name="{{ $field }}" value="{{ old($field) }}" required>
            </div>
        @endforeach
        @if ($services->isNotEmpty())
            <fieldset class="col-12">
                <legend class="h6">{{ __('extensions.promo.services') }}</legend>
                <p class="form-text">{{ __('extensions.promo.services_hint') }}</p>
                @foreach ($services as $offer)
                    <label class="form-check d-block">
                        <input class="form-check-input" type="checkbox" name="service_ids[]" value="{{ $offer->service_id }}" @checked(in_array($offer->service_id, old('service_ids', [])))>
                        {{ $offer->service?->name }}
                    </label>
                @endforeach
            </fieldset>
        @endif
        <div><button class="btn btn-primary">{{ __('extensions.promo.create') }}</button></div>
    </form>
    <x-promotion-list :promotions="$promotions" route-name="shop.promotions.disable" />
</x-layouts.app>
