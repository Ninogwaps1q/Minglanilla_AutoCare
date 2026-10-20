const scanner = document.querySelector('[data-passport-scanner]');

if (scanner) {
    const video = scanner.querySelector('[data-scanner-video]');
    const placeholder = scanner.querySelector('[data-scanner-placeholder]');
    const status = scanner.querySelector('[data-scanner-status]');
    const startButton = scanner.querySelector('[data-scanner-start]');
    const stopButton = scanner.querySelector('[data-scanner-stop]');
    const form = scanner.querySelector('[data-scanner-form]');
    const linkInput = scanner.querySelector('#share_url');

    let detector;
    let stream;
    let timer;
    let wantsCamera = false;

    const setStatus = (message) => {
        status.textContent = message;
    };

    const stopCamera = () => {
        wantsCamera = false;
        window.clearTimeout(timer);
        stream?.getTracks().forEach((track) => track.stop());
        stream = undefined;
        video.pause();
        video.srcObject = null;
        video.hidden = true;
        placeholder.hidden = false;
        startButton.disabled = false;
        startButton.hidden = false;
        stopButton.hidden = true;
    };

    const scanFrame = async () => {
        if (!wantsCamera || !stream) {
            return;
        }

        try {
            if (video.readyState >= HTMLMediaElement.HAVE_CURRENT_DATA) {
                const codes = await detector.detect(video);
                const value = codes.find((code) => code.rawValue)?.rawValue?.trim();

                if (value) {
                    let url;
                    try {
                        url = new URL(value);
                    } catch {
                        setStatus('This QR is not a website link. Ask the customer to show their shop-sharing QR.');
                    }

                    if (url && url.origin !== window.location.origin) {
                        setStatus('This QR belongs to a different website. Ask the customer for this site’s shop-sharing QR.');
                    } else if (url) {
                        stopCamera();
                        linkInput.value = value;
                        setStatus('QR found. Opening the service passport…');
                        form.requestSubmit();
                        return;
                    }
                }
            }
        } catch {
            stopCamera();
            setStatus('Could not read from the camera. Restart it or paste the sharing link below.');
            return;
        }

        if (wantsCamera) {
            timer = window.setTimeout(scanFrame, 180);
        }
    };

    startButton.addEventListener('click', async () => {
        if (wantsCamera || !detector) {
            return;
        }

        wantsCamera = true;
        startButton.disabled = true;
        stopButton.hidden = false;
        setStatus('Requesting camera access…');

        try {
            const cameraStream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: { facingMode: { ideal: 'environment' } },
            });

            if (!wantsCamera) {
                cameraStream.getTracks().forEach((track) => track.stop());
                return;
            }

            stream = cameraStream;
            video.srcObject = stream;
            video.hidden = false;
            placeholder.hidden = true;
            await video.play();
            setStatus('Camera is ready. Hold the shop-sharing QR steady inside the preview.');
            scanFrame();
        } catch (error) {
            stopCamera();
            setStatus(error?.name === 'NotAllowedError'
                ? 'Camera access was denied. Allow the camera in your browser settings, or paste the link below.'
                : 'Camera could not start. Check your camera or paste the sharing link below.');
        }
    });

    stopButton.addEventListener('click', () => {
        stopCamera();
        setStatus('Camera stopped. You can start it again or paste a sharing link.');
    });

    window.addEventListener('pagehide', stopCamera);

    const prepareCamera = async () => {
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            startButton.disabled = true;
            setStatus('Camera scanning needs HTTPS or localhost. Paste the sharing link below instead.');
            return;
        }

        if (!('BarcodeDetector' in window)) {
            startButton.disabled = true;
            setStatus('This browser cannot scan QR codes here. Use its camera app to copy the link, then paste it below.');
            return;
        }

        try {
            if (typeof BarcodeDetector.getSupportedFormats === 'function'
                && !(await BarcodeDetector.getSupportedFormats()).includes('qr_code')) {
                throw new Error('QR codes are unsupported');
            }

            detector = new BarcodeDetector({ formats: ['qr_code'] });
        } catch {
            startButton.disabled = true;
            setStatus('This browser cannot scan QR codes here. Use its camera app to copy the link, then paste it below.');
        }
    };

    prepareCamera();
}
