<x-layouts.app title="Technicians">
    <div class="page-heading"><h1>Technicians</h1><p>Manage your team and assign active technicians to repair jobs.</p></div>
    @if ($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <details class="surface workspace-disclosure mb-4" @if ($technicians->isEmpty() || old('_create_form') === 'technicians') open @endif>
        <summary>Add a technician</summary>
        <div class="workspace-disclosure__body">
    <form method="POST" enctype="multipart/form-data" action="{{ route('shop.technicians.store') }}" class="row g-3">
        @csrf
        <input type="hidden" name="_create_form" value="technicians">
        <div class="col-md-4"><label class="form-label" for="name">Name</label><input id="name" class="form-control" name="name" value="{{ old('_create_form') === 'technicians' ? old('name') : '' }}" required maxlength="255"></div>
        <div class="col-md-3"><label class="form-label" for="phone">Phone</label><input id="phone" class="form-control" name="phone" value="{{ old('_create_form') === 'technicians' ? old('phone') : '' }}" maxlength="30"></div>
        <div class="col-md-3"><label class="form-label" for="specialization">Specialization</label><input id="specialization" class="form-control" name="specialization" value="{{ old('_create_form') === 'technicians' ? old('specialization') : '' }}" maxlength="255"></div>
        <div class="col-md-2"><label class="form-label" for="experience_years">Experience (years)</label><input id="experience_years" class="form-control" type="number" min="0" max="80" name="experience_years" value="{{ old('_create_form') === 'technicians' ? old('experience_years', 0) : 0 }}" required></div>
        <div class="col-md-3"><label class="form-label" for="max_active_jobs">Maximum active jobs</label><input id="max_active_jobs" class="form-control" type="number" min="1" max="20" name="max_active_jobs" value="{{ old('_create_form') === 'technicians' ? old('max_active_jobs', 3) : 3 }}" required></div>
        <input type="hidden" name="status" value="active">
        <div class="col-md-6"><label class="form-label" for="photo">Photo</label><input id="photo" class="form-control @error('photo') is-invalid @enderror" type="file" name="photo" accept="image/png,image/jpeg,image/webp" aria-describedby="technician-photo-help" data-image-preview-target="technician-photo-preview" data-image-max-size="2097152">@error('photo')<div class="invalid-feedback">{{ $message }}</div>@enderror<small id="technician-photo-help" class="form-text">JPG, PNG, or WebP up to 2 MB.</small></div>
        <div class="col-md-6 d-flex align-items-center gap-3"><div id="technician-photo-preview" class="technician-avatar uploaded-image-fallback" role="img" aria-label="Technician photo preview"><i class="bi bi-person" aria-hidden="true"></i></div><span class="small text-secondary">Photo preview. Save the technician to publish it.</span></div>
        <div class="col-12"><button class="btn btn-primary">Add technician</button></div>
    </form>
        </div>
    </details>
    <div class="surface table-responsive mb-4"><table class="table align-middle mb-0"><caption class="visually-hidden">Technician roster</caption><thead><tr><th scope="col">Technician</th><th scope="col">Specialization</th><th scope="col">Experience</th><th scope="col">Active assignments</th><th scope="col">Status</th></tr></thead><tbody>
        @forelse ($technicians as $technician)
            <tr><td><div class="d-flex align-items-center gap-3"><x-uploaded-image class="technician-avatar" :path="$technician->photo" :alt="$technician->name.' photo'" fallback-icon="person" /><div><strong>{{ $technician->name }}</strong><br><span class="small text-secondary">{{ $technician->phone }}</span></div></div></td><td>{{ $technician->specialization ?: 'General service' }}</td><td>{{ $technician->experience_years }} years</td><td>
                <strong>{{ $technician->active_assignments_count }} / {{ $technician->max_active_jobs }}</strong>
                @php($upcomingVisits = $technician->activeAssignments->filter(fn ($assignment) => $assignment->repairJob?->appointment?->appointment_date?->toDateString() >= today()->toDateString())->take(3))
                @foreach($upcomingVisits as $assignment)
                    <span class="d-block small text-secondary">{{ $assignment->repairJob->appointment->appointment_date->format('M j') }} at {{ $assignment->repairJob->appointment->appointment_time->format('g:i A') }} · {{ $assignment->repairJob->job_number }}</span>
                @endforeach
                @if($technician->active_assignments_count >= $technician->max_active_jobs)<span class="d-block small text-danger">At capacity</span>@endif
            </td><td>{{ ucfirst($technician->status) }}</td></tr>
            <tr><td colspan="5"><details><summary>Edit {{ $technician->name }} and capacity</summary>
                <form method="POST" enctype="multipart/form-data" action="{{ route('shop.technicians.update', $technician) }}" class="row g-2 align-items-end mt-2">@csrf @method('PATCH')
                    <div class="col-md-3"><label class="form-label">Name<input class="form-control" name="name" value="{{ $technician->name }}" required></label></div>
                    <div class="col-md-2"><label class="form-label">Phone<input class="form-control" name="phone" value="{{ $technician->phone }}"></label></div>
                    <div class="col-md-2"><label class="form-label">Specialization<input class="form-control" name="specialization" value="{{ $technician->specialization }}"></label></div>
                    <div class="col-md-2"><label class="form-label">Experience (years)<input class="form-control" type="number" min="0" max="80" name="experience_years" value="{{ $technician->experience_years }}" required></label></div>
                    <div class="col-md-2"><label class="form-label">Active job limit<input class="form-control" type="number" min="1" max="20" name="max_active_jobs" value="{{ $technician->max_active_jobs }}" required></label></div>
                    <div class="col-md-2"><label class="form-label">Status<select class="form-select" name="status"><option value="active" @selected($technician->status === 'active')>Active</option><option value="inactive" @selected($technician->status === 'inactive')>Inactive</option></select></label></div>
                    <div class="col-md-4"><label class="form-label">Replace photo<input class="form-control" type="file" name="photo" accept="image/png,image/jpeg,image/webp"></label></div>
                    <div class="col-md-2"><button class="btn btn-outline-primary">Save technician</button></div>
                </form>
            </details></td></tr>
        @empty<tr><td colspan="5" class="p-4">No technicians added yet.</td></tr>@endforelse
    </tbody></table></div>
    <p class="small text-secondary">Active-job limits and overlapping booked visit times are checked before assignment. Jobs without appointment times still count toward the active-job limit.</p>
    <h2 class="h4">Assign to repair jobs</h2>
    @forelse ($repairJobs as $repairJob)
        <article class="surface p-3 mb-3"><div class="d-flex justify-content-between"><strong>{{ $repairJob->job_number }}</strong><span>{{ $repairJob->status }}</span></div><p class="mb-3">{{ $repairJob->description }}</p><form method="POST" action="{{ route('shop.technicians.assign', $repairJob) }}" class="row g-2 align-items-end">@csrf<div class="col-md-5"><label class="form-label" for="technician-{{ $repairJob->id }}">Technician</label><select id="technician-{{ $repairJob->id }}" class="form-select" name="technician_id" required><option value="">Choose technician</option>@foreach ($technicians->where('status', 'active') as $technician)<option value="{{ $technician->id }}" @disabled($technician->active_assignments_count >= $technician->max_active_jobs)>{{ $technician->name }} · {{ $technician->active_assignments_count }}/{{ $technician->max_active_jobs }} active jobs{{ $technician->active_assignments_count >= $technician->max_active_jobs ? ' (full)' : '' }}</option>@endforeach</select></div><div class="col-md-5"><label class="form-label" for="assignment-notes-{{ $repairJob->id }}">Assignment notes (optional)</label><input id="assignment-notes-{{ $repairJob->id }}" class="form-control" name="notes" maxlength="1000" placeholder="Instructions for the technician"></div><div class="col-md-2"><button class="btn btn-outline-primary w-100" type="submit">Assign technician</button></div></form></article>
    @empty
        <div class="surface p-4">No active repair jobs to assign.</div>
    @endforelse
</x-layouts.app>
