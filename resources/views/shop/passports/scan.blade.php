<x-layouts.app title="Scan service passport">
    <div class="page-heading">
        <h1>Scan a service passport</h1>
        <p>Ask the customer to open their vehicle passport and show the temporary shop-sharing QR. Only active, approved
            shops can view it.</p>
    </div>

    <div class="row g-4 align-items-start" data-passport-scanner>
        <div class="col-12 col-xl-7">
            <section class="surface p-4" aria-labelledby="scan-camera-title">
                <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-3">
                    <div>
                        <h2 id="scan-camera-title" class="h4 mb-1">Scan with camera</h2>
                        <p class="text-secondary mb-0">Point your camera at the customer's shop-sharing QR code.</p>
                    </div>
                    <span class="badge text-bg-secondary">Read-only access</span>
                </div>
                <div class="passport-scanner-preview" data-scanner-preview>
                    <video data-scanner-video autoplay muted playsinline aria-label="Live camera preview"
                        hidden></video>
                    <div class="passport-scanner-placeholder" data-scanner-placeholder><i class="bi bi-qr-code-scan"
                            aria-hidden="true"></i><span>Camera is off</span></div>
                </div>
                <p class="small text-secondary mt-3 mb-3" role="status" aria-live="polite" data-scanner-status>Choose
                    Start camera when the customer is ready.</p>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-primary" data-scanner-start><i class="bi bi-camera me-2"
                            aria-hidden="true"></i>Start camera</button>
                    <button type="button" class="btn btn-outline-secondary" data-scanner-stop hidden>Stop
                        camera</button>
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-5">
            <section class="surface p-4" aria-labelledby="scan-link-title">
                <h2 id="scan-link-title" class="h4">Use the sharing link</h2>
                <p class="text-secondary">If the camera is unavailable, ask the customer to copy their shop-sharing link
                    and paste it below.</p>
                <form action="{{ route('shop.passports.open') }}" method="POST" data-scanner-form>
                    @csrf
                    <label for="share_url" class="form-label">Customer's shop-sharing link</label>
                    <input id="share_url" name="share_url" type="url"
                        class="form-control @error('share_url') is-invalid @enderror" value="{{ old('share_url') }}"
                        placeholder="https://your-site.com/shop/shared-passports/..." autocomplete="off"
                        spellcheck="false" inputmode="url" required
                        aria-describedby="share-url-help @error('share_url') share-url-error @enderror">
                    @error('share_url')
                    <div id="share-url-error" class="invalid-feedback">{{ $message }}</div>@enderror
                    <p id="share-url-help" class="small text-secondary mt-2">Use the temporary shop QR or link, not the
                        customer's private QR. Expired or revoked links cannot be opened.</p>
                    <button class="btn btn-primary mt-2" type="submit">Open service passport</button>
                </form>
            </section>
            <p class="small text-secondary mt-3 mb-0">Camera scanning needs permission and a secure connection (HTTPS or
                localhost). The camera is used only on this device; the video is not uploaded.</p>
        </div>
    </div>
</x-layouts.app>