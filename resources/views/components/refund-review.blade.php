@props(['payment', 'reviewUrl', 'checkUrl'])
<p class="mb-2">Refund status: <strong>{{ ucfirst(str_replace('_', ' ', $payment->refund_status)) }}</strong></p>
<p class="small text-break">Payment: {{ $payment->paymongo_payment_id }}@if ($payment->paymongo_refund_id)<br>Refund: {{ $payment->paymongo_refund_id }}@endif</p>
@if ($payment->refundReviewer)
    <p class="small">Reviewed by {{ $payment->refundReviewer->name }} on {{ $payment->refund_reviewed_at?->format('M j, Y g:i A') }}.@if ($payment->refund_review_notes)<br>{{ $payment->refund_review_notes }}@endif</p>
@endif
@if ($payment->refund_status === 'requested')
    <form method="POST" action="{{ $reviewUrl }}">
        @csrf @method('PATCH')
        <label class="form-label" for="refund-notes-{{ $payment->id }}">Review note (optional)</label>
        <textarea class="form-control mb-2" id="refund-notes-{{ $payment->id }}" name="notes" rows="2" maxlength="1000"></textarea>
        @if ($payment instanceof \App\Models\Payment)
            <label class="form-label" for="refund-resolution-{{ $payment->id }}">Invoice treatment</label>
            <select class="form-select mb-2" id="refund-resolution-{{ $payment->id }}" name="refund_resolution" required>
                <option value="">Choose treatment</option>
                <option value="credit">Credit cancelled or reduced service; lower amount due</option>
                <option value="replacement">Replacement payment needed; keep amount due</option>
            </select>
        @endif
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-primary" type="submit" name="decision" value="approved">Approve and send PHP {{ number_format($payment->refund_amount, 2) }} refund</button>
            <button class="btn btn-outline-danger" type="submit" name="decision" value="rejected" formnovalidate>Reject refund</button>
        </div>
    </form>
@else
    @if (! $payment->paymongo_refund_id)
        <p class="text-warning-emphasis">Confirmation is pending. Find this payment in PayMongo and match its refund ID below. Do not send another refund.</p>
    @endif
    <form method="POST" action="{{ $checkUrl }}" class="row g-2 align-items-end">
        @csrf
        @if (! $payment->paymongo_refund_id)
            <div class="col-md-8"><label class="form-label" for="refund-id-{{ $payment->id }}">PayMongo refund ID</label><input class="form-control" id="refund-id-{{ $payment->id }}" name="refund_id" placeholder="ref_…" maxlength="100" pattern="ref_[A-Za-z0-9_-]+" required></div>
        @endif
        <div class="col-auto"><button class="btn btn-outline-primary" type="submit">Check refund status</button></div>
    </form>
@endif
