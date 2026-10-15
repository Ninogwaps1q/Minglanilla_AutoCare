@props(['title' => null])
@guest
    <x-layouts.public :title="$title">
        <div class="container py-4 py-lg-5">{{ $slot }}</div>
    </x-layouts.public>
@else
    @php
        $role = auth()->user()->role;
        $navigation = match ($role) {
            'customer' => [['Start here', [['dashboard', 'Home', 'house'], ['map.index', 'Find nearby shops', 'geo-alt'], ['customer.appointments.create', 'Book a service', 'calendar-plus']]], ['Track my service', [['customer.appointments.index', 'Appointments', 'calendar-check'], ['customer.repairs.index', 'Repair progress', 'tools'], ['customer.quotations.index', 'Estimates', 'file-text'], ['customer.invoices.index', 'Payments', 'receipt']]], ['My vehicle', [['customer.vehicles.index', 'Garage', 'car-front'], ['customer.service-history.index', 'Service history', 'clock-history'], ['customer.reminders.index', 'Maintenance planner', 'calendar-event'], ['customer.inspections.index', 'Inspection reports', 'clipboard-check']]], ['Help & account', [['customer.messages.index', 'Messages', 'chat-dots'], ['customer.favorites.index', 'Saved shops', 'heart'], ['customer.reviews.index', 'My reviews', 'star'], ['customer.emergency-requests.index', 'Roadside assistance', 'life-preserver']]]],
            'shop_owner' => [['Today’s work', [['shop.dashboard', 'Home', 'house'], ['shop.appointments.index', 'Appointments', 'calendar-check'], ['shop.repair-jobs.index', 'Repair jobs', 'tools'], ['shop.quotations.index', 'Estimates', 'file-text'], ['shop.invoices.index', 'Bills & payments', 'receipt']]], ['Run the shop', [['shop.profile.edit', 'Shop profile', 'shop'], ['shop.services.index', 'Services & pricing', 'wrench'], ['shop.inventory.index', 'Parts inventory', 'box-seam'], ['shop.technicians.index', 'Team', 'people'], ['shop.reports.index', 'Business reports', 'bar-chart']]], ['Customer care', [['shop.messages.index', 'Messages', 'chat-dots'], ['shop.reviews.index', 'Customer reviews', 'star'], ['shop.emergency-requests.index', 'Roadside requests', 'life-preserver']]]],
            'admin' => [['Platform', [['admin.dashboard', 'Overview', 'house'], ['admin.shops.index', 'Review shops', 'shop'], ['admin.users.index', 'Manage users', 'people'], ['admin.services.index', 'Service catalog', 'wrench'], ['barangays.index', 'Service areas', 'geo-alt']]], ['Monitor & report', [['admin.monitoring.index', 'Live operations', 'activity'], ['admin.reviews.index', 'Moderate reviews', 'star'], ['admin.reports.index', 'Analytics', 'bar-chart'], ['admin.activity-logs.index', 'Audit trail', 'clock-history']]]],
            default => [],
        };
        $roleLabel = match ($role) { 'shop_owner' => 'Shop owner', 'admin' => 'Administration', default => 'Customer' };
        $dashboardRoute = match ($role) { 'shop_owner' => 'shop.dashboard', 'admin' => 'admin.dashboard', default => 'dashboard' };
        $navigationLinks = collect($navigation)->flatMap(fn (array $group): array => $group[1]);
        $currentNavigationItem = $navigationLinks->first(fn (array $link): bool => request()->routeIs($link[0]))
            ?? $navigationLinks->first(function (array $link): bool {
                if (! str_ends_with($link[0], '.index') && ! str_ends_with($link[0], '.edit')) {
                    return false;
                }

                $prefix = substr($link[0], 0, strrpos($link[0], '.'));

                return request()->routeIs($prefix.'.*');
            });
        $currentPageLabel = $currentNavigationItem[1] ?? $title ?? $roleLabel.' workspace';
    @endphp
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <x-theme-init />
        <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name', 'Minglanilla AutoCare Hub') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="portal-shell role-{{ $role }}">
        <a class="skip-link" href="#main-content">Skip to content</a>
        <aside class="offcanvas-lg offcanvas-start portal-sidebar" tabindex="-1" id="portalNavigation" aria-labelledby="navigationTitle">
            <div class="offcanvas-header">
                <h2 class="h5 mb-0" id="navigationTitle">Your workspace</h2>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#portalNavigation" aria-label="Close navigation"></button>
            </div>
            <div class="offcanvas-body">
                <a class="portal-brand" href="{{ route($dashboardRoute) }}">
                    <img class="brand-logo" src="{{ asset('images/Minglanilla AutoCare Hub Logo.png') }}" alt="" width="48" height="48">
                    <span>Minglanilla<strong>AutoCare Hub</strong></span>
                </a>
                <nav aria-label="{{ $roleLabel }} navigation" class="portal-menu">
                    @foreach ($navigation as [$group, $links])
                        @php
                            $collapsible = match ($role) {
                                'customer' => in_array($group, ['My vehicle', 'Help & account'], true),
                                'shop_owner' => in_array($group, ['Run the shop', 'Customer care'], true),
                                'admin' => $group === 'Monitor & report',
                                default => false,
                            };
                            $groupActive = collect($links)->contains(function (array $link): bool {
                                $prefix = substr($link[0], 0, strrpos($link[0], '.'));

                                return request()->routeIs($link[0]) || request()->routeIs($prefix.'.*');
                            });
                        @endphp
                        <div class="portal-nav-group">
                            @if ($collapsible)
                                <details class="portal-nav-details" @if ($groupActive) open @endif>
                                    <summary class="portal-nav-label">{{ $group }} <i class="bi bi-chevron-down" aria-hidden="true"></i></summary>
                            @else
                                <div class="portal-nav-label">{{ $group }}</div>
                            @endif
                            @foreach ($links as [$route, $label, $icon])
                                @php
                                    $routePrefix = substr($route, 0, strrpos($route, '.'));
                                    $hasSiblingRoute = $navigationLinks->contains(
                                        fn (array $link): bool => $link[0] !== $route && str_starts_with($link[0], $routePrefix.'.'),
                                    );
                                    $active = request()->routeIs($route)
                                        || (! $hasSiblingRoute
                                            && (str_ends_with($route, '.index') || str_ends_with($route, '.edit'))
                                            && request()->routeIs($routePrefix.'.*'));
                                @endphp
                                <a class="portal-nav-link {{ $active ? 'active' : '' }}" href="{{ route($route) }}" @if ($active) aria-current="page" @endif><i class="bi bi-{{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span></a>
                            @endforeach
                            @if ($collapsible)
                                </details>
                            @endif
                        </div>
                    @endforeach
                </nav>
                <a class="portal-nav-link portal-return" href="{{ route('home') }}"><i class="bi bi-arrow-up-right-square" aria-hidden="true"></i>Public website</a>
            </div>
        </aside>
        <div class="portal-workspace">
            <header class="portal-topbar">
                <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#portalNavigation" aria-controls="portalNavigation" aria-label="Open navigation"><i class="bi bi-list" aria-hidden="true"></i></button>
                <div class="portal-context"><i class="bi bi-{{ $role === 'customer' ? 'car-front' : ($role === 'shop_owner' ? 'shop' : 'shield-check') }}" aria-hidden="true"></i><span><small>{{ $roleLabel }} portal</small><strong>{{ $currentPageLabel }}</strong></span></div>
                @if ($role === 'customer')
                    <nav class="customer-top-actions d-none d-md-flex" aria-label="Customer quick actions"><a href="{{ route('map.index') }}"><i class="bi bi-geo-alt" aria-hidden="true"></i>Find shop</a><a class="primary" href="{{ route('customer.appointments.create') }}"><i class="bi bi-calendar-plus" aria-hidden="true"></i>Book service</a></nav>
                @endif
                <div class="d-flex align-items-center gap-2 ms-auto">
                    @if (in_array($role, ['customer', 'shop_owner']))
                        <a class="btn btn-light" href="{{ route($role === 'customer' ? 'customer.notifications.index' : 'shop.notifications.index') }}" aria-label="Notifications"><i class="bi bi-bell" aria-hidden="true"></i></a>
                    @endif
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle portal-account" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            @if (auth()->user()->profile_photo)
                                <img class="account-avatar" src="{{ url('media/'.ltrim(auth()->user()->profile_photo, '/')) }}" alt="" width="28" height="28">
                            @else
                                <i class="bi bi-person-circle" aria-hidden="true"></i>
                            @endif
                            <span>{{ auth()->user()->name }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            @if ($role === 'customer')
                                <li><a class="dropdown-item" href="{{ route('customer.profile.edit') }}">My profile</a></li>
                            @elseif ($role === 'shop_owner')
                                <li><a class="dropdown-item" href="{{ route('shop.profile.edit') }}">Shop profile</a></li>
                            @endif
                            <li><a class="dropdown-item" href="{{ route('home') }}">Public website</a></li>
                            <li><x-theme-toggle menu /></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><form method="POST" action="{{ route('logout') }}">@csrf<button class="dropdown-item text-danger" type="submit">Sign out</button></form></li>
                        </ul>
                    </div>
                </div>
            </header>
            <main class="portal-content" id="main-content" tabindex="-1">
                @if (session('status'))
                    <div class="alert alert-success alert-dismissible fade show" role="status">{{ session('status') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss notification"></button></div>
                @endif
                {{ $slot }}
            </main>
            <footer class="portal-footer">Minglanilla AutoCare Hub · {{ $roleLabel }} workspace</footer>
        </div>
        @if ($role === 'customer')
            <nav class="customer-mobile-nav" aria-label="Customer quick navigation">
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}"><i class="bi bi-house" aria-hidden="true"></i><span>Home</span></a>
                <a href="{{ route('map.index') }}" class="{{ request()->routeIs('map.index') ? 'active' : '' }}"><i class="bi bi-geo-alt" aria-hidden="true"></i><span>Shops</span></a>
                <a href="{{ route('customer.appointments.create') }}" class="customer-mobile-book {{ request()->routeIs('customer.appointments.create') ? 'active' : '' }}"><i class="bi bi-plus-lg" aria-hidden="true"></i><span>Book</span></a>
                <a href="{{ route('customer.vehicles.index') }}" class="{{ request()->routeIs('customer.vehicles.*') ? 'active' : '' }}"><i class="bi bi-car-front" aria-hidden="true"></i><span>Garage</span></a>
                <a href="{{ route('customer.repairs.index') }}" class="{{ request()->routeIs('customer.repairs.*') ? 'active' : '' }}"><i class="bi bi-tools" aria-hidden="true"></i><span>Repairs</span></a>
            </nav>
            @unless (request()->routeIs('customer.ai-assistant.*'))
                <x-ai-chat-widget />
            @endunless
        @elseif ($role === 'shop_owner')
            <nav class="customer-mobile-nav shop-mobile-nav" aria-label="Shop quick navigation">
                <a href="{{ route('shop.dashboard') }}" class="{{ request()->routeIs('shop.dashboard') ? 'active' : '' }}"><i class="bi bi-house" aria-hidden="true"></i><span>Home</span></a>
                <a href="{{ route('shop.appointments.index') }}" class="{{ request()->routeIs('shop.appointments.*') ? 'active' : '' }}"><i class="bi bi-calendar-check" aria-hidden="true"></i><span>Bookings</span></a>
                <a href="{{ route('shop.repair-jobs.create') }}" class="customer-mobile-book {{ request()->routeIs('shop.repair-jobs.create') ? 'active' : '' }}"><i class="bi bi-plus-lg" aria-hidden="true"></i><span>New job</span></a>
                <a href="{{ route('shop.repair-jobs.index') }}" class="{{ request()->routeIs('shop.repair-jobs.index') ? 'active' : '' }}"><i class="bi bi-tools" aria-hidden="true"></i><span>Repairs</span></a>
                <a href="{{ route('shop.messages.index') }}" class="{{ request()->routeIs('shop.messages.*') ? 'active' : '' }}"><i class="bi bi-chat-dots" aria-hidden="true"></i><span>Messages</span></a>
            </nav>
        @elseif ($role === 'admin')
            <nav class="customer-mobile-nav admin-mobile-nav" aria-label="Admin quick navigation">
                <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"><i class="bi bi-house" aria-hidden="true"></i><span>Overview</span></a>
                <a href="{{ route('admin.shops.index') }}" class="{{ request()->routeIs('admin.shops.*') ? 'active' : '' }}"><i class="bi bi-shop" aria-hidden="true"></i><span>Shops</span></a>
                <a href="{{ route('admin.monitoring.index') }}" class="customer-mobile-book {{ request()->routeIs('admin.monitoring.*') ? 'active' : '' }}"><i class="bi bi-activity" aria-hidden="true"></i><span>Monitor</span></a>
                <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}"><i class="bi bi-people" aria-hidden="true"></i><span>Users</span></a>
                <a href="{{ route('admin.reports.index') }}" class="{{ request()->routeIs('admin.reports.*') ? 'active' : '' }}"><i class="bi bi-bar-chart" aria-hidden="true"></i><span>Reports</span></a>
            </nav>
        @endif
        @stack('scripts')
    </body>
    </html>
@endguest
