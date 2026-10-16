<x-layouts.app title="Technicians">
    <div class="page-heading"><h1>Technicians</h1><p>Manage your team and assign active technicians to repair jobs.</p></div>
    @if ($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" enctype="multipart/form-data" action="{{ route('shop.technicians.store') }}" class="surface p-4 row g-3 mb-4">
        @csrf
        <div class="col-md-4"><label class="form-label" for="name">Name</label><input id="name" class="form-control" name="name" required maxlength="255"></div>
        <div class="col-md-3"><label class="form-label" for="phone">Phone</label><input id="phone" class="form-control" name="phone" maxlength="30"></div>
        <div class="col-md-3"><label class="form-label" for="specialization">Specialization</label><input id="specialization" class="form-control" name="specialization" maxlength="255"></div>
        <div class="col-md-2"><label class="form-label" for="experience_years">Experience (years)</label><input id="experience_years" class="form-control" type="number" min="0" max="80" name="experience_years" value="0" required></div>
        <input type="hidden" name="status" value="active">
        <div class="col-md-6"><label class="form-label" for="photo">Photo</label><input id="photo" class="form-control @error('photo') is-invalid @enderror" type="file" name="photo" accept="image/png,image/jpeg,image/webp" aria-describedby="technician-photo-help" data-image-preview-target="technician-photo-preview" data-image-max-size="2097152">@error('photo')<div class="invalid-feedback">{{ $message }}</div>@enderror<small id="technician-photo-help" class="form-text">JPG, PNG, or WebP up to 2 MB.</small></div>
        <div class="col-md-6 d-flex align-items-center gap-3"><div id="technician-photo-preview" class="technician-avatar uploaded-image-fallback" role="img" aria-label="Technician photo preview"><i class="bi bi-person" aria-hidden="true"></i></div><span class="small text-secondary">Photo preview. Save the technician to publish it.</span></div>
        <div class="col-12"><button class="btn btn-primary">Add technician</button></div>
    </form>
    <div class="surface table-responsive mb-4"><table class="table align-middle mb-0"><caption class="visually-hidden">Technician roster</caption><thead><tr><th scope="col">Technician</th><th scope="col">Specialization</th><th scope="col">Experience</th><th scope="col">Active assignments</th><th scope="col">Status</th></tr></thead><tbody>
        @forelse ($technicians as $technician)<tr><td><div class="d-flex align-items-center gap-3"><x-uploaded-image class="technician-avatar" :path="$technician->photo" :alt="$technician->name.' photo'" fallback-icon="person" /><div><strong>{{ $technician->name }}</strong><br><span class="small text-secondary">{{ $technician->phone }}</span></div></div></td><td>{{ $technician->specialization ?: 'General service' }}</td><td>{{ $technician->experience_years }} years</td><td>{{ $technician->active_assignments_count }}</td><td>{{ ucfirst($technician->status) }}</td></tr>@empty<tr><td colspan="5" class="p-4">No technicians added yet.</td></tr>@endforelse
    </tbody></table></div>
    <h2 class="h4">Assign to repair jobs</h2>
    @forelse ($repairJobs as $repairJob)
        <article class="surface p-3 mb-3"><div class="d-flex justify-content-between"><strong>{{ $repairJob->job_number }}</strong><span>{{ $repairJob->status }}</span></div><p class="mb-3">{{ $repairJob->description }}</p><form method="POST" action="{{ route('shop.technicians.assign', $repairJob) }}" class="row g-2 align-items-end">@csrf<div class="col-md-5"><label class="form-label" for="technician-{{ $repairJob->id }}">Technician</label><select id="technician-{{ $repairJob->id }}" class="form-select" name="technician_id" required><option value="">Choose technician</option>@foreach ($technicians->where('status', 'active') as $technician)<option value="{{ $technician->id }}">{{ $technician->name }}</option>@endforeach</select></div><div class="col-md-5"><label class="form-label" for="assignment-notes-{{ $repairJob->id }}">Assignment notes (optional)</label><input id="assignment-notes-{{ $repairJob->id }}" class="form-control" name="notes" maxlength="1000" placeholder="Instructions for the technician"></div><div class="col-md-2"><button class="btn btn-outline-primary w-100" type="submit">Assign technician</button></div></form></article>
    @empty
        <div class="surface p-4">No active repair jobs to assign.</div>
    @endforelse
</x-layouts.app>
