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
    customer: ['dashboard', 'customer/vehicles', 'customer/appointments', 'customer/appointments/create', 'customer/quotations', 'customer/repairs', 'customer/invoices', 'customer/reminders', 'customer/messages', 'customer/reviews', 'customer/notifications', 'customer/inspections', 'customer/emergency-requests', 'customer/ai-assistant', 'customer/profile', 'customer/favorites', 'customer/service-history', 'customer/services', 'customer/service-cases'],
    shop_owner: ['shop/dashboard', 'shop/profile', 'shop/services', 'shop/appointments', 'shop/passports/scan', 'shop/repair-jobs', 'shop/quotations', 'shop/invoices', 'shop/inventory', 'shop/technicians', 'shop/messages', 'shop/reviews', 'shop/reports', 'shop/emergency-requests', 'shop/notifications', 'shop/today-work', 'shop/service-cases'],
    admin: ['admin/dashboard', 'admin/shops', 'admin/users', 'admin/services', 'admin/service-categories', 'admin/barangays', 'admin/reviews', 'admin/reports', 'admin/activity-logs', 'admin/monitoring', 'admin/service-cases'],
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

for (const [role, path] of [['shop_owner', '/shop/reports'], ['admin', '/admin/reports']]) {
    test(role + ' report controls filter dates and show status charts in both themes', async ({ page }, testInfo) => {
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await signIn(page, role);
        await page.goto(path);
        await page.getByRole('link', { name: 'Last 7 days', exact: true }).click();
        await expect(page.getByRole('link', { name: 'Last 7 days', exact: true })).toHaveAttribute('aria-current', 'true');
        const status = page.getByRole('region', { name: 'Appointment status breakdown' });
        await expect(status.locator('.report-doughnut__total strong')).toHaveText('2');
        await expect(status.locator('.report-status-legend')).toContainText('50.0%');

        for (const theme of ['light', 'dark']) {
            await page.evaluate(value => localStorage.setItem('autocare-theme', value), theme);
            await page.reload();
            await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
            const canvas = page.locator('[data-report-status-chart]');
            await expect(canvas).toBeVisible();
            await expect.poll(() => canvas.evaluate(element =>
                [...element.getContext('2d').getImageData(0, 0, element.width, element.height).data].some(value => value > 0)
            )).toBe(true);
            expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(
                await page.evaluate(() => document.documentElement.clientWidth + 1)
            );
            await page.screenshot({ path: testInfo.outputPath(role + '-reports-' + theme + '.png'), fullPage: true });
        }

        await page.getByLabel('From', { exact: true }).fill('2001-01-01');
        await page.getByLabel('To', { exact: true }).fill('2001-01-07');
        await page.getByRole('button', { name: 'Apply range' }).click();
        await expect(status).toContainText('No appointments in this date range');
        await expect(page.locator('[data-report-status-chart]')).toHaveCount(0);
        const pdfUrl = new URL(await page.getByRole('link', { name: 'Download PDF' }).getAttribute('href'));
        expect(pdfUrl.searchParams.get('from')).toBe('2001-01-01');
        expect(pdfUrl.searchParams.get('to')).toBe('2001-01-07');
        expect(errors).toEqual([]);
    });
}

test('shop can send itemized estimate, start approved repair, and issue its invoice', async ({ page, context }, testInfo) => {
    test.skip(testInfo.project.name !== 'desktop', 'The cross-role workflow runs once; page rendering runs at every viewport');
    const fixture = await signIn(page, 'shop_owner');
    await page.goto('/shop/repair-jobs?status=diagnosis');
    const job = page.locator(`#repair-${fixture.diagnosisRepairJob}`);
    await expect(job.getByRole('link', { name: 'Send estimate for approval' })).toBeVisible();
    await expect(job.getByLabel('Next stage')).toHaveCount(0);
    await job.getByRole('link', { name: 'Send estimate for approval' }).click();
    await expect(page.getByText('PHP 1,250.00', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Send estimate to customer' }).click();
    await expect(page.getByText('Quotation sent to the customer.')).toBeVisible();

    await context.clearCookies();
    await signIn(page, 'customer');
    await page.goto('/customer/repairs');
    await page.locator(`#repair-${fixture.diagnosisRepairJob}`).getByRole('button', { name: 'Approve estimate' }).click();
    await expect(page.getByText('Quotation response saved.')).toBeVisible();

    await context.clearCookies();
    await signIn(page, 'shop_owner');
    await page.goto('/shop/repair-jobs?status=approved');
    const approvedJob = page.locator(`#repair-${fixture.diagnosisRepairJob}`);
    await expect(approvedJob.getByRole('button', { name: 'Start repair' })).toBeVisible();
    await page.screenshot({ path: testInfo.outputPath('approved-repair-ready-to-start.png'), fullPage: true });
    await approvedJob.getByRole('button', { name: 'Start repair' }).click();
    await page.goto('/shop/repair-jobs?status=repairing');
    const activeJob = page.locator(`#repair-${fixture.diagnosisRepairJob}`);
    await activeJob.getByLabel('Next stage').selectOption('completed');
    await activeJob.getByRole('button', { name: 'Update repair stage' }).click();
    await page.goto('/shop/repair-jobs?status=completed');
    await page.locator(`#repair-${fixture.diagnosisRepairJob}`).getByRole('button', { name: 'Issue invoice' }).click();
    await expect(page).toHaveURL(/shop\/invoices$/);
    await expect(page.getByText('PHP 1,250.00', { exact: true })).toBeVisible();
});

test('customer can share location and see the assigned roadside shop', async ({ page }, testInfo) => {
    await signIn(page, 'customer');
    await page.context().grantPermissions(['geolocation']);
    await page.context().setGeolocation({ latitude: 10.2443, longitude: 123.7898 });
    await page.goto('/customer/emergency-requests');
    await page.getByRole('button', { name: 'Use my current location' }).click();
    await expect(page.getByRole('status')).toContainText('Location ready');
    const landmark = 'Browser roadside ' + testInfo.project.name;
    await page.getByLabel('Location details').fill(landmark);
    await page.getByRole('button', { name: 'Send assistance request' }).click();
    const request = page.getByRole('article', { name: 'breakdown request' }).filter({ hasText: landmark });
    await expect(request).toContainText('Browser Test AutoCare');
    await expect(request.getByRole('link', { name: '09170000000' })).toHaveAttribute('href', 'tel:09170000000');
    await expect(request).toContainText('Dispatch history (1)');
});

for (const [role, notificationsPath] of [['customer', '/customer/notifications'], ['shop_owner', '/shop/notifications']]) {
    test(role + ' notification navigation stays labeled and aligned in both themes', async ({ page }, testInfo) => {
        await signIn(page, role);
        const notificationLink = page.locator('.portal-topbar .portal-notifications-link');
        await expect(notificationLink).toBeVisible();
        const unreadCount = Number((await notificationLink.getAttribute('aria-label')).match(/Notifications, (\d+) unread/)[1]);
        await expect(notificationLink.locator('.portal-notifications-count')).toHaveText(String(unreadCount));
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(await page.evaluate(() => document.documentElement.clientWidth + 1));
        await page.screenshot({ path: testInfo.outputPath(role + '-notification-header-light.png'), fullPage: false });

        await notificationLink.click();
        await expect(page).toHaveURL(new RegExp(notificationsPath + '$'));
        await expect(page.getByRole('heading', { name: 'Notifications' })).toBeVisible();
        await expect(page.locator('.notification-summary')).toContainText('unread');
        await page.evaluate(() => localStorage.setItem('autocare-theme', 'dark'));
        await page.reload();
        await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
        await expect(page.locator('.portal-topbar .portal-notifications-link')).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath(role + '-notifications-dark.png'), fullPage: false });
    });

    test(role + ' can filter and bulk-update notifications without overflow', async ({ page }, testInfo) => {
        await signIn(page, role);
        await page.goto(notificationsPath);
        const markAllUnread = page.getByRole('button', { name: 'Mark all as unread' });
        if (await markAllUnread.isEnabled()) {
            await markAllUnread.click();
        }
        await page.locator('.notification-item').filter({ hasText: 'Browser older update' })
            .getByRole('button', { name: 'Mark as read' }).click();
        await expect(page.locator('.notification-item.is-unread').first()).toBeVisible();
        await expect(page.getByRole('button', { name: 'Mark all as read' })).toBeEnabled();
        await expect(markAllUnread).toBeEnabled();
        await page.getByRole('link', { name: /Unread/ }).last().click();
        await expect(page).toHaveURL(/filter=unread/);
        await expect(page.getByText('Browser older update')).toHaveCount(0);
        await page.getByRole('button', { name: 'Mark all as read' }).click();
        await expect(page.getByRole('heading', { name: 'All caught up' })).toBeVisible();
        await expect(page.locator('.notification-summary')).toHaveText('0 unread');
        await page.getByRole('button', { name: 'Mark all as unread' }).click();
        await expect(page.getByText('Browser older update')).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(await page.evaluate(() => document.documentElement.clientWidth + 1));
        await page.screenshot({ path: testInfo.outputPath(role + '-notifications-unread-' + testInfo.project.name + '.png'), fullPage: false });
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
    await page.getByText('My private QR (owner login only)').click();
    const qr = page.getByAltText('QR code linking to your private vehicle passport');
    await expect(qr).toBeVisible();
    expect(await qr.evaluate(image => image.complete && image.naturalWidth > 0)).toBe(true);
});

test('customer shares and revokes a temporary service passport for a verified shop', async ({ page, browser }, testInfo) => {
    test.setTimeout(90000);
    const fixture = await signIn(page, 'customer');
    await page.goto('/customer/vehicles/' + fixture.vehicle + '/passport');
    await page.getByRole('button', { name: 'Create 24-hour shop link' }).click();
    const sharedUrl = await page.getByLabel('Shop sharing link').inputValue();
    expect(sharedUrl).toContain('/shop/shared-passports/');
    await page.screenshot({ path: testInfo.outputPath('customer-shared-passport.png'), fullPage: true, mask: [page.locator('#passport-share-url')] });

    const shopContext = await browser.newContext();
    try {
        const shopPage = await shopContext.newPage();
        await signIn(shopPage, 'shop_owner');
        await shopPage.goto('/shop/passports/scan');
        await expect(shopPage.getByRole('heading', { name: 'Scan a service passport' })).toBeVisible();
        expect(await shopPage.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(
            await shopPage.evaluate(() => document.documentElement.clientWidth + 1)
        );
        await shopPage.screenshot({ path: testInfo.outputPath('shop-passport-scanner.png'), fullPage: true });
        await shopPage.getByLabel("Customer's shop-sharing link").fill(sharedUrl);
        await shopPage.getByRole('button', { name: 'Open service passport' }).click();
        await expect(shopPage.getByRole('heading', { name: 'Shared service passport' })).toBeVisible();
        await expect(shopPage.getByText('BROWSER-001')).toBeVisible();
        await expect(shopPage.getByRole('link', { name: 'Review shop appointments' })).toBeVisible();
        await shopPage.screenshot({ path: testInfo.outputPath('shop-shared-passport.png'), fullPage: true });

        await shopPage.addInitScript((url) => {
            window.BarcodeDetector = class {
                static async getSupportedFormats() { return ['qr_code']; }
                async detect() { return [{ rawValue: url }]; }
            };
            Object.defineProperty(navigator, 'mediaDevices', { value: { getUserMedia: async () => new MediaStream() } });
            HTMLMediaElement.prototype.play = async function () {};
            Object.defineProperty(HTMLMediaElement.prototype, 'readyState', { get: () => 2 });
        }, sharedUrl);
        await shopPage.getByRole('link', { name: 'Scan another passport' }).click();
        await shopPage.getByRole('button', { name: 'Start camera' }).click();
        await expect(shopPage.getByRole('heading', { name: 'Shared service passport' })).toBeVisible();

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
    const paymentForm = page.locator('form:has([data-payment-method])').first();
    await expect(paymentForm).toBeVisible();
    await expect(page.getByText('This form does not transfer money.').first()).toBeVisible();
    await paymentForm.locator('[data-payment-method]').selectOption('gcash');
    await expect(paymentForm.locator('[data-method-instructions=gcash]')).toContainText('Send GCash to 09171234567');
    await paymentForm.locator('[data-payment-method]').selectOption('bank_transfer');
    await expect(paymentForm.locator('[data-method-instructions=bank_transfer]')).toContainText('Test Bank');
});

test('customer dashboard makes primary actions clear', async ({ page }, testInfo) => {
    test.setTimeout(60000);
    await signIn(page, 'customer');
    await expect(page.getByRole('heading', { name: /Good (morning|afternoon|evening)/ })).toBeVisible();
    await expect(page.locator('.customer-welcome .btn-accent')).toBeVisible();
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
    await page.route('**/customer/ai-assistant', async route => {
        await new Promise(resolve => setTimeout(resolve, 400));
        await route.continue();
    });
    await page.getByLabel('Ask AutoCare AI').fill('Please book a service for my car');
    const responsePromise = page.waitForResponse(response => response.request().method() === 'POST' && response.url().includes('/customer/ai-assistant'), { timeout: 30000 });
    await page.getByRole('button', { name: 'Send question' }).click();
    await expect(page.locator('.ai-widget-typing')).toBeVisible();
    await responsePromise;
    const aiPanel = page.getByRole('region', { name: 'AutoCare AI chat' });
    await expect(aiPanel).toBeVisible();
    await expect(aiPanel.getByText('I found your vehicle', { exact: false })).toBeVisible();
    await expect(aiPanel.getByRole('link', { name: 'Book a service' }).last()).toBeVisible();
    await expect(page.locator('.ai-widget-typing')).toBeHidden();
    expect(page.url()).toBe(dashboardUrl);
    await page.unroute('**/customer/ai-assistant');
    await page.route('**/customer/ai-assistant', route => route.fulfill({
        status: 503, contentType: 'application/json',
        body: JSON.stringify({ message: 'AI Assistant is temporarily unavailable.' }),
    }), { times: 1 });
    await page.getByLabel('Ask AutoCare AI').fill('Explain brake fluid');
    await page.getByRole('button', { name: 'Send question' }).click();
    await expect(aiPanel.getByRole('alert')).toContainText('AI Assistant is temporarily unavailable.');
    await expect(page.getByLabel('Ask AutoCare AI')).toHaveValue('Explain brake fluid');
    await expect(page.getByRole('button', { name: 'Send question' })).toBeEnabled();
    await expect(aiPanel.getByText('Explain brake fluid')).toHaveCount(0);
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
    await expect(page.locator('.ai-message-assistant:not(.ai-message-typing)')).toHaveCount(0);
    await expect(page.getByLabel('Your question for AutoCare AI')).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(await page.evaluate(() => document.documentElement.clientWidth + 1));
    await page.screenshot({ path: testInfo.outputPath('ai-assistant.png'), fullPage: true });
    const pageUrl = page.url();
    await page.locator('.ai-composer [data-ai-prompt]').nth(1).click();
    await expect(page.locator('.ai-message-assistant:not(.ai-message-typing)')).toHaveCount(1);
    await expect(page.locator('.ai-command-action').filter({ hasText: 'Book a service' })).toBeVisible();
    await expect(page.getByLabel('Your question for AutoCare AI')).toBeEmpty();
    expect(page.url()).toBe(pageUrl);
});

test('shop and admin dashboards make daily priorities clear', async ({ page }, testInfo) => {
    await signIn(page, 'shop_owner');
    await page.goto('/shop/dashboard');
    await expect(page.getByRole('heading', { name: 'Browser Test AutoCare' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Today at a glance' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Review appointments' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'View repair jobs' })).toBeVisible();
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
    test.setTimeout(120000);
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
        await row.getByRole('button', { name: 'Update status', exact: true }).click();
        await expect(row.locator('.badge')).toHaveText('Ready for repair');
        const startRepairLink = row.getByRole('link', { name: 'Start repair job' });
        await expect(startRepairLink).toBeVisible();
        await expect(row.getByLabel('Appointment status')).toBeHidden();
        expect(await startRepairLink.evaluate(link => link.getBoundingClientRect().right <= document.documentElement.clientWidth + 1)).toBe(true);
        await page.evaluate(() => localStorage.setItem('autocare-theme', 'dark'));
        await page.reload();
        await expect(startRepairLink).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('confirmed-shop-appointment.png'), fullPage: true });
        await row.getByText('Other appointment actions').click();
        await expect(row.getByLabel('Appointment status')).toBeVisible();
        await expect(row.getByRole('option', { name: 'Confirm', exact: true })).toHaveCount(0);
        await page.evaluate(() => localStorage.removeItem('autocare-theme'));

        await context.clearCookies();
        await signIn(page, 'admin');
        await page.goto('/admin/monitoring?module=appointments&status=confirmed');
        const monitored = page.getByRole('row').filter({ has: page.getByRole('rowheader', { name: '#' + appointmentId, exact: true }) });
        await expect(monitored.getByRole('cell', { name: 'Browser Test AutoCare', exact: true })).toBeVisible();
        await expect(monitored.getByRole('cell', { name: 'Confirmed', exact: true })).toBeVisible();

        await context.clearCookies();
        await signIn(page, 'shop_owner');
        await page.goto('/shop/appointments?status=confirmed');
        await page.getByRole('row').filter({ hasText: reason }).getByRole('link', { name: 'Start repair job' }).click();
        await expect(page.locator('#appointment_id')).toHaveValue(appointmentId);
        await page.getByLabel('Work description').fill('Complete ' + reason);
        await page.getByRole('button', { name: 'Create repair job' }).click();
        await expect(page.getByText(/Repair job .* created/)).toBeVisible();
        const jobNumber = (await page.getByText(/Repair job .* created/).textContent()).match(/Repair job (\S+) created/)[1];
        await page.goto('/shop/appointments?status=in_progress');
        await expect(page.getByRole('row').filter({ hasText: reason }).getByRole('link', { name: 'View repair' })).toBeVisible();

        await context.clearCookies();
        await signIn(page, 'customer');
        await page.goto('/customer/appointments?status=in_progress');
        const customerAppointment = page.locator('article').filter({ hasText: reason });
        await expect(customerAppointment.getByRole('link', { name: 'View repair progress' })).toBeVisible();
        await page.screenshot({ path: testInfo.outputPath('customer-appointment-in-repair.png'), fullPage: true });
        await customerAppointment.getByRole('link', { name: 'View repair progress' }).click();
        await expect(page.getByRole('heading', { name: jobNumber })).toBeVisible();
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
    const shop = page.locator('article').filter({ has: page.getByRole('heading', { name: 'Browser Test AutoCare' }) });
    await shop.getByLabel('Reason for suspending Browser Test AutoCare').fill('Browser moderation test');
    await shop.getByRole('button', { name: 'Suspend shop', exact: true }).click();
    await expect(shop.getByRole('button', { name: 'Reactivate shop' })).toBeVisible();
    expect((await page.request.get('/shops/' + fixture.shop)).status()).toBe(404);
    await shop.getByRole('button', { name: 'Reactivate shop' }).click();
    await expect(shop.getByRole('button', { name: 'Suspend shop', exact: true })).toBeVisible();
    expect((await page.request.get('/shops/' + fixture.shop)).status()).toBe(200);
});

test('admin Verify document sends the clicked verification status', async ({ page }, testInfo) => {
    const fixture = await signIn(page, 'admin');
    await page.goto('/admin/shops');
    await page.getByText('Review documents', { exact: false }).click();

    const documentId = fixture.browserDocuments[testInfo.project.name];
    const reviewForm = page.locator(`form[action$="/admin/shop-documents/${documentId}"]`);
    await reviewForm.getByRole('button', { name: 'Verify document' }).click();

    await expect(page.getByText('Document review saved.')).toBeVisible();
    const documentCard = page.locator('details .border.rounded.p-3.mb-2')
        .filter({ has: page.locator(`a[href$="/admin/shop-documents/${documentId}"]`) });
    await expect(documentCard).toContainText('verified');
});

test('shop owner records part usage for an active repair', async ({ page }) => {
    const fixture = await signIn(page, 'shop_owner');
    await page.goto('/shop/inventory');
    const row = page.getByRole('row').filter({ hasText: 'Browser Brake Pad' });
    const stock = Number((await row.getByRole('cell').first().textContent()).match(/\d+/)[0]);
    await row.getByLabel('Repair job').selectOption(String(fixture.repairJob));
    await row.getByLabel('Units used').fill('1');
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

test('messages show the correct shop and customer identities on every viewport', async ({ page }, testInfo) => {
    await signIn(page, 'customer');
    await page.goto('/customer/messages');
    const shopContact = page.locator('.message-contact').first();
    await expect(shopContact.getByRole('img', { name: 'Browser Test AutoCare logo' })).toBeVisible();
    await expect(shopContact).toContainText('Browser Test AutoCare');
    await shopContact.click();
    await expect(page.getByRole('heading', { name: 'Browser Test AutoCare' })).toBeVisible();
    await expect(page.locator('[data-ai-widget]')).toHaveCount(0);
    await expect(page.locator('.message-row.is-incoming')).toBeVisible();
    await expect(page.locator('.message-row.is-own').first()).toBeVisible();
    const sentMessage = `Browser message from ${testInfo.project.name}`;
    await page.locator('.message-compose textarea').fill(sentMessage);
    await page.locator('.message-compose button').click();
    await expect(page.locator('.message-row.is-own').last()).toContainText(sentMessage);
    await page.locator('.message-compose button').click({ trial: true });
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(await page.evaluate(() => document.documentElement.clientWidth + 1));
    await page.screenshot({ path: testInfo.outputPath('customer-conversation.png'), fullPage: true });
    await page.evaluate(() => localStorage.setItem('autocare-theme', 'dark'));
    await page.reload();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await expect(page.locator('.message-compose label')).toContainText('Your message');
    await page.locator('.message-compose button').click({ trial: true });
    await page.screenshot({ path: testInfo.outputPath('customer-conversation-dark.png'), fullPage: true });

    await page.context().clearCookies();
    await signIn(page, 'shop_owner');
    await page.goto('/shop/messages');
    const customerContact = page.locator('.message-contact').first();
    await expect(customerContact.getByRole('img', { name: 'Browser customer profile photo' })).toBeVisible();
    await expect(customerContact).toContainText('Browser customer');
    await expect(customerContact).toContainText(sentMessage);
    await customerContact.click();
    await expect(page.getByRole('heading', { name: 'Browser customer' })).toBeVisible();
    await expect(page.locator('.message-row.is-incoming').first()).toBeVisible();
    await expect(page.locator('.message-row.is-own').first()).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(await page.evaluate(() => document.documentElement.clientWidth + 1));
    await page.screenshot({ path: testInfo.outputPath('shop-conversation.png'), fullPage: true });
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
    await expect.poll(() => page.locator('.profile-photo-preview').evaluate(element => element.complete && element.naturalWidth > 0)).toBe(true);
    await expect.poll(() => page.locator('.account-avatar').evaluate(element => element.complete && element.naturalWidth > 0)).toBe(true);

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

test('admin can resolve and dismiss complaints with a clear reason on every viewport', async ({ page }, testInfo) => {
    await page.addInitScript(() => localStorage.setItem('autocare-theme', 'dark'));
    const fixture = await signIn(page, 'admin');
    await page.goto('/admin/service-cases');
    await expect(page.getByRole('heading', { name: 'Complaint resolution' })).toBeVisible();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await page.screenshot({ path: testInfo.outputPath('admin-complaints-dark.png'), fullPage: false });

    const resolveCase = page.locator('#complaint-' + fixture.complaintCases[testInfo.project.name].resolved);
    const resolveReason = resolveCase.getByLabel('Reason for your decision');
    await resolveCase.getByRole('button', { name: 'Resolve complaint' }).click();
    await expect(resolveReason).toBeFocused();
    await expect(resolveCase.locator('.badge')).toHaveText('Open');
    await resolveReason.fill('The shop will inspect the vehicle again at no cost.');
    await resolveCase.getByRole('button', { name: 'Resolve complaint' }).click();
    await expect(resolveCase.locator('.badge')).toHaveText('Resolved');
    await expect(resolveCase).toContainText('The shop will inspect the vehicle again at no cost.');

    const dismissCase = page.locator('#complaint-' + fixture.complaintCases[testInfo.project.name].dismissed);
    await dismissCase.getByLabel('Reason for your decision').fill('The available evidence does not support further action.');
    await dismissCase.getByRole('button', { name: 'Dismiss complaint' }).click();
    await expect(dismissCase.locator('.badge')).toHaveText('Dismissed');
    await expect(dismissCase).toContainText('The available evidence does not support further action.');
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(await page.evaluate(() => document.documentElement.clientWidth + 1));
});
