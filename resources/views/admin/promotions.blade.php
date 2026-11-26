<x-layouts.app :title="__('extensions.promo.title')">
    <div class="page-heading"><h1>{{ __('extensions.promo.title') }}</h1></div>
    <x-form-errors />
    <x-promotion-list :promotions="$promotions" route-name="admin.promotions.disable" :show-shop="true" />
</x-layouts.app>
