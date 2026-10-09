<x-layouts.app title="Activity logs">
    <div class="page-heading">
        <h1>Activity logs</h1>
        <p>Administrative actions are retained here for accountability.</p>
    </div>
    <div class="surface overflow-hidden">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th>Administrator</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Description</th>
                        <th>When</th>
                    </tr>
                </thead>
                <tbody>@forelse($activityLogs as $activityLog)<tr>
                        <td>{{ $activityLog->user?->name ?? 'System' }}</td>
                        <td>{{ str_replace('_', ' ', $activityLog->action) }}</td>
                        <td>{{ str_replace('_', ' ', $activityLog->module) }}</td>
                        <td>{{ $activityLog->description }}</td>
                        <td>{{ $activityLog->created_at->format('M j, Y g:i A') }}</td>
                    </tr>@empty<tr>
                        <td colspan="5" class="p-4 text-secondary">No administrative activity has been recorded.</td>
                    </tr>@endforelse</tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $activityLogs->links() }}</div>
</x-layouts.app>