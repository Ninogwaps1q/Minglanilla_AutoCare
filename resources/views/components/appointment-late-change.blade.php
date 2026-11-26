@props(['appointment'])
@if ($appointment->needsLateChangeAcknowledgement())
    <label class="form-check-label small d-block mb-2">
        <input type="checkbox" class="form-check-input me-1" name="acknowledge_late_change" value="1" required>
        {{ __('extensions.appointments.acknowledge_late') }}
    </label>
@endif
