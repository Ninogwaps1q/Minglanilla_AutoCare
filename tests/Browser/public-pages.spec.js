import { test, expect } from '@playwright/test';
import { installDirectionsMapStub } from './directions-map-stub.js';

const stubOpenStreetMap = page => page.route('https://www.openstreetmap.org/export/embed.html*', route => route.fulfill({
    contentType: 'text/html',
    body: '<main>OpenStreetMap preview</main>',
}));

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

test('landing navbar remains visible while scrolling between Home, providers, and About', async ({ page }, testInfo) => {
    test.setTimeout(60000);
    await page.goto('/');
    const navigation = page.getByRole('navigation', { name: 'Primary navigation' });
    const isMobile = testInfo.project.name !== 'desktop';
    const toggle = page.getByRole('button', { name: 'Toggle navigation' });
    const menu = page.locator('#publicNavigation');
    await expect(page.locator('#find-shops #maps')).toHaveCount(1);
    await expect(navigation.getByRole('link', { name: 'Maps', exact: true })).toHaveCount(0);
    expect(await page.evaluate(() => document.querySelector('#home').getBoundingClientRect().top >= document.querySelector('.public-navbar').getBoundingClientRect().bottom - 1)).toBe(true);

    await page.evaluate(() => window.scrollTo({ top: 600, behavior: 'instant' }));
    await expect.poll(() => page.evaluate(() => window.scrollY)).toBeGreaterThan(200);
    await expect(navigation).toBeInViewport();
    await expect.poll(() => navigation.evaluate(element => Math.round(element.getBoundingClientRect().top))).toBe(0);

    const providerLink = navigation.locator('a.nav-link[href="#find-shops"]');
    await expect(providerLink).toHaveCount(1);
    await expect(providerLink).toHaveText('Find shops & mechanics');
    await expect(navigation.locator('a.nav-link').filter({ hasText: 'Find a mechanic' })).toHaveCount(0);
    for (const [label, section] of [['Find shops & mechanics', 'find-shops'], ['About', 'about'], ['Home', 'home']]) {
        if (isMobile) {
            await expect(menu).not.toHaveClass(/\bcollapsing\b/);
            await toggle.click();
            await expect(menu).toHaveClass(/\bshow\b/);
        }
        await navigation.getByRole('link', { name: label, exact: true }).click();
        await expect(page).toHaveURL(new RegExp(`#${section}$`));
        await expect(page.locator(`#${section}`)).toBeInViewport();
        if (isMobile) {
            await expect(toggle).toHaveAttribute('aria-expanded', 'false');
            await expect(menu).not.toHaveClass(/\bshow\b|\bcollapsing\b/);
        }
        await expect(navigation).toBeInViewport();
        await expect.poll(() => navigation.evaluate(element => Math.round(element.getBoundingClientRect().top))).toBe(0);
    }
});

test('landing map shows the shop logo only after its pin is clicked', async ({ page }) => {
    test.skip(process.env.BROWSER_ISOLATED !== '1', 'Requires the disposable browser fixture');
    await installDirectionsMapStub(page);
    const fixture = JSON.parse(process.env.BROWSER_FIXTURE);
    await page.goto('/');
    await page.locator('#maps').scrollIntoViewIfNeeded();
    await expect(page.locator('#landing-shop-map .test-google-marker-icon')).toHaveCount(1, { timeout: 15000 });
    await expect(page.locator('#landing-shop-map .test-google-marker-icon')).toHaveAttribute('src', /red-dot\.png$/);
    await expect(page.locator('#landing-shop-map .gm-style-iw')).toHaveCount(0);
    await page.locator('#landing-shop-map .test-google-marker-icon').click();
    const popup = page.locator('#landing-shop-map .gm-style-iw');
    await expect(popup).toContainText('Browser Test AutoCare');
    const logo = popup.locator('img');
    await expect(logo).toHaveAttribute('src', /\/media\/shop-logos\//);
    await expect.poll(() => logo.evaluate(image => image.naturalWidth)).toBeGreaterThan(0);
    await popup.getByRole('link', { name: 'View shop' }).click();
    await expect(page).toHaveURL(new RegExp(`/shops/${fixture.shop}$`));
});

test('live directory popup uses the saved shop logo', async ({ page }, testInfo) => {
    test.skip(process.env.LIVE_GOOGLE_MAPS !== '1', 'Live map check is opt-in');
    test.setTimeout(60000);
    await page.goto('/shops?view=map');
    const providers = JSON.parse(await page.locator('#shop-map').getAttribute('data-shops'));
    const shop = providers.find(provider => provider.provider_type === 'shop');
    await expect.poll(async () => {
        if (await page.locator('#shop-map .provider-map-fallback').count()) return 'fallback';
        return await page.getByTitle(shop.name, { exact: true }).count() ? 'ready' : 'loading';
    }, { timeout: 15000 }).not.toBe('loading');
    if (await page.locator('#shop-map .provider-map-fallback').count()) {
        await expect(page.locator('#shop-map iframe')).toHaveAttribute('src', /openstreetmap\.org\/export\/embed\.html/);
        await expect(page.locator('#shop-map .gm-err-container')).toHaveCount(0);
        await page.locator('#shop-map').screenshot({ path: testInfo.outputPath('live-map-fallback.png') });
        test.skip(true, 'The configured Google Maps key has reached its daily quota');
    }
    await page.getByTitle(shop.name, { exact: true }).dispatchEvent('click');
    await expect(page.locator('#shop-map .shop-map-popup img')).toBeVisible();
    await expect.poll(() => page.locator('#shop-map .gm-style-iw-c').evaluate(element => getComputedStyle(element).backgroundColor)).toBe('rgb(255, 255, 255)');
    await page.locator('#shop-map').screenshot({ path: testInfo.outputPath('live-shop-popup-light.png') });

    await page.locator('[data-theme-toggle]').first().dispatchEvent('click');
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await expect(page.locator('#shop-map .shop-map-popup img')).toBeVisible();
    await expect.poll(() => page.locator('#shop-map .gm-style-iw-c').evaluate(element => getComputedStyle(element).backgroundColor)).toBe('rgb(21, 34, 56)');
    await page.locator('#shop-map').screenshot({ path: testInfo.outputPath('live-shop-popup-dark.png') });
});

test('live fallback map renders OpenStreetMap tiles when Google Maps is blocked', async ({ page }, testInfo) => {
    test.skip(process.env.LIVE_OPENSTREETMAP !== '1', 'Live map check is opt-in');
    test.setTimeout(60000);
    const tileLoaded = page.waitForResponse(response => /openstreetmap\.org\/\d+\/\d+\/\d+\.png/.test(response.url()) && response.status() === 200, { timeout: 20000 });
    await page.route('https://maps.googleapis.com/maps/api/js*', route => route.abort());
    await page.goto('/shops?view=map');
    const iframe = page.locator('#shop-map iframe');
    await expect(iframe).toBeVisible({ timeout: 15000 });
    await iframe.scrollIntoViewIfNeeded();
    const map = page.frameLocator('#shop-map iframe');
    await expect(map.locator('.maplibregl-canvas')).toBeVisible({ timeout: 20000 });
    await expect(map.locator('.maplibregl-marker')).toBeVisible();
    await tileLoaded;
    await page.locator('#shop-map').screenshot({ path: testInfo.outputPath('live-openstreetmap-fallback.png') });
    await page.locator('[data-theme-toggle]').first().dispatchEvent('click');
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await expect(iframe).toBeVisible();
    await page.locator('#shop-map').screenshot({ path: testInfo.outputPath('live-openstreetmap-fallback-dark.png') });
});

test('landing map shows a mechanic logo after its service-area pin is clicked', async ({ page }) => {
    test.skip(process.env.BROWSER_ISOLATED !== '1', 'Requires the disposable browser fixture');
    await installDirectionsMapStub(page);
    const fixture = JSON.parse(process.env.BROWSER_FIXTURE);
    await page.route('**/api/shops', async route => {
        const response = await route.fetch();
        const payload = await response.json();
        payload.data.push({
            id: 99999,
            name: 'Browser Mobile Mechanic',
            provider_type: 'independent_mechanic',
            latitude: 10.2,
            longitude: 123.8,
            logo_url: `/media/${fixture.logo}`,
        });
        await route.fulfill({ response, json: payload });
    });
    await page.goto('/');
    await page.locator('#maps').scrollIntoViewIfNeeded();
    const mechanicMarker = page.locator('#landing-shop-map .test-google-marker-icon[title="Browser Mobile Mechanic approximate service area"]');
    await expect(mechanicMarker).toHaveAttribute('src', /orange-dot\.png$/);
    await expect(page.locator('#landing-shop-map .gm-style-iw')).toHaveCount(0);
    await mechanicMarker.click();
    const popup = page.locator('#landing-shop-map .gm-style-iw');
    await expect(popup).toContainText('Approximate service area');
    await expect(popup.locator('img')).toHaveAttribute('src', `/media/${fixture.logo}`);
});

test('landing map failure keeps the shop directory reachable', async ({ page }) => {
    await stubOpenStreetMap(page);
    await page.route('https://maps.googleapis.com/maps/api/js*', route => route.abort());
    await page.goto('/');
    await page.locator('#maps').scrollIntoViewIfNeeded();
    await expect(page.locator('#landing-shop-map iframe')).toBeVisible({ timeout: 15000 });
    await expect(page.locator('#maps').getByRole('link', { name: 'Open full provider map' })).toBeVisible();
});

test('landing and directory show provider map previews when Google Maps rejects the key', async ({ page }) => {
    test.skip(process.env.BROWSER_ISOLATED !== '1', 'Requires the disposable browser fixture');
    await stubOpenStreetMap(page);
    await page.route('**/api/shops', async route => {
        const response = await route.fetch();
        const payload = await response.json();
        payload.data.push({ id: 99999, name: 'Browser Mobile Mechanic', provider_type: 'independent_mechanic', latitude: 10.2, longitude: 123.8 });
        await route.fulfill({ response, json: payload });
    });
    await page.route('https://maps.googleapis.com/maps/api/js*', route => route.fulfill({
        contentType: 'application/javascript',
        body: 'window.gm_authFailure?.();',
    }));

    await page.goto('/');
    await page.locator('#maps').scrollIntoViewIfNeeded();
    await expect(page.locator('#landing-shop-map iframe')).toHaveAttribute('src', /openstreetmap\.org\/export\/embed\.html/);
    await expect(page.locator('#landing-map-status')).toContainText('OpenStreetMap preview');
    await page.locator('#landing-shop-map select').selectOption('99999');
    await expect(page.locator('#landing-shop-map iframe')).toHaveAttribute('src', /marker=10\.2%2C123\.8/);
    await expect(page.locator('#landing-shop-map .provider-map-fallback-details')).toContainText('Browser Mobile Mechanic');
    await expect(page.locator('#landing-shop-map .provider-map-fallback-details')).toContainText('Approximate service area');
    await page.locator('#landing-shop-map select').focus();
    await expect(page.locator('#landing-shop-map select')).toBeFocused();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1)).toBe(true);
    await expect(page.locator('#maps').getByRole('link', { name: 'Open full provider map' })).toBeVisible();

    await page.goto('/shops?view=map');
    await expect(page.locator('#shop-map iframe')).toBeVisible();
    await expect(page.locator('#shop-map .provider-map-fallback-details')).toContainText('Browser Test AutoCare');
    await expect(page.locator('#shop-results')).toContainText('Browser Test AutoCare');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1)).toBe(true);
});

test('landing provider search opens the shared directory with the query', async ({ page }) => {
    await page.goto('/');
    await page.locator('#find-shops').getByLabel('Shop, mechanic, or city').fill('Browser Test AutoCare');
    await page.locator('#find-shops').getByRole('button', { name: 'Find providers' }).click();

    await expect(page).toHaveURL(/\/shops\?search=Browser(?:\+|%20)Test/);
    await expect(page.getByLabel('Provider or city')).toHaveValue('Browser Test AutoCare');
});

test('landing area choices carry through to the nationwide directory', async ({ page }) => {
    test.skip(process.env.BROWSER_ISOLATED !== '1', 'Requires the disposable browser fixture');
    test.setTimeout(90000);
    await installDirectionsMapStub(page);
    await page.goto('/');
    await page.getByText('Filter by region, province, or city', { exact: true }).click();
    const region = page.getByLabel('Region', { exact: true });
    await region.selectOption({ label: 'Davao Region' });
    await region.focus();
    await page.keyboard.press('Tab');
    const province = page.getByLabel('Province or area');
    await expect(province).toBeFocused();
    await province.selectOption({ label: 'Davao del Sur' });
    await page.getByLabel('City / municipality').selectOption({ label: 'City of Digos' });
    await page.getByRole('button', { name: 'Find providers' }).click();
    await expect(page).toHaveURL(/\/shops\?.*municipality_id=\d+/);
    await expect(page.locator('#shop-results')).toContainText('Browser Test AutoCare');
    await expect(page.getByLabel('City / municipality').locator('option:checked')).toHaveText('City of Digos');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1)).toBe(true);
});

test('provider results scroll sideways with the keyboard when space is limited', async ({ page }) => {
    test.skip(process.env.BROWSER_ISOLATED !== '1', 'Requires the disposable browser fixture');
    await page.goto('/shops');
    const results = page.getByRole('region', { name: 'Shop and mechanic results' });
    await expect(results).toBeVisible();
    await results.evaluate(element => { element.style.width = '10rem'; });
    await results.focus();
    await expect(results).toBeFocused();
    await page.keyboard.press('ArrowRight');
    await expect.poll(() => results.evaluate(element => element.scrollLeft)).toBeGreaterThan(0);
});

test('provider directory stays within the viewport in light and dark modes', async ({ page }, testInfo) => {
    for (const theme of ['light', 'dark']) {
        await page.goto('/shops');
        await page.evaluate(selectedTheme => localStorage.setItem('autocare-theme', selectedTheme), theme);
        await page.reload();
        await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
        const results = page.getByRole('region', { name: 'Shop and mechanic results' });
        await expect(results).toBeVisible();
        expect(await results.evaluate(element => getComputedStyle(element).overflowX)).toBe('auto');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1)).toBe(true);
        if (testInfo.project.name !== 'desktop') {
            await results.scrollIntoViewIfNeeded();
            await page.screenshot({ path: testInfo.outputPath(`providers-${theme}.png`) });
        }
    }
});

test('social sign-in choices stay readable on login and registration', async ({ page }, testInfo) => {
    await page.goto('/login');
    await expect(page.getByRole('link', { name: 'Google' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Facebook' })).toBeVisible();
    await expect(page.getByText('New social sign-ins create customer accounts.')).toBeVisible();
    await page.screenshot({ path: testInfo.outputPath('social-login-light.png') });

    await page.evaluate(() => localStorage.setItem('autocare-theme', 'dark'));
    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await expect(page.getByRole('link', { name: 'Google' })).toBeVisible();
    await page.screenshot({ path: testInfo.outputPath('social-login-dark.png') });

    await page.goto('/register');
    await expect(page.getByRole('link', { name: 'Google' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Facebook' })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1)).toBe(true);
});

test('password visibility control and error recovery work in both themes', async ({ page }, testInfo) => {
    test.setTimeout(90000);
    await page.goto('/login');
    for (const width of [360, 768, 1440]) {
        await page.setViewportSize({ width, height: 850 });
        for (const theme of ['light', 'dark']) {
            await page.evaluate(value => localStorage.setItem('autocare-theme', value), theme);
            await page.goto('/login');
            await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
            await page.locator('#password').fill('test-value-not-submitted');
            const toggle = page.getByRole('button', { name: 'Show password', exact: true });
            await toggle.focus();
            await expect(toggle).toBeFocused();
            await page.keyboard.press('Space');
            await expect(page.locator('#password')).toHaveAttribute('type', 'text');
            await page.getByRole('button', { name: 'Hide password', exact: true }).click();
            await expect(page.locator('#password')).toHaveAttribute('type', 'password');
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1)).toBe(true);
            await page.screenshot({ path: testInfo.outputPath('sign-in-' + width + '-' + theme + '.png') });

            expect((await page.goto('/browser-test-missing-page')).status()).toBe(404);
            await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
            const home = page.getByRole('link', { name: 'Return home' });
            await home.focus();
            await expect(home).toBeFocused();
            await page.screenshot({ path: testInfo.outputPath('recovery-' + width + '-' + theme + '.png') });
        }
    }
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
    test.skip(process.env.BROWSER_ISOLATED !== '1', 'Requires the disposable browser fixture');
    await installDirectionsMapStub(page);
    await context.grantPermissions(['geolocation']);
    await context.setGeolocation({ latitude: 6.7493, longitude: 125.3552 });
    await page.goto('/map');
    await expect(page).toHaveURL(/\/shops\?view=map#shop-map$/);
    await expect(page.locator('#shop-map .test-google-marker-icon')).toHaveCount(1);
    await page.getByRole('button', { name: 'Use my location', exact: true }).click();
    await expect(page.locator('#location-status')).toHaveText('Showing distances from your location.');
    await expect(page.locator('#latitude')).toHaveValue('6.7493');
    await expect(page.locator('#longitude')).toHaveValue('125.3552');
});

test('nearby shop search filters the verified shop and shows its distance on the map', async ({ page, context }) => {
    test.skip(process.env.BROWSER_ISOLATED !== '1', 'Requires the disposable browser fixture');
    await installDirectionsMapStub(page);
    const fixture = JSON.parse(process.env.BROWSER_FIXTURE);
    await context.grantPermissions(['geolocation']);
    await context.setGeolocation({ latitude: 6.7493, longitude: 125.3552 });
    await page.goto('/shops');
    await page.getByLabel('Service', { exact: true }).selectOption(String(fixture.service));
    await page.getByRole('button', { name: 'Find providers' }).click();
    await expect(page).toHaveURL(new RegExp(`service=${fixture.service}`));
    await expect(page.locator('#shop-results article')).toHaveCount(1);
    await expect(page.locator('#shop-results')).toContainText('Browser Test AutoCare');
    await page.getByRole('button', { name: 'Use my location', exact: true }).click();
    await expect(page.locator('#shop-results')).toContainText('0.0 km away');
    await page.getByRole('link', { name: 'Map', exact: true }).click();
    await expect(page.locator('#shop-map .test-google-marker-icon')).toHaveCount(2);
});

test('a shop pin opens its matching logo only after clicking', async ({ page }) => {
    test.skip(process.env.BROWSER_ISOLATED !== '1', 'Requires the disposable browser fixture');
    await installDirectionsMapStub(page);
    const fixture = JSON.parse(process.env.BROWSER_FIXTURE);
    expect((await page.request.get(`/media/${fixture.logo}`)).status()).toBe(200);

    await page.goto(`/shops/${fixture.shop}`);
    const currentLogo = await page.locator('.shop-public-logo').getAttribute('src');
    await page.goto('/shops?view=map');

    const popup = page.locator('#shop-map .gm-style-iw');
    const marker = page.locator('#shop-map .test-google-marker-icon');
    await expect(marker).toHaveAttribute('src', /red-dot\.png$/);
    await expect(popup).toHaveCount(0);
    await marker.click();
    await expect(popup).toContainText('Browser Test AutoCare', { timeout: 10000 });
    const logo = popup.locator('img');
    await expect(logo).toHaveAttribute('src', currentLogo);
    await expect.poll(() => logo.evaluate(image => image.naturalWidth)).toBeGreaterThan(0);
    await page.evaluate(() => localStorage.setItem('autocare-theme', 'dark'));
    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await expect(popup).toHaveCount(0);
    await marker.click();
    await expect(page.locator('#shop-map .gm-style-iw img')).toHaveAttribute('src', currentLogo);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1)).toBe(true);
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

test('shop directory remains usable when the map provider is unavailable', async ({ page }, testInfo) => {
    test.skip(process.env.BROWSER_ISOLATED !== '1', 'Requires the disposable browser fixture');
    test.setTimeout(90000);
    await stubOpenStreetMap(page);
    await page.route('https://maps.googleapis.com/maps/api/js*', route => route.abort());
    await page.goto('/shops');
    await expect(page.locator('#shop-map')).toBeHidden();
    await page.getByRole('link', { name: 'Map', exact: true }).click();
    await expect(page.locator('#shop-map iframe')).toBeVisible();
    await page.getByRole('link', { name: 'List', exact: true }).click();
    await expect(page.locator('#shop-results')).toContainText('Browser Test AutoCare');
    await expect(page.locator('#shop-results').getByRole('link', { name: 'View shop' })).toBeVisible();
    for (const [width, theme] of [[360, 'light'], [360, 'dark'], [768, 'light'], [768, 'dark'], [1440, 'light'], [1440, 'dark']]) {
        await page.setViewportSize({ width, height: 850 });
        await page.evaluate(value => localStorage.setItem('autocare-theme', value), theme);
        await page.reload();
        await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
        const mapLink = page.getByRole('link', { name: 'Map', exact: true });
        await mapLink.focus();
        await expect(mapLink).toBeFocused();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1)).toBe(true);
        await page.screenshot({ path: testInfo.outputPath('directory-' + width + '-' + theme + '.png'), fullPage: true });
    }
});

test('directory filter submits and can be cleared', async ({ page }) => {
    await page.goto('/shops');
    await page.getByLabel('Provider or city').fill('no-such-shop-browser-check');
    await page.getByRole('button', { name: 'Find providers', exact: true }).click();
    await expect(page).toHaveURL(/search=no-such-shop-browser-check/);
    await expect(page.getByRole('heading', { name: 'No providers match these filters' })).toBeVisible();
    await page.getByRole('link', { name: 'Clear filters', exact: true }).click();
    await expect(page.getByLabel('Provider or city')).toHaveValue('');
});

test('public shop profile guides customers to booking and messaging', async ({ page }) => {
    test.skip(process.env.BROWSER_ISOLATED !== '1', 'Requires the disposable browser fixture');
    await installDirectionsMapStub(page);
    await page.context().grantPermissions(['geolocation']);
    await page.context().setGeolocation({ latitude: 6.7493, longitude: 125.3552 });
    const fixture = JSON.parse(process.env.BROWSER_FIXTURE);
    const response = await page.goto('/shops/' + fixture.shop);
    expect(response.status()).toBe(200);
    await expect(page.getByRole('heading', { name: 'Customer reviews' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Sign in to book this provider' })).toBeVisible();
    await page.getByRole('button', { name: 'Get directions' }).click();
    await expect(page.getByText('Turn right onto Test Road')).toBeVisible();
    await expect(page.locator('.driving-directions-summary')).toContainText('2.5 km');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1)).toBe(true);
    await page.evaluate(() => localStorage.setItem('autocare-theme', 'dark'));
    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await page.getByRole('button', { name: 'Get directions' }).click();
    await expect(page.getByText('Turn right onto Test Road')).toBeVisible();

    await page.getByRole('link', { name: 'Sign in to book this provider' }).click();
    await expect(page).toHaveURL(/\/login$/);
    await page.getByLabel('Email address').fill('customer@browser.example.test');
    await page.getByLabel('Password', { exact: true }).fill(fixture.password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page.locator('#shop_id')).toHaveValue(String(fixture.shop));
    await page.goto('/shops/' + fixture.shop);
    await expect(page.locator('#main-content').getByRole('link', { name: 'Book a service' })).toBeVisible();
    await expect(page.locator('#main-content').getByRole('link', { name: 'Message shop' })).toBeVisible();
    await page.locator('#main-content').getByRole('link', { name: 'Book a service' }).click();
    await expect(page.locator('#shop_id')).toHaveValue(String(fixture.shop));
});

test('shop directions explain a denied location permission', async ({ page }, testInfo) => {
    test.skip(process.env.BROWSER_ISOLATED !== '1' || testInfo.project.name !== 'desktop', 'The error state needs one browser check');
    await installDirectionsMapStub(page);
    await page.addInitScript(() => {
        Object.defineProperty(navigator, 'geolocation', {
            configurable: true,
            value: { getCurrentPosition: (_success, error) => error({ code: 1 }) },
        });
    });
    const fixture = JSON.parse(process.env.BROWSER_FIXTURE);
    await page.goto('/shops/' + fixture.shop);
    await page.getByRole('button', { name: 'Get directions' }).click();
    await expect(page.locator('.driving-directions-status')).toContainText('Location access was denied');
});

test('shop directions explain when no driving route is found', async ({ page }, testInfo) => {
    test.skip(process.env.BROWSER_ISOLATED !== '1' || testInfo.project.name !== 'desktop', 'The error state needs one browser check');
    await installDirectionsMapStub(page, 'empty');
    await page.context().grantPermissions(['geolocation']);
    await page.context().setGeolocation({ latitude: 6.7493, longitude: 125.3552 });
    const fixture = JSON.parse(process.env.BROWSER_FIXTURE);
    await page.goto('/shops/' + fixture.shop);
    await page.getByRole('button', { name: 'Get directions' }).click();
    await expect(page.locator('.driving-directions-status')).toContainText('No driving route was found');
});

test('shop directions explain when Google Maps cannot load', async ({ page }, testInfo) => {
    test.skip(process.env.BROWSER_ISOLATED !== '1' || testInfo.project.name !== 'desktop', 'The error state needs one browser check');
    await page.route('https://maps.googleapis.com/maps/api/js*', route => route.abort());
    const fixture = JSON.parse(process.env.BROWSER_FIXTURE);
    await page.goto('/shops/' + fixture.shop);
    await expect(page.locator('.driving-directions-status')).toContainText('Map could not load');
    await expect(page.locator('.driving-directions-map')).toContainText('Map could not load');
});

test('mobile public navigation opens and closes', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === 'desktop', 'Mobile navigation only');
    await page.goto('/');
    const toggle = page.getByRole('button', { name: 'Toggle navigation' });
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator('#publicNavigation')).toHaveClass(/\bshow\b/);
    await expect(page.getByRole('navigation', { name: 'Primary navigation' }).getByRole('link', { name: 'Find shops & mechanics', exact: true })).toBeVisible();
    await toggle.click();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(page.locator('#publicNavigation')).not.toHaveClass(/\bshow\b/);
});
