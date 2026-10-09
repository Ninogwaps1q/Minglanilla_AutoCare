@if ($errors->any())
    <div class="alert alert-danger" role="alert"><p class="fw-semibold">Please correct the following:</p><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
