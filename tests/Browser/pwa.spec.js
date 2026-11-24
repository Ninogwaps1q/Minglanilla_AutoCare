import { test, expect } from '@playwright/test';

test.skip(() => process.env.BROWSER_ISOLATED !== '1', 'Requires the isolated browser server');

test('installs the offline worker and keeps private pages out of its cache', async ({ page, context }, testInfo) => {
    test.setTimeout(60000);
    await page.goto('/login');
    await page.evaluate(() => navigator.serviceWorker.ready);
    await expect.poll(() => page.evaluate(() => Boolean(navigator.serviceWorker.controller))).toBe(true);

    const fixture = JSON.parse(process.env.BROWSER_FIXTURE);
    await page.getByLabel('Email address').fill('customer@browser.example.test');
    await page.getByLabel('Password', { exact: true }).fill(fixture.password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/dashboard$/);
    await expect(page.locator('link[rel=manifest]')).toHaveAttribute('href', '/manifest.webmanifest');
    const chrome = await context.newCDPSession(page);
    const { installabilityErrors } = await chrome.send('Page.getInstallabilityErrors');
    expect(installabilityErrors.filter(error => error.errorId !== 'in-incognito')).toEqual([]);
    await chrome.detach();
    await page.evaluate(() => fetch('/api/shops'));

    const cachedPaths = await page.evaluate(async () => {
        const names = await caches.keys();
        const requests = await Promise.all(names.map(async name => (await caches.open(name)).keys()));
        return requests.flat().map(request => new URL(request.url).pathname).sort();
    });
    expect(cachedPaths).toEqual(['/icons/icon-192.png', '/icons/icon-512.png', '/icons/icon-maskable-512.png', '/offline.html']);

    await context.setOffline(true);
    await page.reload();
    await expect(page.getByRole('heading', { name: "You're offline" })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Return to AutoCare home' })).toBeVisible();
    await page.keyboard.press('Tab');
    await expect(page.getByRole('link', { name: 'Return to AutoCare home' })).toBeFocused();
    await page.screenshot({ path: testInfo.outputPath('offline.png') });
});

test('opens a supported install prompt from the public page', async ({ page }) => {
    await page.goto('/');
    await page.evaluate(() => {
        const event = new Event('beforeinstallprompt', { cancelable: true });
        event.prompt = () => { document.body.dataset.installPromptOpened = 'true'; };
        window.dispatchEvent(event);
    });
    const install = page.getByRole('button', { name: 'Install app', exact: true });
    await install.focus();
    await expect(install).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.locator('body')).toHaveAttribute('data-install-prompt-opened', 'true');
    await expect(install).toBeHidden();
});

test('shows the Add to Home Screen hint on iOS in dark mode', async ({ page }) => {
    await page.addInitScript(() => {
        Object.defineProperty(navigator, 'platform', { value: 'iPhone' });
        Object.defineProperty(navigator, 'userAgent', { value: 'iPhone' });
    });
    await page.emulateMedia({ colorScheme: 'dark' });
    await page.goto('/');
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await expect(page.getByText('On iPhone or iPad, tap Share, then Add to Home Screen.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Install app', exact: true })).toBeHidden();
});
