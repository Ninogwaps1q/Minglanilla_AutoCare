<x-layouts.app title="Staff accounts">
    <div class="page-heading"><h1>Staff accounts</h1><p>Give each person their own sign-in. Access is limited to {{ $shop->name }} and their assigned role.</p></div>
    <x-form-errors />
    <section class="surface p-4 mb-4" aria-labelledby="invite-title">
        <h2 id="invite-title" class="h4">Invite staff</h2>
        <p class="text-secondary">They receive an email link to set a password. Receptionists manage bookings and estimates; technicians handle repairs; cashiers manage invoices and reports.</p>
        <form method="POST" action="{{ route('shop.staff.store') }}" class="row g-3 align-items-end">@csrf
            <div class="col-md-4"><label class="form-label" for="staff-name">Full name</label><input class="form-control" id="staff-name" name="name" value="{{ old('name') }}" maxlength="255" required></div>
            <div class="col-md-4"><label class="form-label" for="staff-email">Email address</label><input class="form-control" id="staff-email" type="email" name="email" value="{{ old('email') }}" required></div>
            <div class="col-md-2"><label class="form-label" for="staff-role">Role</label><select class="form-select" id="staff-role" name="staff_role" required><option value="receptionist">Receptionist</option><option value="technician">Technician</option><option value="cashier">Cashier</option></select></div>
            <div class="col-md-2"><button class="btn btn-primary" type="submit">Send invitation</button></div>
        </form>
    </section>
    <section class="surface p-4" aria-labelledby="team-title">
        <h2 id="team-title" class="h4">Team access</h2>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th scope="col">Staff member</th><th scope="col">Status</th><th scope="col">Role and access</th></tr></thead><tbody>
            @forelse ($staff as $member)
                <tr><td><strong>{{ $member->name }}</strong><br><span class="text-secondary">{{ $member->email }}</span></td><td>{{ ucfirst($member->status) }}</td><td>
                    @if ($member->status === 'invited')<form method="POST" action="{{ route('shop.staff.resend', $member) }}">@csrf<button class="btn btn-sm btn-outline-primary" type="submit">Resend setup link</button></form>@else
                        <form method="POST" action="{{ route('shop.staff.update', $member) }}" class="d-flex flex-wrap gap-2">@csrf @method('PATCH')
                            <select class="form-select form-select-sm w-auto" name="staff_role" aria-label="Role for {{ $member->name }}"><option value="receptionist" @selected($member->staff_role === 'receptionist')>Receptionist</option><option value="technician" @selected($member->staff_role === 'technician')>Technician</option><option value="cashier" @selected($member->staff_role === 'cashier')>Cashier</option></select>
                            <select class="form-select form-select-sm w-auto" name="status" aria-label="Access for {{ $member->name }}"><option value="active" @selected($member->status === 'active')>Active</option><option value="suspended" @selected($member->status === 'suspended')>Suspended</option></select>
                            <button class="btn btn-sm btn-outline-primary" type="submit">Save access</button>
                        </form>
                    @endif
                </td></tr>
            @empty
                <tr><td colspan="3" class="text-secondary">No staff accounts yet.</td></tr>
            @endforelse
        </tbody></table></div>
    </section>
</x-layouts.app>
