<x-layouts.app :title="__('extensions.export.title')">
    <div class="page-heading"><h1>{{ __('extensions.export.title') }}</h1></div>
    <x-report-filters :from="$from" :to="$to" index-route="shop.exports.index" />
    <x-report-csv-links :from="$from" :to="$to" route-name="shop.reports.csv" :datasets="['bookings', 'repairs', 'invoices', 'payments']" />
</x-layouts.app>
