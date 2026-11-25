<div class="form-check">
    <input class="form-check-input @error('legal_consent') is-invalid @enderror" name="legal_consent" id="legal-consent" type="checkbox" value="1" @checked(old('legal_consent')) required aria-describedby="legal-consent-links">
    <label class="form-check-label" for="legal-consent">{{ __('privacy.agreement') }}</label>
    <div class="form-text d-flex flex-wrap gap-2" id="legal-consent-links"><x-legal-links /></div>
    @error('legal_consent')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
