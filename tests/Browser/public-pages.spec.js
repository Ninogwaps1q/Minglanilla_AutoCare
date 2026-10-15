import { test, expect } from '@playwright/test';

for (const path of ['/', '/login', '/register', '/forgot-password', '/shops', '/map']) {
    test('public page renders without overflow or JavaScript exceptions: ' + path, async ({ page }, testInfo) => {
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        const response = await page.goto(path);
        expect(response.status()).toBe(200);
        await expect(page.locator('main')).toBeVisible();
        await expect(page.locator('h1')).toBeVisible();
        await page.evaluate(() => document.fonts.ready);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1)).toBe(true);
        expect(errors).toEqual([]);
        if (path === '/') {
            await page.screenshot({ path: testInfo.outputPath('home.png'), fullPage: true });
        }
    });
}

test('password visibility control works', async ({ page }) => {
    await page.goto('/login');
    await page.locator('#password').fill('test-value-not-submitted');
    await page.getByRole('button', { name: 'Show password', exact: true }).click();
    await expect(page.locator('#password')).toHaveAttribute('type', 'text');
    await page.getByRole('button', { name: 'Hide password', exact: true }).click();
    await expect(page.locator('#password')).toHaveAttribute('type', 'password');
});

test('color theme can be changed and remains selected across pages', async ({ page }, testInfo) => {
    await page.goto('/');
    if (testInfo.project.name !== 'desktop') {
        await page.getByRole('button', { name: 'Toggle navigation' }).click();
    }

    await page.getByRole('button', { name: 'Switch to dark mode' }).click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await expect(page.locator('html')).toHaveAttribute('data-bs-theme', 'dark');
    await expect(page.getByRole('button', { name: 'Switch to light mode' })).toBeVisible();
    expect(await page.evaluate(() => localStorage.getItem('autocare-theme'))).toBe('dark');
    await expect.poll(() => page.evaluate(() => getComputedStyle(document.body).backgroundColor)).toBe('rgb(13, 23, 40)');
    expect(await page.evaluate(() => getComputedStyle(document.querySelector('.hero-route-card')).backgroundColor)).toBe('rgb(21, 34, 56)');

    await page.goto('/login');
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    if (testInfo.project.name !== 'desktop') {
        await page.getByRole('button', { name: 'Toggle navigation' }).click();
    }
    await page.getByRole('button', { name: 'Switch to light mode' }).click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    expect(await page.evaluate(() => localStorage.getItem('autocare-theme'))).toBe('light');
});

test('nearby map uses permitted browser coordinates', async ({ page, context }) => {
    await context.grantPermissions(['geolocation']);
    await context.setGeolocation({ latitude: 10.2443, longitude: 123.7898 });
    await page.goto('/map');
    await expect(page.locator('#shop-map .leaflet-control-zoom')).toBeVisible();
    await page.getByRole('button', { name: 'Use my location', exact: true }).click();
    await expect(page.locator('#location-status')).toHaveText('Results are sorted by distance.');
    await expect(page.locator('#latitude')).toHaveValue('10.2443');
    await expect(page.locator('#longitude')).toHaveValue('123.7898');
});

test('nearby shop search filters the verified shop and shows its distance on the map', async ({ page, context }) => {
    test.skip(process.env.BROWSER_ISOLATED !== '1', 'Requires the disposable browser fixture');
    const fixture = JSON.parse(process.env.BROWSER_FIXTURE);
    await context.grantPermissions(['geolocation']);
    await context.setGeolocation({ latitude: 10.2443, longitude: 123.7898 });
    await page.goto('/map');
    await page.getByLabel('Service', { exact: true }).selectOption(String(fixture.service));
    await page.getByRole('button', { name: 'Apply filters' }).click();
    await expect(page).toHaveURL(new RegExp(`service=${fixture.service}`));
    await expect(page.locator('#shop-results article')).toHaveCount(1);
    await expect(page.locator('#shop-results')).toContainText('Browser Test AutoCare');
    await page.getByRole('button', { name: 'Use my location', exact: true }).click();
    await expect(page.locator('#shop-results')).toContainText('0.0 km away');
    await expect(page.locator('.leaflet-marker-icon')).toHaveCount(1);
});

test('nearby map explains denied location access', async ({ page }) => {
    await page.addInitScript(() => {
        Object.defineProperty(navigator, 'geolocation', {
            value: { getCurrentPosition: (_success, failure) => failure({ code: 1 }) },
        });
    });
    await page.goto('/map');
    await page.getByRole('button', { name: 'Use my location', exact: true }).click();
    await expect(page.locator('#location-status')).toContainText('We could not access your location.');
    await expect(page.getByRole('button', { name: 'Use my location', exact: true })).toBeEnabled();
});

test('directory filter submits and can be cleared', async ({ page }) => {
    await page.goto('/shops');
    await page.getByLabel('Shop name').fill('no-such-shop-browser-check');
    await page.getByRole('button', { name: 'Find shops', exact: true }).click();
    await expect(page).toHaveURL(/search=no-such-shop-browser-check/);
    await expect(page.getByRole('heading', { name: 'No shops match these filters' })).toBeVisible();
    await page.getByRole('link', { name: 'Clear filters', exact: true }).click();
    await expect(page.getByLabel('Shop name')).toHaveValue('');
});

test('mobile public navigation opens and closes', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'desktop', 'Mobile navigation only');
    await page.goto('/');
    const toggle = page.getByRole('button', { name: 'Toggle navigation' });
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator('#publicNavigation')).toHaveClass(/\bshow\b/);
    await expect(page.getByRole('navigation', { name: 'Primary navigation' }).getByRole('link', { name: 'Shop directory', exact: true })).toBeVisible();
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(page.locator('#publicNavigation')).not.toHaveClass(/\bshow\b/);
});
