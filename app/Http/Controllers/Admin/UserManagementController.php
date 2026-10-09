<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserStatusRequest;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'in:customer,shop_owner,admin'],
            'status' => ['nullable', 'in:active,suspended'],
        ]);

        return view('admin.users.index', [
            'users' => User::query()
                ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
                ->when($filters['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->latest()
                ->paginate()
                ->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function update(UpdateUserStatusRequest $request, User $user): RedirectResponse
    {
        abort_if($user->isAdmin(), 403);

        $user->update($request->validated());
        ActivityLog::query()->create([
            'user_id' => $request->user()->id,
            'action' => 'updated_account_status',
            'module' => 'user_management',
            'description' => "Set {$user->email} status to {$user->status}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('status', 'User account status updated.');
    }
}
