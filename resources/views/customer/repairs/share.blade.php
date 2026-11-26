<x-layouts.app :title="__('extensions.share.manage')">
    <div class="page-heading">
        <h1>{{ __('extensions.share.manage') }}</h1>
        <p>{{ $repairJob->job_number }}</p>
        <a href="{{ route('customer.repairs.index', ['repair_job_id' => $repairJob->id]) }}">{{ __('extensions.share.back') }}</a>
    </div>
    <x-form-errors />
    @if (session('repair_share_url'))
        <div class="surface p-4 mb-3">
            <label class="form-label" for="repair-share-url">{{ __('extensions.share.copy') }}</label>
            <input class="form-control" id="repair-share-url" type="url" value="{{ session('repair_share_url') }}" readonly>
            <p class="form-text mb-0">{{ __('extensions.share.copy_help') }}</p>
        </div>
    @endif
    <form method="POST" action="{{ route('customer.repairs.shares.store', $repairJob) }}" class="surface p-4 mb-3">
        @csrf
        <p>{{ __('extensions.share.public_access') }}</p>
        <label for="share-days" class="form-label">{{ __('extensions.share.days') }}</label>
        <input class="form-control w-auto mb-3" type="number" min="1" max="30" name="days" id="share-days" value="{{ old('days', 7) }}" required>
        @if ($repairJob->photos->isNotEmpty())
            <fieldset class="mb-3">
                <legend class="h6">{{ __('extensions.share.photos') }}</legend>
                <p class="form-text">{{ __('extensions.share.photo_hint') }}</p>
                <div class="row g-3">
                    @foreach ($repairJob->photos as $photo)
                        <div class="col-6 col-sm-4 col-lg-3">
                            <img width="240" height="160" class="img-fluid rounded mb-1" loading="lazy" src="{{ route('repair-photos.show', $photo) }}" alt="{{ __('extensions.share.stage.'.$photo->stage) }}">
                            <label class="form-check small text-break">
                                <input type="checkbox" class="form-check-input" name="photo_ids[]" value="{{ $photo->id }}" @checked(in_array($photo->id, old('photo_ids', [])))>
                                {{ __('extensions.share.select_photo', ['photo' => __('extensions.share.stage.'.$photo->stage), 'number' => $loop->iteration]) }}
                            </label>
                        </div>
                    @endforeach
                </div>
            </fieldset>
        @endif
        <button class="btn btn-primary">{{ __('extensions.share.create') }}</button>
    </form>
    <section class="surface p-4">
        <h2 class="h5">{{ __('extensions.share.active') }}</h2>
        @forelse ($shares as $share)
            <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center border-bottom py-2">
                <span>{{ __('extensions.share.expires', ['date' => \Illuminate\Support\Carbon::parse($share->expires_at)->timezone('Asia/Manila')->format('M j, Y, g:i A').' PHT']) }}</span>
                <form method="POST" action="{{ route('customer.repairs.shares.revoke', [$repairJob, $share->id]) }}">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger" data-confirm="{{ __('extensions.share.confirm_revoke') }}">{{ __('extensions.share.revoke') }}</button>
                </form>
            </div>
        @empty
            <p class="mb-0">{{ __('extensions.share.empty_links') }}</p>
        @endforelse
    </section>
</x-layouts.app>
