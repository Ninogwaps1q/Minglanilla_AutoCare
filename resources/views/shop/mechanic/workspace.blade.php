<x-layouts.app :title="__('mechanic.'.$section)">
    @php($settings = $shop->mechanic_settings ?? [])
    <div class="page-heading">
        <h1>{{ __('mechanic.'.$section) }}</h1>
        <p>{{ $shop->name }}</p>
    </div>
    <x-form-errors />

    @if ($section === 'availability')
        <form method="POST" action="{{ route('shop.mechanic.availability') }}" class="surface rounded p-3 p-lg-4 mb-3">
            @csrf @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="online">{{ __('mechanic.availability') }}</label>
                    <select class="form-select" name="online" id="online">
                        <option value="1" @selected(old('online', (int) $shop->mechanicOnline()) == 1)>{{ __('mechanic.online') }}</option>
                        <option value="0" @selected(old('online', (int) $shop->mechanicOnline()) == 0)>{{ __('mechanic.offline') }}</option>
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label" for="buffer_minutes">Minutes between appointments</label><input class="form-control" id="buffer_minutes" name="buffer_minutes" type="number" min="0" max="180" value="{{ old('buffer_minutes', $settings['buffer_minutes'] ?? 0) }}" required></div>
                <div class="col-md-6"><label class="form-label" for="rest_start">Daily break starts</label><input class="form-control" id="rest_start" name="rest_start" type="time" value="{{ old('rest_start', $settings['rest']['start'] ?? '') }}"></div>
                <div class="col-md-6"><label class="form-label" for="rest_end">Daily break ends</label><input class="form-control" id="rest_end" name="rest_end" type="time" value="{{ old('rest_end', $settings['rest']['end'] ?? '') }}"></div>
                <div class="col-12"><button class="btn btn-primary">{{ __('ui.Save changes') }}</button></div>
            </div>
        </form>
        <a class="btn btn-outline-primary" href="{{ route('shop.profile.edit') }}">Working hours, days off and roadside availability</a>
    @elseif ($section === 'coverage')
        <form method="POST" action="{{ route('shop.mechanic.coverage') }}" class="surface rounded p-3 p-lg-4">
            @csrf @method('PUT')
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label" for="radius_km">Travel radius (km)</label><input class="form-control" id="radius_km" name="radius_km" type="number" min="1" max="100" list="coverage-radius" value="{{ old('radius_km', $settings['radius_km'] ?? 15) }}" required><datalist id="coverage-radius"><option value="5"><option value="10"><option value="15"><option value="20"></datalist><div class="form-text">Measured from the approximate service area pin.</div></div>
                <div class="col-md-6"><label class="form-label" for="coverage_level">Restrict this radius to an area</label><select class="form-select" id="coverage_level" name="coverage_level">@foreach (['radius' => 'No area restriction', 'barangay' => 'Selected barangay', 'city' => 'Selected city or municipality', 'province' => 'Selected province'] as $level => $label)<option value="{{ $level }}" @selected(old('coverage_level', $settings['coverage_level'] ?? 'radius') === $level)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-12"><x-address-picker prefix="coverage" barangay-name="coverage_barangay" :selected-barangay="old('coverage_barangay', $settings['coverage_barangay'] ?? null)" :address-fields="false" :required="false" /></div>
                <div class="col-12"><input type="hidden" name="home_service" value="0"><label class="form-check"><input class="form-check-input" type="checkbox" name="home_service" value="1" @checked(old('home_service', $shop->offersHomeService()))> Accept home service requests</label></div>
                @foreach (['inspection_fee' => __('mechanic.inspection_fee'), 'home_fee' => __('mechanic.home_fee'), 'travel_rate' => 'Travel rate per km'] as $field => $label)
                    <div class="col-md-4"><label for="{{ $field }}" class="form-label">{{ $label }} (PHP)</label><input id="{{ $field }}" name="{{ $field }}" class="form-control" type="number" min="0" step="0.01" max="{{ $field === 'travel_rate' ? 10000 : 100000 }}" value="{{ old($field, $settings[$field] ?? 0) }}" required></div>
                @endforeach
                @unless ($shop->mechanicEntitlements()['travel_pricing'])
                    <div class="col-12"><p class="mb-0 text-secondary">Your current plan saves these rates. They apply when a plan with travel pricing is active.</p></div>
                @endunless
                <div class="col-12 d-flex flex-wrap gap-2"><button class="btn btn-primary">Save with selected coverage</button><button class="btn btn-outline-primary" name="keep_existing_areas" value="1">Add selected area to coverage</button><a class="btn btn-outline-primary" href="{{ route('shop.profile.edit') }}">Edit service-area pin</a></div>
            </div>
        </form>
        <section class="surface rounded p-3 mt-3">
            <h2 class="h5">Selected service areas</h2>
            @if (collect($coverageAreas)->every(fn ($areas) => $areas->isEmpty()))
                <p class="small text-secondary mb-0">The travel radius applies. There are no additional area restrictions.</p>
            @else
                <ul class="list-unstyled mb-0">
                    @foreach ($coverageAreas as $level => $areas)
                        @foreach ($areas as $id => $name)
                            <li class="d-flex flex-wrap gap-2 justify-content-between align-items-center py-2 border-bottom">
                                <span class="text-break">{{ $name }} <span class="small text-secondary">{{ ucfirst($level) }}</span></span>
                                <form method="POST" action="{{ route('shop.mechanic.coverage.destroy', [$level, $id]) }}">@csrf @method('DELETE')<button class="btn btn-outline-danger" aria-label="Remove {{ $name }}">Remove</button></form>
                            </li>
                        @endforeach
                    @endforeach
                </ul>
            @endif
        </section>
    @elseif ($section === 'tools')
        <section class="surface rounded p-3 p-lg-4 mb-3">
            <h2 class="h5">Add equipment or a carried part</h2>
            @include('shop.mechanic.tool-form', ['item' => null, 'tool' => []])
        </section>
        @forelse ($shop->mechanic_tools ?? [] as $item => $tool)
            <details class="workspace-disclosure mb-3">
                <summary><strong class="text-break">{{ $tool['name'] }}</strong> · {{ $tool['quantity'] }} · {{ $tool['available'] ? 'Available' : 'Unavailable' }}</summary>
                @include('shop.mechanic.tool-form')
                <form method="POST" action="{{ route('shop.mechanic.tools.destroy', $item) }}" class="mt-2">@csrf @method('DELETE')<button class="btn btn-outline-danger">Remove {{ $tool['name'] }}</button></form>
            </details>
        @empty
            <p class="text-secondary">No equipment recorded yet.</p>
        @endforelse
    @elseif ($section === 'portfolio')
        <form method="POST" action="{{ route('shop.mechanic.profile') }}" class="surface rounded row g-3 p-3 mx-0 mb-3">
            @csrf @method('PUT')
            @include('shop.mechanic.profile-fields', ['profile' => $shop->mechanic_profile])
            <div class="col-12"><button class="btn btn-primary">{{ __('ui.Save changes') }}</button></div>
        </form>
        <form method="POST" action="{{ route('shop.mechanic.portfolio.store') }}" enctype="multipart/form-data" class="surface rounded row g-3 p-3 mx-0 mb-3">
            @csrf
            <h2 class="h5 mb-0">Add a repair project</h2>
            <div class="col-md-6"><label for="title" class="form-label">Project title</label><input id="title" name="title" class="form-control" maxlength="100" required></div>
            <div class="col-md-6"><label for="vehicle" class="form-label">Vehicle type</label><input id="vehicle" name="vehicle" class="form-control" maxlength="100" required></div>
            <div class="col-12"><label for="description" class="form-label">Repair description</label><textarea id="description" name="description" class="form-control" maxlength="1000" rows="3" required></textarea></div>
            @foreach (['before', 'after'] as $phase)
                <div class="col-md-6"><label for="{{ $phase }}" class="form-label">{{ ucfirst($phase) }} photo (optional)</label><input id="{{ $phase }}" name="{{ $phase }}" class="form-control" type="file" accept="image/jpeg,image/png,image/webp"></div>
            @endforeach
            <div class="col-12"><label class="form-check"><input type="checkbox" class="form-check-input" name="permission" value="1" required> I have permission to publish the project and photos. I have removed plate numbers and personal information.</label></div>
            <div class="col-12"><button class="btn btn-primary">Publish project</button></div>
        </form>
        @foreach ($shop->mechanic_portfolio ?? [] as $id => $project)
            <div class="surface rounded p-3 mb-2 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="text-break"><strong>{{ $project['title'] }}</strong><p class="mb-0">{{ $project['description'] }}</p></div>
                <form method="POST" action="{{ route('shop.mechanic.portfolio.destroy', $id) }}">@csrf @method('DELETE')<button class="btn btn-outline-danger">Remove project</button></form>
            </div>
        @endforeach
    @elseif ($section === 'earnings')
        <form method="GET" class="d-flex gap-2 flex-wrap align-items-end mb-3">
            <div><label for="period" class="form-label">Earnings period</label><select id="period" name="period" class="form-select">@foreach (['day', 'week', 'month'] as $choice)<option value="{{ $choice }}" @selected($period === $choice)>{{ __('mechanic.'.$choice) }}</option>@endforeach</select></div>
            <button class="btn btn-primary">Apply</button>
            <a class="btn btn-outline-primary" href="{{ route('shop.mechanic.export', ['period' => $period]) }}">Download CSV</a>
        </form>
        <div class="row g-3 mb-3">
            @foreach ($metrics as $label => $amount)
                <div class="col-12 col-sm-6 col-xl-4"><dl class="surface rounded p-3 h-100 mb-0"><dt>{{ __('mechanic.'.$label) }}</dt><dd class="price mb-0">PHP {{ number_format($amount, 2) }}</dd></dl></div>
            @endforeach
        </div>
        <form method="POST" action="{{ route('shop.mechanic.expenses.store') }}" class="surface rounded row g-3 mx-0 p-3 mb-3">
            @csrf
            <h2 class="h5 mb-0">Record an expense</h2>
            <div class="col-md-3"><label for="spent_on" class="form-label">Date paid</label><input type="date" id="spent_on" name="spent_on" class="form-control" required value="{{ old('spent_on', today(\App\Models\Appointment::TIMEZONE)->toDateString()) }}"></div>
            <div class="col-md-6"><label for="expense-description" class="form-label">Description</label><input id="expense-description" name="description" class="form-control" maxlength="160" required></div>
            <div class="col-md-3"><label for="expense-amount" class="form-label">Amount (PHP)</label><input id="expense-amount" name="amount" class="form-control" type="number" step="0.01" min="0.01" required></div>
            <div class="col-12"><button class="btn btn-primary">Record expense</button></div>
        </form>
        <section class="surface rounded p-3 mb-3">
            <h2 class="h5">Payment history</h2>
            <ul class="list-unstyled mb-0">
                @forelse ($payments as $payment)
                    <li class="d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 border-bottom"><a href="{{ route('shop.invoices.show', $payment->invoice_id) }}">{{ $payment->invoice->invoice_number }}</a><span class="text-secondary">{{ $payment->payment_date->timezone(\App\Models\Appointment::TIMEZONE)->toDateString() }} · {{ ucfirst($payment->status) }}</span><strong>PHP {{ number_format($payment->amount, 2) }}</strong></li>
                @empty
                    <li class="text-secondary">No payments recorded.</li>
                @endforelse
            </ul>
            <div class="mt-3">{{ $payments->links() }}</div>
        </section>
        <section class="surface rounded p-3">
            <h2 class="h5">Recent expenses for this period</h2>
            <ul class="list-unstyled mb-0">
                @forelse ($expenses as $expense)
                    <li class="d-flex flex-wrap align-items-center justify-content-between gap-2 py-2 border-bottom"><span class="text-break">{{ $expense->description }}</span><span>{{ $expense->spent_on }}</span><strong>PHP {{ number_format($expense->amount, 2) }}</strong></li>
                @empty
                    <li class="text-secondary">No expenses recorded for this period.</li>
                @endforelse
            </ul>
        </section>
    @endif
</x-layouts.app>
