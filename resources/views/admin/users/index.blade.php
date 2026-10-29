<x-layouts.app title="User management">
    <div class="page-heading">
        <h1>User management</h1>
        <p>Search accounts and control customer or shop-owner access.</p>
    </div>
    <x-form-errors />
    <div class="surface p-4 mb-4">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-5"><label class="form-label" for="search">Search users</label><input id="search"
                    class="form-control" name="search" value="{{ $filters['search'] ?? '' }}"
                    placeholder="Name or email"></div>
            <div class="col-md-3"><label class="form-label" for="filter-role">Role</label><select id="filter-role" class="form-select" name="role">
                    <option value="">All roles</option>
                    @foreach(['customer' => 'Customer', 'shop_owner' => 'Shop owner', 'admin' => 'Administrator'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['role'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select></div>
            <div class="col-md-2"><label class="form-label" for="filter-status">Account status</label><select id="filter-status" class="form-select" name="status">
                    <option value="">All statuses</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="suspended" @selected(($filters['status'] ?? '') === 'suspended')>Suspended</option>
                </select></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Apply filters</button></div>
            @if (filled($filters['search'] ?? null) || filled($filters['role'] ?? null) || filled($filters['status'] ?? null))
                <div class="col-12"><a href="{{ route('admin.users.index') }}">Clear filters and show all users</a></div>
            @endif
        </form>
    </div>
    <p class="small text-secondary">{{ number_format($users->total()) }} {{ $users->total() === 1 ? 'account' : 'accounts' }} found.</p>
    <div class="surface overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0 stacked-mobile-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th class="text-end">Access</th>
                    </tr>
                </thead>
                <tbody>@forelse($users as $managedUser)
                    <tr>
                        <td data-label="User"><strong>{{ $managedUser->name }}</strong><br><span
                                class="small text-secondary">{{ $managedUser->email }}</span></td>
                        <td data-label="Role" class="text-capitalize">{{ str_replace('_', ' ', $managedUser->role) }}</td>
                        <td data-label="Status"><span
                                class="badge text-bg-{{ $managedUser->status === 'active' ? 'success' : 'secondary' }} text-capitalize">{{ $managedUser->status }}</span>
                        </td>
                        <td data-label="Joined">{{ $managedUser->created_at->format('M j, Y') }}</td>
                        <td data-label="Access" class="text-end">@if(!$managedUser->isAdmin())
                            <form method="POST" action="{{ route('admin.users.update', $managedUser) }}">@csrf
                                @method('PATCH')<button
                                    class="btn btn-sm btn-{{ $managedUser->status === 'active' ? 'outline-danger' : 'success' }}"
                                    name="status"
                                    value="{{ $managedUser->status === 'active' ? 'suspended' : 'active' }}" aria-label="{{ $managedUser->status === 'active' ? 'Suspend' : 'Reactivate' }} {{ $managedUser->name }}">{{ $managedUser->status === 'active' ? 'Suspend' : 'Reactivate' }}</button>
                        </form>@else<span class="text-secondary small">Administrator</span>@endif
                        </td>
                </tr>@empty<tr>
                        <td colspan="5" class="p-4 text-secondary">No users match these filters.</td>
                    </tr>@endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $users->links() }}</div>
</x-layouts.app>
