// Register the installable app without storing sessions or private Laravel pages.
if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch((error) => {
            if (import.meta.env.DEV) console.warn('GroveMap service worker registration failed:', error);
        });
    });
}

// The install button appears only when the browser offers its install prompt.
let deferredInstallPrompt = null;
const installButton = document.querySelector('[data-grovemap-install]');
if (installButton) {
    const standalone = window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;

    if (!standalone) {
        window.addEventListener('beforeinstallprompt', (event) => {
            event.preventDefault();
            deferredInstallPrompt = event;
            installButton.hidden = false;
        });
        installButton.addEventListener('click', async () => {
            if (!deferredInstallPrompt) return;
            const prompt = deferredInstallPrompt;
            deferredInstallPrompt = null;
            installButton.hidden = true;
            await prompt.prompt();
            await prompt.userChoice;
        });
        window.addEventListener('appinstalled', () => {
            deferredInstallPrompt = null;
            installButton.hidden = true;
        });
    }
}
