@props(['title' => null])
@guest
    <x-layouts.public :title="$title">
        <div class="container py-4 py-lg-5">{{ $slot }}</div>
    </x-layouts.public>
@else
    @php
        $role = auth()->user()->role;
        $ownerShop = in_array($role, ['shop_owner', 'mechanic', 'shop_staff'], true) ? auth()->user()->managedShop() : null;
        $navigation = match ($role) {
            'customer' => [['Start here', [['dashboard', 'Home', 'house'], ['shops.index', 'Find providers', 'geo-alt'], ['customer.emergency-requests.index', 'Roadside assistance', 'life-preserver'], ['customer.appointments.create', 'Book a service', 'calendar-plus']]], ['Track my service', [['customer.services.index', 'My services', 'clipboard2-pulse'], ['customer.appointments.index', 'Appointments', 'calendar-check'], ['customer.repairs.index', 'Repair progress', 'tools'], ['customer.quotations.index', 'Estimates', 'file-text'], ['customer.invoices.index', 'Payments', 'receipt'], ['customer.service-cases.index', 'Follow-ups & complaints', 'chat-left-text']]], ['My vehicle', [['customer.vehicles.index', 'My Vehicle', 'car-front'], ['customer.service-history.index', 'Service history', 'clock-history'], ['customer.reminders.index', 'Maintenance planner', 'calendar-event'], ['customer.inspections.index', 'Inspection reports', 'clipboard-check']]], ['Help & account', [['customer.favorites.index', 'Saved shops', 'heart'], ['customer.reviews.index', 'My reviews', 'star']]]],
            'shop_owner' => [['Today’s work', [['shop.dashboard', 'Home', 'house'], ['shop.today-work.index', 'Today’s work', 'list-task'], ['shop.appointments.index', 'Appointments', 'calendar-check'], ['shop.emergency-requests.index', 'Roadside requests', 'life-preserver'], ['shop.repair-jobs.index', 'Repair jobs', 'tools'], ['shop.quotations.index', 'Estimates', 'file-text'], ['shop.invoices.index', 'Bills & payments', 'receipt']]], ['Run the shop', [['shop.profile.edit', 'Shop profile', 'shop'], ['shop.services.index', 'Services & pricing', 'wrench'], ['shop.inventory.index', 'Parts inventory', 'box-seam'], ['shop.technicians.index', 'Team', 'people'], ['shop.reports.index', 'Business reports', 'bar-chart'], ['shop.pricing', 'Plans & pricing', 'tags']]], ['Customer care', [['shop.service-cases.index', 'Follow-ups & complaints', 'chat-left-text'], ['shop.reviews.index', 'Customer reviews', 'star']]]],
            'mechanic' => [['My work', [['shop.dashboard', 'Home', 'house'], ['shop.today-work.index', 'Today’s work', 'list-task'], ['shop.appointments.index', 'Bookings', 'calendar-check'], ['shop.emergency-requests.index', 'Roadside requests', 'life-preserver'], ['shop.repair-jobs.index', 'Repair jobs', 'tools'], ['shop.quotations.index', 'Estimates', 'file-text'], ['shop.invoices.index', 'Bills & payments', 'receipt']]], ['My services', [['shop.profile.edit', 'Mechanic profile', 'person-badge'], ['shop.services.index', 'Services & pricing', 'wrench'], ['shop.inventory.index', 'Parts inventory', 'box-seam'], ['shop.reports.index', 'Business reports', 'bar-chart'], ['shop.pricing', 'Plans & pricing', 'tags']]], ['Customer care', [['shop.messages.index', 'Messages', 'chat-dots'], ['shop.service-cases.index', 'Follow-ups & complaints', 'chat-left-text'], ['shop.reviews.index', 'Customer reviews', 'star']]]],
            'shop_staff' => [['Shop workspace', match (auth()->user()->staff_role) {
                'receptionist' => [['shop.dashboard', 'Home', 'house'], ['shop.today-work.index', 'Today’s work', 'list-task'], ['shop.appointments.index', 'Appointments', 'calendar-check'], ['shop.quotations.index', 'Estimates', 'file-text'], ['shop.service-cases.index', 'Customer care', 'chat-left-text'], ['shop.emergency-requests.index', 'Roadside requests', 'life-preserver']],
                'technician' => [['shop.dashboard', 'Home', 'house'], ['shop.today-work.index', 'Today’s work', 'list-task'], ['shop.repair-jobs.index', 'Repair jobs', 'tools'], ['shop.inventory.index', 'Parts inventory', 'box-seam']],
                'cashier' => [['shop.dashboard', 'Home', 'house'], ['shop.invoices.index', 'Bills & payments', 'receipt'], ['shop.reports.index', 'Business reports', 'bar-chart']],
                default => [['shop.dashboard', 'Home', 'house']],
            }]],
            'admin' => [['Platform', [['admin.dashboard', 'Overview', 'house'], ['admin.shops.index', 'Review shops', 'shop'], ['admin.shop-plans.index', 'Shop plans', 'tags'], ['admin.subscription-billing.index', 'Subscription billing', 'receipt'], ['admin.users.index', 'Manage users', 'people'], ['admin.services.index', 'Service catalog', 'wrench'], ['admin.vehicle-catalog.index', 'Vehicle catalog', 'car-front'], ['barangays.index', 'Service areas', 'geo-alt']]], ['Monitor & report', [['admin.monitoring.index', 'Operations', 'activity'], ['admin.service-cases.index', 'Complaint resolution', 'chat-square-text'], ['admin.reviews.index', 'Moderate reviews', 'star'], ['admin.privacy-requests.index', 'Privacy requests', 'file-lock'], ['admin.reports.index', 'Analytics', 'bar-chart'], ['admin.activity-logs.index', 'Audit trail', 'clock-history']]]],
            default => [],
        };
        if ($role === 'shop_owner') {
            $navigation[1][1][] = ['shop.staff.index', 'Staff accounts', 'person-badge'];
        }
        if (in_array($role, ['shop_owner', 'mechanic', 'shop_staff'], true) && ! $ownerShop?->hasPlanAtLeast('premium')) {
            foreach ($navigation as &$group) {
                $group[1] = array_values(array_filter($group[1], fn (array $link): bool => ! in_array($link[0], ['shop.inventory.index', 'shop.technicians.index', 'shop.reports.index'], true)));
            }
            unset($group);
        }
        $roleLabel = match ($role) { 'shop_owner' => 'Shop owner', 'mechanic' => 'Independent mechanic', 'shop_staff' => ucfirst(auth()->user()->staff_role ?? 'Shop staff'), 'admin' => 'Administration', default => 'Customer'};
        $dashboardRoute = match ($role) { 'shop_owner', 'mechanic', 'shop_staff' => 'shop.dashboard', 'admin' => 'admin.dashboard', default => 'dashboard'};
        $quickLinks = match ($role) {
            'customer' => [
                ['dashboard', 'Home', 'house', ['dashboard']],
                ['shops.index', 'Find', 'geo-alt', ['shops.*']],
                ['customer.appointments.create', 'Book', 'plus-lg', ['customer.appointments.create']],
                ['customer.vehicles.index', 'My Vehicle', 'car-front', ['customer.vehicles.*']],
                ['customer.services.index', 'My services', 'tools', ['customer.services.*', 'customer.appointments.index', 'customer.repairs.*', 'customer.quotations.*', 'customer.invoices.*', 'customer.service-cases.*']],
            ],
            'shop_owner', 'mechanic' => [
                ['shop.dashboard', 'Home', 'house', ['shop.dashboard']],
                ['shop.appointments.index', 'Bookings', 'calendar-check', ['shop.appointments.*']],
                $ownerShop?->hasPlanAtLeast('basic')
                    ? ['shop.repair-jobs.create', 'New job', 'plus-lg', ['shop.repair-jobs.create']]
                    : ['shop.pricing', 'Plans', 'tags', ['shop.pricing']],
                ['shop.repair-jobs.index', 'Repairs', 'tools', ['shop.repair-jobs.index']],
                ['shop.today-work.index', 'Today', 'list-task', ['shop.today-work.*']],
            ],
            'admin' => [
                ['admin.dashboard', 'Overview', 'house', ['admin.dashboard']],
                ['admin.shops.index', 'Shops', 'shop', ['admin.shops.*']],
                ['admin.monitoring.index', 'Monitor', 'activity', ['admin.monitoring.*']],
                ['admin.users.index', 'Users', 'people', ['admin.users.*']],
                ['admin.reports.index', 'Reports', 'bar-chart', ['admin.reports.*']],
            ],
            default => [],
        };
        $navigationLinks = collect($navigation)->flatMap(fn(array $group): array => $group[1]);
        $currentNavigationItem = $navigationLinks->first(fn(array $link): bool => request()->routeIs($link[0]))
            ?? $navigationLinks->first(function (array $link): bool {
                if (!str_ends_with($link[0], '.index') && !str_ends_with($link[0], '.edit')) {
                    return false;
                }

                $prefix = substr($link[0], 0, strrpos($link[0], '.'));

                return request()->routeIs($prefix . '.*');
            });
        $currentPageLabel = (isset($currentNavigationItem[1]) ? __('ui.'.$currentNavigationItem[1]) : $title) ?? $roleLabel . ' workspace';
    @endphp
    <!doctype html>
    <html lang="{{ app()->getLocale() }}">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="location-consent-url" content="{{ route('privacy.location') }}">
        <x-theme-init />
        <x-pwa-head />
        <title>{{ $title ? $title . ' · ' : '' }}{{ config('app.name', 'Minglanilla AutoCare Hub') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body class="portal-shell autocare-ui role-{{ $role }}" @if (in_array($role, ['customer', 'shop_owner', 'mechanic'], true))
        data-activity-counts-url="{{ route($role === 'customer' ? 'customer.notifications.counts' : 'shop.notifications.counts') }}"
    @endif>
        <a class="skip-link" href="#main-content">{{ __('ui.' . 'Skip to content') }}</a>
        <aside class="offcanvas-lg offcanvas-start portal-sidebar" tabindex="-1" id="portalNavigation"
            aria-labelledby="navigationTitle">
            <div class="offcanvas-header">
                <h2 class="h5 mb-0" id="navigationTitle">{{ __('ui.' . 'Your workspace') }}</h2>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"
                    data-bs-target="#portalNavigation" aria-label="Close navigation"></button>
            </div>
            <div class="offcanvas-body">
                <a class="portal-brand" href="{{ route($dashboardRoute) }}">
                    <img class="brand-logo" src="{{ asset('images/Minglanilla AutoCare Hub Logo.png') }}" alt="" width="48"
                        height="48">
                    <span>Minglanilla<strong>AutoCare Hub</strong></span>
                </a>
                <nav aria-label="{{ $roleLabel }} navigation" class="portal-menu">
                    @foreach ($navigation as [$group, $links])
                        @php
                            $collapsible = match ($role) {
                                'customer' => in_array($group, ['My vehicle', 'Help & account'], true),
                                'shop_owner', 'mechanic' => in_array($group, ['Run the shop', 'My services', 'Customer care'], true),
                                'admin' => $group === 'Monitor & report',
                                default => false,
                            };
                            $groupActive = collect($links)->contains(function (array $link): bool {
                                $prefix = substr($link[0], 0, strrpos($link[0], '.'));

                                return request()->routeIs($link[0]) || request()->routeIs($prefix . '.*');
                            });
                        @endphp
                        <div class="portal-nav-group">
                            @if ($collapsible)
                                <details class="portal-nav-details" @if ($groupActive) open @endif>
                                    <summary class="portal-nav-label">{{ __('ui.'.$group) }} <i class="bi bi-chevron-down"
                                            aria-hidden="true"></i></summary>
                            @else
                                    <div class="portal-nav-label">{{ __('ui.'.$group) }}</div>
                                @endif
                                @foreach ($links as [$route, $label, $icon])
                                    @php
                                        $routePrefix = substr($route, 0, strrpos($route, '.'));
                                        $hasSiblingRoute = $navigationLinks->contains(
                                            fn(array $link): bool => $link[0] !== $route && str_starts_with($link[0], $routePrefix . '.'),
                                        );
                                        $active = request()->routeIs($route)
                                            || (!$hasSiblingRoute
                                                && (str_ends_with($route, '.index') || str_ends_with($route, '.edit'))
                                                && request()->routeIs($routePrefix . '.*'));
                                    @endphp
                                    <a class="portal-nav-link {{ $active ? 'active' : '' }}" href="{{ route($route) }}" @if ($active) aria-current="page" @endif><i class="bi bi-{{ $icon }}"
                                            aria-hidden="true"></i><span>{{ __('ui.'.$label) }}</span>@if ($route === 'admin.service-cases.index')<span
                                                class="portal-nav-count {{ $openComplaintCount === 0 ? 'is-empty' : '' }}">{{ $openComplaintCount > 99 ? '99+' : $openComplaintCount }}<span
                                            class="visually-hidden"> open complaints</span></span>@endif</a>
                                @endforeach
                                @if ($collapsible)
                                    </details>
                                @endif
                        </div>
                    @endforeach
                </nav>
            </div>
        </aside>
        <div class="portal-workspace">
            <header class="portal-topbar">
                <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas"
                    data-bs-target="#portalNavigation" aria-controls="portalNavigation" aria-label="Open navigation"><i
                        class="bi bi-list" aria-hidden="true"></i></button>
                <div class="portal-context"><i
                        class="bi bi-{{ $role === 'customer' ? 'car-front' : ($role === 'shop_owner' ? 'shop' : ($role === 'mechanic' ? 'wrench-adjustable' : 'shield-check')) }}"
                        aria-hidden="true"></i><span><small>{{ $roleLabel }}
                            portal</small><strong>{{ $currentPageLabel }}</strong></span></div>
                <div class="d-flex align-items-center gap-2 ms-auto">
                    @if (in_array($role, ['customer', 'shop_owner', 'mechanic']))
                        <a class="portal-notifications-link"
                            href="{{ route($role === 'customer' ? 'customer.notifications.index' : 'shop.notifications.index') }}"
                            aria-label="Notifications, {{ $unreadNotificationCount }} unread"><i class="bi bi-bell"
                                aria-hidden="true"></i><span class="portal-notifications-label">{{ __('ui.' . 'Notifications') }}</span><span
                                class="portal-notifications-count {{ $unreadNotificationCount === 0 ? 'is-empty' : '' }}"
                                aria-hidden="true">{{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}</span></a>
                        <a class="portal-notifications-link portal-messages-link"
                            href="{{ route($role === 'customer' ? 'customer.messages.index' : 'shop.messages.index') }}"
                            aria-label="Messages, {{ $unreadMessageCount }} unread"><i class="bi bi-chat-dots"
                                aria-hidden="true"></i><span class="portal-notifications-label">{{ __('ui.' . 'Messages') }}</span><span
                                class="portal-messages-count {{ $unreadMessageCount === 0 ? 'is-empty' : '' }}"
                                aria-hidden="true">{{ $unreadMessageCount > 99 ? '99+' : $unreadMessageCount }}</span></a>
                    @endif
                    <div class="dropdown">
                        <button class="btn btn-light dropdown-toggle portal-account" type="button" data-bs-toggle="dropdown"
                            aria-expanded="false">
                            @if (auth()->user()->profile_photo)
                                <img class="account-avatar"
                                    src="{{ url('media/' . ltrim(auth()->user()->profile_photo, '/')) }}" alt="" width="28"
                                    height="28">
                            @else
                                <i class="bi bi-person-circle" aria-hidden="true"></i>
                            @endif
                            <span>{{ auth()->user()->name }}</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            @if ($role === 'customer')
                                <li><a class="dropdown-item" href="{{ route('customer.profile.edit') }}">{{ __('ui.' . 'My profile') }}</a></li>
                            @elseif (in_array($role, ['shop_owner', 'mechanic'], true))
                                <li><a class="dropdown-item" href="{{ route('shop.profile.edit') }}">{{ $role === 'mechanic' ? 'Mechanic profile' : 'Shop profile' }}</a></li>
                            @endif
                            @unless ($role === 'admin')
                                <li><a class="dropdown-item" href="{{ route('preferences.notifications.edit') }}">{{ __('alerts.preferences') }}</a></li>
                            @endunless
                            <li><a class="dropdown-item" href="{{ route('home') }}">{{ __('ui.' . 'Public website') }}</a></li>
                            <li><x-theme-toggle menu /></li>
                            <li><x-install-app menu /></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">@csrf<button
                                        class="dropdown-item text-danger" type="submit">{{ __('ui.' . 'Sign out') }}</button></form>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>
            <main class="portal-content" id="main-content" tabindex="-1">
                @if (session('status'))
                    <div class="alert alert-success alert-dismissible fade show" role="status">{{ session('status') }}<button
                            type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('ui.Dismiss notification') }}"></button>
                    </div>
                @endif
                @if ($messagesUnavailable ?? false)
                    <div class='alert alert-warning' role='alert'>
                        <i class='bi bi-exclamation-triangle me-2' aria-hidden='true'></i>
                        {{ __('ui.Messages are temporarily unavailable. Try again in a moment') }}
                    </div>
                @endif
                @if (
                        request()->integer('focus') > 0 && in_array(request()->route()?->getName(), [
                            'customer.appointments.index',
                            'shop.appointments.index',
                            'customer.quotations.index',
                            'shop.quotations.index',
                            'customer.invoices.index',
                            'shop.invoices.index',
                            'customer.service-cases.index',
                            'shop.service-cases.index',
                            'customer.emergency-requests.index',
                            'shop.emergency-requests.index',
                            'customer.reminders.index',
                            'customer.reviews.index',
                            'shop.reviews.index',
                        ], true)
                    )
                    <div class="notification-focus-bar">Showing the update you opened. <a
                            href="{{ route(request()->route()->getName()) }}">View all</a></div>
                @endif
                {{ $slot }}
            </main>
            <footer class="portal-footer d-flex flex-wrap align-items-center gap-3">
                <span>Minglanilla AutoCare Hub · {{ $roleLabel }} {{ __('ui.workspace') }}</span>
                <x-legal-links />
                <x-language-switcher prefix="portal-language" />
            </footer>
        </div>
        @if ($quickLinks !== [])
            <nav @class(['customer-mobile-nav', 'shop-mobile-nav' => in_array($role, ['shop_owner', 'mechanic'], true), 'admin-mobile-nav' => $role === 'admin'])
                aria-label="{{ __('ui.Quick navigation') }}">
                @foreach ($quickLinks as [$route, $label, $icon, $activeRoutes])
                    @php($active = request()->routeIs(...$activeRoutes))
                    <a href="{{ route($route) }}" @class(['active' => $active]) @if ($active) aria-current="page" @endif>
                        <i class="bi bi-{{ $icon }}" aria-hidden="true"></i><span>{{ __('ui.'.$label) }}</span>
                    </a>
                @endforeach
            </nav>
        @endif
        @if ($role === 'customer')
            @unless (request()->routeIs('customer.ai-assistant.*', 'customer.messages.show', 'customer.emergency-requests.*', 'customer.appointments.create', 'customer.repairs.*', 'preferences.notifications.edit'))
                <x-ai-chat-widget />
            @endunless
        @endif
        @stack('scripts')
    </body>

    </html>
@endguest
