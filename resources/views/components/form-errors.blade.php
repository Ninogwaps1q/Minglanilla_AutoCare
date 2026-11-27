@if ($errors->any())
    <div class="alert alert-danger" role="alert"><p class="fw-semibold">{{ request()->routeIs('admin.two-factor.*') ? 'Please correct the following:' : __('ui.Check the following fields') }}</p><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
