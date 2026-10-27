<x-layouts.app title="Additional work estimate">
    <div class="page-heading">
        <h1>Additional work estimate</h1>
        <p>{{ $repairJob->job_number }} · Ask the customer to approve the revised total before adding work.</p>
    </div>
    <x-form-errors />
    <section class="surface p-4 mb-3">
        <h2 class="h5">Already approved</h2>
        <p class="mb-0">Current approved total: <strong>PHP
                {{ number_format($repairJob->quotation->total, 2) }}</strong>. The new estimate will include these
            existing items plus the extra work below.</p>
    </section>
    <form method="POST" action="{{ route('shop.quotations.supplement.store', $repairJob) }}" class="surface p-4">@csrf
        <h2 class="h5">Extra work</h2>
        <p class="text-secondary small">Add only new work. Existing approved items stay on the revised estimate
            automatically.</p>
        <div id="supplement-items" class="vstack gap-3">
            @foreach(old('items', [['type' => 'labor', 'description' => '', 'quantity' => 1, 'unit_price' => '']]) as $index => $item)
                <div class="row g-2 align-items-end border rounded p-2">
                    <div class="col-md-2"><label class="form-label">Type<select class="form-select"
                                name="items[{{ $index }}][type]">
                                <option value="labor" @selected(($item['type'] ?? '') === 'labor')>Labor</option>
                                <option value="part" @selected(($item['type'] ?? '') === 'part')>Part</option>
                                <option value="service" @selected(($item['type'] ?? '') === 'service')>Service</option>
                                <option value="other" @selected(($item['type'] ?? '') === 'other')>Other</option>
                            </select></label></div>
                    <div class="col-md-3"><label class="form-label">Description<input class="form-control"
                                name="items[{{ $index }}][description]" value="{{ $item['description'] ?? '' }}" required
                                maxlength="1000"></label></div>
                    <div class="col-md-2"><label class="form-label">Quantity<input class="form-control" type="number"
                                name="items[{{ $index }}][quantity]" min="1" value="{{ $item['quantity'] ?? 1 }}"
                                required></label></div>
                    <div class="col-md-2"><label class="form-label">Unit price (PHP)<input class="form-control"
                                type="number" name="items[{{ $index }}][unit_price]" min="0" step="0.01"
                                value="{{ $item['unit_price'] ?? '' }}" required></label></div>
                    <div class="col-md-3"><label class="form-label">Inventory part (optional)<select class="form-select"
                                name="items[{{ $index }}][spare_part_id]">
                                <option value="">No linked stock</option>@foreach($parts as $part)
                                    <option value="{{ $part->id }}" @selected(($item['spare_part_id'] ?? '') == $part->id)>
                                        {{ $part->name }} · {{ $part->quantity }} on hand
                                </option>@endforeach
                            </select></label></div>
                </div>
            @endforeach
        </div>
        <button type="button" id="add-supplement-item" class="btn btn-outline-secondary mt-3">Add another item</button>
        <div class="row g-3 mt-2">
            <div class="col-md-8"><label class="form-label" for="supplement-notes">Why is this extra work
                    needed?</label><textarea class="form-control" id="supplement-notes" name="notes" minlength="10"
                    maxlength="5000" required rows="3">{{ old('notes') }}</textarea></div>
            <div class="col-md-4"><label class="form-label" for="supplement-valid">Offer valid until</label><input
                    class="form-control" id="supplement-valid" type="date" name="valid_until"
                    min="{{ today()->toDateString() }}"
                    value="{{ old('valid_until', today()->addDays(7)->toDateString()) }}" required></div>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-4"><button class="btn btn-primary">Send revised estimate</button><a
                class="btn btn-outline-secondary"
                href="{{ route('shop.repair-jobs.index', ['repair_job_id' => $repairJob->id]) }}">Back to repair</a>
        </div>
    </form>
    @push('scripts')
        <script>
            document.getElementById('add-supplement-item')?.addEventListener('click', function () {
                const container = document.getElementById('supplement-items');
                const index = container.children.length;
                if (index >= 20) { return; }
                const clone = container.firstElementChild.cloneNode(true);
                clone.querySelectorAll('input, select').forEach(field => {
                    field.name = field.name.replace(/items\[\d+\]/, 'items[' + index + ']');
                    if (field.tagName === 'INPUT') { field.value = field.type === 'number' && field.name.endsWith('[quantity]') ? '1' : ''; }
                    else { field.selectedIndex = 0; }
                });
                container.appendChild(clone);
                clone.querySelector('input').focus();
                this.disabled = container.children.length >= 20;
            });
        </script>
    @endpush
</x-layouts.app>