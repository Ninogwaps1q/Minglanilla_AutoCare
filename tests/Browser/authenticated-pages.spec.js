import { test, expect } from '@playwright/test';

test.skip(() => process.env.BROWSER_ISOLATED !== '1', 'Requires disposable browser database');

async function signIn(page, role) {
    const fixture = JSON.parse(process.env.BROWSER_FIXTURE);
    await page.goto('/login');
    await page.getByLabel('Email address').fill(role + '@browser.example.test');
    await page.getByLabel('Password', { exact: true }).fill(fixture.password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/dashboard$/);
    return fixture;
}

const pages = {
    customer: ['dashboard', 'customer/vehicles', 'customer/appointments', 'customer/appointments/create', 'customer/quotations', 'customer/repairs', 'customer/invoices', 'customer/reminders', 'customer/messages', 'customer/reviews', 'customer/notifications', 'customer/inspections', 'customer/emergency-requests', 'customer/ai-assistant', 'customer/profile', 'customer/favorites', 'customer/service-history'],
    shop_owner: ['shop/dashboard', 'shop/profile', 'shop/services', 'shop/appointments', 'shop/repair-jobs', 'shop/quotations', 'shop/invoices', 'shop/inventory', 'shop/technicians', 'shop/messages', 'shop/reviews', 'shop/reports', 'shop/emergency-requests', 'shop/notifications'],
    admin: ['admin/dashboard', 'admin/shops', 'admin/users', 'admin/services', 'admin/service-categories', 'admin/barangays', 'admin/reviews', 'admin/reports', 'admin/activity-logs', 'admin/monitoring'],
};

for (const [role, paths] of Object.entries(pages)) {
    test(role + ' pages and navigation work at the configured viewport', async ({ page }, testInfo) => {
        test.setTimeout(120000);
        await signIn(page, role);
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        for (const path of paths) {
            const response = await page.goto('/' + path);
            expect(response.status(), path).toBe(200);
            await expect(page.locator('h1'), path).toBeVisible();
            await expect(page.locator('#portalNavigation'), path).toBeAttached();
            await page.evaluate(() => document.fonts.ready);
            const layout = await page.evaluate(() => ({
                viewport: document.documentElement.clientWidth,
                page: document.documentElement.scrollWidth,
                ancestors: [...document.querySelectorAll('.portal-workspace, .portal-content, .surface, .table-responsive')]
                    .slice(0, 8).map(element => ({ tag: element.className, left: Math.round(element.getBoundingClientRect().left),
                        right: Math.round(element.getBoundingClientRect().right), width: element.scrollWidth,
                        overflow: getComputedStyle(element).overflowX })),
                overflowing: [...document.querySelectorAll('main, main *')]
                    .filter(element => element.getBoundingClientRect().right > document.documentElement.clientWidth + 1)
                    .slice(0, 8).map(element => `${element.tagName}.${element.className}=${Math.round(element.getBoundingClientRect().right)}`),
            }));
            expect(layout.page, `${path}: ${JSON.stringify(layout)}`).toBeLessThanOrEqual(layout.viewport + 1);
            if (path.endsWith('/reports')) {
                const charts = page.locator('[data-report-chart]');
                expect(await charts.count()).toBeGreaterThan(0);
                for (const chart of await charts.all()) {
                    await expect(chart).toBeVisible();
                    await expect.poll(() => chart.evaluate(canvas => [...canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height).data].some(value => value > 0))).toBe(true);
                }
            }
        }
        if (testInfo.project.name !== 'desktop') {
            await page.getByRole('button', { name: 'Open navigation' }).click();
            await expect(page.locator('#portalNavigation')).toHaveClass(/\bshow\b/);
            await page.getByRole('button', { name: 'Close navigation' }).click();
            await expect(page.locator('#portalNavigation')).not.toHaveClass(/\bshow\b/);
        }
        expect(errors).toEqual([]);
        await page.screenshot({ path: testInfo.outputPath(role + '.png'), fullPage: true });
    });
}

test('customer creates a vehicle and opens its private passport', async ({ page }, testInfo) => {
    await signIn(page, 'customer');
    const plate = 'E2E-' + testInfo.project.name;
    await page.goto('/customer/vehicles/create');
    for (const [label, value] of [['Plate number', plate], ['Current mileage', '25000'], ['Brand', 'Toyota'], ['Model', 'Vios'], ['Year', '2022']]) {
        await page.getByLabel(label, { exact: true }).fill(value);
    }
    await page.locator('#photo').setInputFiles({
        name: 'browser-vehicle.png',
        mimeType: 'image/png',
        buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9JQqkAAAAASUVORK5CYII=', 'base64'),
    });
    await expect(page.locator('img[data-image-preview]')).toBeVisible();
    expect(await page.locator('img[data-image-preview]').evaluate(image => image.complete && image.naturalWidth > 0)).toBe(true);
    await expect(page.locator('.file-selection')).toContainText('Save to publish this image');
    await page.getByRole('button', { name: 'Save vehicle' }).click();
    await expect(page).toHaveURL(/customer\/vehicles$/);
    await page.reload();
    const vehicleCard = page.locator('article').filter({ hasText: plate });
    await expect(vehicleCard.getByText(plate, { exact: true })).toBeVisible();
    expect(await vehicleCard.locator('.vehicle-card-photo').evaluate(element => element.complete && element.naturalWidth > 0)).toBe(true);
    const fixture = JSON.parse(process.env.BROWSER_FIXTURE);
    await page.goto('/customer/vehicles/' + fixture.vehicle + '/passport');
    const qr = page.getByAltText('QR code linking to this private vehicle passport');
    await expect(qr).toBeVisible();
    expect(await qr.evaluate(image => image.complete && image.naturalWidth > 0)).toBe(true);
});

test('customer shares and revokes a temporary service passport for a verified shop', async ({ page, browser }, testInfo) => {
    const fixture = await signIn(page, 'customer');
    await page.goto('/customer/vehicles/' + fixture.vehicle + '/passport');
    await page.getByRole('button', { name: 'Create 24-hour shop link' }).click();
    const sharedUrl = await page.getByLabel('Shop sharing link').inputValue();
    expect(sharedUrl).toContain('/shop/shared-passports/');
    await page.screenshot({ path: testInfo.outputPath('customer-shared-passport.png'), fullPage: true });

    const shopContext = await browser.newContext();
    try {
        const shopPage = await shopContext.newPage();
        await signIn(shopPage, 'shop_owner');
        await shopPage.goto(sharedUrl);
        await expect(shopPage.getByRole('heading', { name: 'Shared service passport' })).toBeVisible();
        await expect(shopPage.getByText('BROWSER-001')).toBeVisible();
        await shopPage.screenshot({ path: testInfo.outputPath('shop-shared-passport.png'), fullPage: true });

        page.once('dialog', dialog => dialog.accept());
        await page.getByRole('button', { name: 'Revoke access' }).click();
        await expect(page.getByRole('button', { name: 'Create 24-hour shop link' })).toBeVisible();
        const revokedResponse = await shopPage.goto(sharedUrl);
        expect(revokedResponse.status()).toBe(404);
    } finally {
        await shopContext.close();
    }
});

test('booking shows the service price and checks the selected shop schedule', async ({ page }) => {
    const fixture = await signIn(page, 'customer');
    await page.goto('/customer/appointments/create');
    await page.locator('#shop_id').selectOption(String(fixture.shop));
    await page.locator('#service_id').selectOption(String(fixture.service));
    await expect(page.locator('#booking-service-summary')).toContainText('₱900.00');
    await page.locator('#appointment_date').fill(await page.locator('#appointment_date').getAttribute('min'));
    await expect(page.locator('#time-suggestions button').first()).toBeVisible();
    await page.locator('#time-suggestions button').first().click();
    await expect(page.locator('#appointment_time')).toHaveValue('08:00');
    await expect(page.locator('#slot-feedback')).toContainText('The shop still needs to confirm');
});

test('customer sees shop payment details and the review steps', async ({ page }) => {
    await signIn(page, 'customer');
    await page.goto('/customer/invoices');
    await expect(page.getByText('This form does not transfer money.')).toBeVisible();
    await page.locator('[data-payment-method]').first().selectOption('gcash');
    await expect(page.getByText('Send GCash to 09171234567')).toBeVisible();
    await page.locator('[data-payment-method]').first().selectOption('bank_transfer');
    await expect(page.getByText('Test Bank')).toBeVisible();
});

test('customer dashboard makes primary actions clear', async ({ page }, testInfo) => {
    await signIn(page, 'customer');
    await expect(page.getByRole('heading', { name: /Good (morning|afternoon|evening)/ })).toBeVisible();
    await expect(page.locator('#main-content').getByRole('link', { name: 'Find a nearby shop' })).toBeVisible();
    await expect(page.locator('#main-content').getByRole('link', { name: 'Book a service' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Your care summary' })).toBeVisible();
    const aiLauncher = page.getByRole('button', { name: 'Open AutoCare AI assistant' });
    await expect(aiLauncher).toBeVisible();
    await expect(page.locator('#portalNavigation').getByRole('link', { name: 'Ask AutoCare AI' })).toHaveCount(0);
    const dashboardUrl = page.url();
    await aiLauncher.click();
    await expect(page.getByRole('region', { name: 'AutoCare AI chat' })).toBeVisible();
    await expect(page.getByLabel('Ask AutoCare AI')).toBeVisible();
    expect(page.url()).toBe(dashboardUrl);
    await page.getByLabel('Ask AutoCare AI').fill('Please book a service for my car');
    await page.getByRole('button', { name: 'Send question' }).click();
    const aiPanel = page.getByRole('region', { name: 'AutoCare AI chat' });
    await expect(aiPanel).toBeVisible();
    await expect(aiPanel.getByRole('link', { name: 'Book a service' }).last()).toBeVisible();
    expect(page.url()).toBe(dashboardUrl);
    await page.getByRole('button', { name: 'Close AutoCare AI chat' }).click();
    await expect(page.getByRole('region', { name: 'AutoCare AI chat' })).toBeHidden();
    await expect(page.getByRole('heading', { name: 'What’s next' })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(await page.evaluate(() => document.documentElement.clientWidth + 1));
    if (testInfo.project.name !== 'desktop') {
        await expect(page.locator('.customer-mobile-nav')).toBeVisible();
        await expect(page.locator('.customer-mobile-nav').getByRole('link', { name: 'Book' })).toBeVisible();
    }
    await page.screenshot({ path: testInfo.outputPath('customer-dashboard.png'), fullPage: true });
});

test('dark theme keeps customer shop and admin workspaces readable', async ({ page }, testInfo) => {
    await page.addInitScript(() => localStorage.setItem('autocare-theme', 'dark'));

    for (const [role, path] of [['customer', '/dashboard'], ['shop_owner', '/shop/dashboard'], ['admin', '/admin/dashboard']]) {
        await signIn(page, role);
        await page.goto(path);
        await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
        await expect(page.locator('html')).toHaveAttribute('data-bs-theme', 'dark');
        expect(await page.locator('.portal-content').evaluate(element => getComputedStyle(element).color)).toBe('rgb(242, 246, 251)');
        const secondaryText = page.locator('.portal-content .text-secondary').first();
        if (await secondaryText.count()) {
            expect(await secondaryText.evaluate(element => getComputedStyle(element).color)).toBe('rgb(194, 206, 222)');
        }
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(await page.evaluate(() => document.documentElement.clientWidth + 1));
        await page.screenshot({ path: testInfo.outputPath(`${role}-dark.png`), fullPage: true });
        await page.context().clearCookies();
    }
});

test('AI assistant starts without saved database chat history', async ({ page }, testInfo) => {
    await signIn(page, 'customer');
    await page.goto('/customer/ai-assistant');
    await expect(page.getByRole('heading', { name: 'Ask AutoCare AI' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'How can I help with your vehicle today?' })).toBeVisible();
    await expect(page.locator('.ai-message-user')).toHaveCount(0);
    await expect(page.locator('.ai-message-assistant')).toHaveCount(0);
    await expect(page.getByLabel('Ask AutoCare AI a question')).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(await page.evaluate(() => document.documentElement.clientWidth + 1));
    await page.screenshot({ path: testInfo.outputPath('ai-assistant.png'), fullPage: true });
});

test('shop and admin dashboards make daily priorities clear', async ({ page }, testInfo) => {
    await signIn(page, 'shop_owner');
    await page.goto('/shop/dashboard');
    await expect(page.getByRole('heading', { name: 'Browser Test AutoCare' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Today at a glance' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Review appointments' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Create repair job' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Quick actions' })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(await page.evaluate(() => document.documentElement.clientWidth + 1));
    if (testInfo.project.name !== 'desktop') {
        await expect(page.locator('.shop-mobile-nav')).toBeVisible();
        await expect(page.locator('.shop-mobile-nav').getByRole('link', { name: 'New job' })).toBeVisible();
    }
    await page.screenshot({ path: testInfo.outputPath('shop-dashboard.png'), fullPage: true });

    await page.context().clearCookies();
    await signIn(page, 'admin');
    await page.goto('/admin/dashboard');
    await expect(page.getByRole('heading', { name: 'System overview' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Shop registrations to review' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Operations requiring attention' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Open operations monitor' })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(await page.evaluate(() => document.documentElement.clientWidth + 1));
    if (testInfo.project.name !== 'desktop') {
        await expect(page.locator('.admin-mobile-nav')).toBeVisible();
        await expect(page.locator('.admin-mobile-nav').getByRole('link', { name: 'Monitor' })).toBeVisible();
    }
    await page.screenshot({ path: testInfo.outputPath('admin-dashboard.png'), fullPage: true });
});

test('customer books, shop confirms, and admin monitors the same appointment', async ({ browser }, testInfo) => {
    const context = await browser.newContext(testInfo.project.use);
    const page = await context.newPage();
    try {
        const fixture = await signIn(page, 'customer');
        const reason = 'Browser booking ' + testInfo.project.name;
        const date = new Date(Date.now() + 86400000).toISOString().slice(0, 10);
        const time = { desktop: '10:00', mobile: '11:00', 'small-mobile': '12:00' }[testInfo.project.name];
        await page.goto('/customer/appointments/create');
        await page.getByLabel('Service shop', { exact: true }).selectOption(String(fixture.shop));
        await page.getByLabel('Service', { exact: true }).selectOption(String(fixture.service));
        await page.getByLabel('Vehicle', { exact: true }).selectOption(String(fixture.vehicle));
        await page.getByLabel('Preferred date').fill(date);
        await page.getByLabel('Preferred time').fill(time);
        await page.getByLabel('What does your vehicle need?').fill(reason);
        await page.getByRole('button', { name: 'Request appointment' }).click();
        await expect(page).toHaveURL(/customer\/appointments$/);
        await expect(page.getByText(/requested successfully/)).toBeVisible();
        const confirmation = await page.getByText(/requested successfully/).textContent();
        const appointmentId = confirmation.match(/Appointment #(\d+)/)[1];

        await context.clearCookies();
        await signIn(page, 'shop_owner');
        await page.goto('/shop/appointments');
        const row = page.getByRole('row').filter({ hasText: reason });
        await expect(row.getByRole('option', { name: 'Complete', exact: true })).toHaveCount(0);
        await row.getByLabel('Appointment status').selectOption('confirmed');
        await row.getByRole('button', { name: 'Save', exact: true }).click();
        await expect(row.locator('.badge')).toHaveText('confirmed');
        await expect(row.getByRole('option', { name: 'Confirm', exact: true })).toHaveCount(0);

        await context.clearCookies();
        await signIn(page, 'admin');
        await page.goto('/admin/monitoring?module=appointments&status=confirmed');
        const monitored = page.getByRole('row').filter({ has: page.getByRole('rowheader', { name: '#' + appointmentId, exact: true }) });
        await expect(monitored.getByRole('cell', { name: 'Browser Test AutoCare', exact: true })).toBeVisible();
        await expect(monitored.getByRole('cell', { name: 'Confirmed', exact: true })).toBeVisible();
    } finally {
        await context.close();
    }
});

test('admin manages categories and suspends then restores a public shop', async ({ page }, testInfo) => {
    test.setTimeout(60000);
    const fixture = await signIn(page, 'admin');
    const category = 'Browser category ' + testInfo.project.name;
    await page.goto('/admin/service-categories');
    await page.getByLabel('Category name', { exact: true }).fill(category);
    await page.getByRole('button', { name: 'Add category', exact: true }).click();
    const section = page.getByRole('region', { name: category, exact: true });
    await expect(section).toBeVisible();
    await section.getByLabel(/Category name/).fill(category + ' updated');
    await section.getByRole('button', { name: 'Save category' }).click();
    const updated = page.getByRole('region', { name: category + ' updated', exact: true });
    await expect(updated).toBeVisible();
    page.once('dialog', dialog => dialog.accept());
    await updated.getByRole('button', { name: 'Delete unused category' }).click();
    await expect(updated).toHaveCount(0);

    await page.goto('/admin/shops');
    const shop = page.getByRole('row').filter({ hasText: 'Browser Test AutoCare' });
    await shop.getByLabel('Reason for suspending Browser Test AutoCare').fill('Browser moderation test');
    await shop.getByRole('button', { name: 'Suspend shop', exact: true }).click();
    await expect(shop.getByRole('button', { name: 'Reactivate shop' })).toBeVisible();
    expect((await page.request.get('/shops/' + fixture.shop)).status()).toBe(404);
    await shop.getByRole('button', { name: 'Reactivate shop' }).click();
    await expect(shop.getByRole('button', { name: 'Suspend shop', exact: true })).toBeVisible();
    expect((await page.request.get('/shops/' + fixture.shop)).status()).toBe(200);
});

test('shop owner records part usage for an active repair', async ({ page }) => {
    const fixture = await signIn(page, 'shop_owner');
    await page.goto('/shop/inventory');
    const row = page.getByRole('row').filter({ hasText: 'Browser Brake Pad' });
    const stock = Number((await row.getByRole('cell').first().textContent()).match(/\d+/)[0]);
    await row.getByLabel('Repair job for Browser Brake Pad').selectOption(String(fixture.repairJob));
    await row.getByLabel('Units of Browser Brake Pad used').fill('1');
    await row.getByRole('button', { name: 'Record usage' }).click();
    await expect(page.getByText('Part usage recorded for the repair job.')).toBeVisible();
    await expect(row.getByRole('cell').first()).toContainText(String(stock - 1));
});

test('customer and shop owner can download their invoice PDF', async ({ page }) => {
    const fixture = JSON.parse(process.env.BROWSER_FIXTURE);
    for (const [role, path] of [['customer', 'customer'], ['shop_owner', 'shop']]) {
        await signIn(page, role);
        await page.goto(`/${path}/invoices/${fixture.invoice}`);
        await expect(page.getByText('Browser brake service')).toBeVisible();
        const downloadPromise = page.waitForEvent('download');
        await page.getByRole('link', { name: 'Download PDF' }).click();
        const download = await downloadPromise;
        expect(download.suggestedFilename()).toBe(`invoice-${fixture.invoice}.pdf`);
        expect(await download.failure()).toBeNull();
        await page.context().clearCookies();
    }
});

test('uploaded customer and shop images render from public storage', async ({ page }) => {
    const fixture = await signIn(page, 'customer');
    const image = {
        name: 'browser-profile.png',
        mimeType: 'image/png',
        buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9JQqkAAAAASUVORK5CYII=', 'base64'),
    };

    await page.goto('/customer/profile');
    await page.locator('#profile_photo').setInputFiles(image);
    await page.getByRole('button', { name: 'Save profile' }).click();
    await expect(page.getByText('Your profile has been updated.')).toBeVisible();
    await expect(page.locator('.profile-photo-preview')).toBeVisible();
    expect(await page.locator('.profile-photo-preview').evaluate(element => element.complete && element.naturalWidth > 0)).toBe(true);
    expect(await page.locator('.account-avatar').evaluate(element => element.complete && element.naturalWidth > 0)).toBe(true);

    await page.context().clearCookies();
    await signIn(page, 'shop_owner');
    await page.goto('/shop/profile');
    await page.locator('#logo').setInputFiles({ ...image, name: 'browser-logo.png' });
    await page.locator('#cover_photo').setInputFiles({ ...image, name: 'browser-cover.png' });
    await page.getByRole('button', { name: 'Save profile' }).click();
    await expect(page.getByText('Shop profile updated.')).toBeVisible();
    await page.goto('/shops/' + fixture.shop);
    for (const selector of ['.shop-public-logo', '.shop-public-cover']) {
        await expect(page.locator(selector)).toBeVisible();
        expect(await page.locator(selector).evaluate(element => element.complete && element.naturalWidth > 0)).toBe(true);
    }
});
