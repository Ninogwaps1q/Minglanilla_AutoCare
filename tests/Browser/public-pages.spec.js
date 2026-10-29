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
        if (path === '/shops') {
            await page.screenshot({ path: testInfo.outputPath('shop-directory.png'), fullPage: true });
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
    const homeNavItem = page.locator('.public-nav-home');
    const themeToggle = homeNavItem.locator('[data-theme-toggle]');
    await expect(homeNavItem.getByRole('link', { name: 'Home' })).toBeVisible();
    await expect(themeToggle).toBeVisible();
    expect(await homeNavItem.evaluate(item => item.firstElementChild?.hasAttribute('data-theme-toggle'))).toBe(true);
    await expect(page.locator('.public-footer [data-theme-toggle]')).toHaveCount(0);
    await homeNavItem.screenshot({ path: testInfo.outputPath('theme-beside-home.png') });
    await themeToggle.click();
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
    await page.locator('.public-nav-home [data-theme-toggle]').click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    expect(await page.evaluate(() => localStorage.getItem('autocare-theme'))).toBe('light');
});

test('nearby map uses permitted browser coordinates', async ({ page, context }) => {
    await context.grantPermissions(['geolocation']);
    await context.setGeolocation({ latitude: 10.2443, longitude: 123.7898 });
    await page.goto('/map');
    await expect(page).toHaveURL(/\/shops#shop-map$/);
    await expect(page.locator('#shop-map .leaflet-control-zoom')).toBeVisible();
    await page.getByRole('button', { name: 'Use my location', exact: true }).click();
    await expect(page.locator('#location-status')).toHaveText('Showing distances from your location.');
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
    await page.getByRole('button', { name: 'Find shops' }).click();
    await expect(page).toHaveURL(new RegExp(`service=${fixture.service}`));
    await expect(page.locator('#shop-results article')).toHaveCount(1);
    await expect(page.locator('#shop-results')).toContainText('Browser Test AutoCare');
    await page.getByRole('button', { name: 'Use my location', exact: true }).click();
    await expect(page.locator('#shop-results')).toContainText('0.0 km away');
    await expect(page.locator('.leaflet-marker-icon')).toHaveCount(1);
});

test('a shop location opens its matching logo on the map', async ({ page }) => {
    test.skip(process.env.BROWSER_ISOLATED !== '1', 'Requires the disposable browser fixture');
    const fixture = JSON.parse(process.env.BROWSER_FIXTURE);

    await page.goto(`/shops/${fixture.shop}`);
    const currentLogo = await page.locator('.shop-public-logo').getAttribute('src');
    await page.getByRole('link', { name: 'View on map' }).click();
    await expect(page).toHaveURL(new RegExp(`selected=${fixture.shop}`));

    const popup = page.locator('#shop-map .leaflet-popup-content');
    await expect(popup).toContainText('Browser Test AutoCare', { timeout: 10000 });
    const logo = popup.locator('img');
    await expect(logo).toHaveAttribute('src', currentLogo);
    await expect.poll(() => logo.evaluate(image => image.naturalWidth)).toBeGreaterThan(0);

    await page.locator('#shop-map .leaflet-popup-close-button').click();
    await page.locator('#shop-map .leaflet-marker-icon').click();
    await expect(popup).toContainText('Browser Test AutoCare');
    await expect(popup.locator('img')).toBeVisible();
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

test('shop directory remains usable when the map provider is unavailable', async ({ page }) => {
    test.skip(process.env.BROWSER_ISOLATED !== '1', 'Requires the disposable browser fixture');
    await page.route('https://unpkg.com/leaflet@1.9.4/dist/*', route => route.abort());
    await page.goto('/shops');
    await expect(page.locator('#shop-map')).toContainText('The map could not load.');
    await expect(page.locator('#shop-results')).toContainText('Browser Test AutoCare');
    await expect(page.getByRole('link', { name: 'View shop' })).toBeVisible();
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

test('public shop profile guides customers to booking and messaging', async ({ page }) => {
    test.skip(process.env.BROWSER_ISOLATED !== '1', 'Requires the disposable browser fixture');
    const fixture = JSON.parse(process.env.BROWSER_FIXTURE);
    const response = await page.goto('/shops/' + fixture.shop);
    expect(response.status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'Customer reviews' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Sign in to book or message' })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1)).toBe(true);

    await page.goto('/login');
    await page.getByLabel('Email address').fill('customer@browser.example.test');
    await page.getByLabel('Password', { exact: true }).fill(fixture.password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await page.goto('/shops/' + fixture.shop);
    await expect(page.locator('#main-content').getByRole('link', { name: 'Book a service' })).toBeVisible();
    await expect(page.locator('#main-content').getByRole('link', { name: 'Message shop' })).toBeVisible();
    await page.locator('#main-content').getByRole('link', { name: 'Book a service' }).click();
    await expect(page.locator('#shop_id')).toHaveValue(String(fixture.shop));
});

test('mobile public navigation opens and closes', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'desktop', 'Mobile navigation only');
    await page.goto('/');
    const toggle = page.getByRole('button', { name: 'Toggle navigation' });
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator('#publicNavigation')).toHaveClass(/\bshow\b/);
    await expect(page.getByRole('navigation', { name: 'Primary navigation' }).getByRole('link', { name: 'Find a shop', exact: true })).toBeVisible();
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(page.locator('#publicNavigation')).not.toHaveClass(/\bshow\b/);
});
