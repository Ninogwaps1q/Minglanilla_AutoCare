<x-layouts.app title="Privacy requests">
    <div class="page-heading"><h1>Privacy requests</h1><p>Verify the requester and review any payment, dispute, or legal retention needs before processing a request.</p></div>
    <x-form-errors />
    <div class="vstack gap-3">
        @forelse ($privacyRequests as $item)
            <article class="surface p-4">
                <h2 class="h5">{{ __('privacy.'.$item->type) }} · #{{ $item->id }}</h2>
                <p class="text-break">{{ $item->user?->email ?? 'Account removed' }} · {{ $item->created_at->toDateString() }} · {{ ucfirst($item->status) }}</p>
                @if ($item->status === 'pending')
                    <p class="small text-secondary">Fulfill the request securely before marking it completed. This action records the decision.</p>
                    <form method="POST" action="{{ route('admin.privacy-requests.update', $item) }}">@csrf @method('PATCH')
                        <label for="reason-{{ $item->id }}" class="form-label">Decision note</label>
                        <textarea id="reason-{{ $item->id }}" name="reason" class="form-control mb-3" rows="2" maxlength="500" required></textarea>
                        <div class="d-flex flex-wrap gap-2"><button class="btn btn-primary" name="status" value="completed">Mark fulfilled</button><button class="btn btn-outline-danger" name="status" value="declined">Decline request</button></div>
                    </form>
                @endif
            </article>
        @empty
            <p class="surface p-4">No privacy requests yet.</p>
        @endforelse
        {{ $privacyRequests->links() }}
    </div>
</x-layouts.app>
