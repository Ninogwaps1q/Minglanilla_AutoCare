<div class="border rounded p-3 mb-2">
    <input type="hidden" name="items[{{ $index }}][category]" value="{{ $item['category'] }}">
    <input type="hidden" name="items[{{ $index }}][item_name]" value="{{ $item['item_name'] }}">
    <div class="form-check">
        <input id="problem-{{ $index }}" class="form-check-input" type="checkbox" name="items[{{ $index }}][has_problem]" value="1" @checked(($item['has_problem'] ?? false) || in_array($item['condition'] ?? '', ['Needs Attention', 'Critical'], true))>
        <label for="problem-{{ $index }}" class="form-check-label fw-semibold">{{ $item['category'] }} · {{ $item['item_name'] }} has a problem</label>
    </div>
    <label for="item-note-{{ $index }}" class="form-label mt-2">Problem note (optional)</label>
    <textarea id="item-note-{{ $index }}" class="form-control" name="items[{{ $index }}][recommendation]" rows="2" maxlength="5000" placeholder="Describe what you found and what you recommend.">{{ $item['recommendation'] ?? '' }}</textarea>
</div>
