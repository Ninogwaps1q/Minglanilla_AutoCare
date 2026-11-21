<x-layouts.app title="Shop workspace">
    <div class="page-heading"><h1>{{ $shop->name }}</h1><p>Your {{ $staffRole }} workspace. Choose the work you need to handle today.</p></div>
    <section class="surface p-4" aria-labelledby="staff-actions-title">
        <h2 id="staff-actions-title" class="h4">Your work</h2>
        <div class="d-flex flex-wrap gap-2">
            @if ($staffRole !== 'cashier')<a class="btn btn-primary" href="{{ route('shop.today-work.index') }}">Today's work</a>@endif
            @if ($staffRole === 'receptionist')
                <a class="btn btn-outline-primary" href="{{ route('shop.appointments.index') }}">Appointments</a>
                <a class="btn btn-outline-primary" href="{{ route('shop.quotations.index') }}">Estimates</a>
                <a class="btn btn-outline-primary" href="{{ route('shop.service-cases.index') }}">Customer care</a>
            @elseif ($staffRole === 'technician')
                <a class="btn btn-outline-primary" href="{{ route('shop.repair-jobs.index') }}">Repair jobs</a>
                @if ($shop->hasPlanAtLeast('premium'))<a class="btn btn-outline-primary" href="{{ route('shop.inventory.index') }}">Parts inventory</a>@endif
            @elseif ($staffRole === 'cashier')
                <a class="btn btn-outline-primary" href="{{ route('shop.invoices.index') }}">Bills and payments</a>
                @if ($shop->hasPlanAtLeast('premium'))<a class="btn btn-outline-primary" href="{{ route('shop.reports.index') }}">Business reports</a>@endif
            @endif
        </div>
    </section>
</x-layouts.app>
