const controls = document.querySelectorAll('[data-install-app]');
const installButtons = document.querySelectorAll('[data-install-button]');
const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
const isAppleMobile = /iPad|iPhone|iPod/.test(navigator.userAgent)
    || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
let installPrompt;

const showInstallControls = () => {
    controls.forEach(control => {
        const showIosHint = !standalone && !installPrompt && isAppleMobile;
        control.hidden = !installPrompt && !showIosHint;
        control.querySelector('[data-install-button]').hidden = !installPrompt;
        control.querySelector('[data-install-ios]').hidden = !showIosHint;
    });
};

if (document.querySelector('link[rel="manifest"]') && 'serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(error => console.warn('AutoCare offline mode could not start.', error));

    if (!standalone) {
        window.addEventListener('beforeinstallprompt', event => {
            if (!controls.length) return;
            event.preventDefault();
            installPrompt = event;
            showInstallControls();
        });
        window.addEventListener('appinstalled', () => {
            installPrompt = undefined;
            controls.forEach(control => { control.hidden = true; });
        });
        installButtons.forEach(button => button.addEventListener('click', async () => {
            if (!installPrompt) return;
            const prompt = installPrompt;
            installPrompt = undefined;
            try {
                await prompt.prompt();
            } catch (error) {
                console.warn('AutoCare install prompt could not open.', error);
            } finally {
                showInstallControls();
            }
        }));
        showInstallControls();
    }
}
