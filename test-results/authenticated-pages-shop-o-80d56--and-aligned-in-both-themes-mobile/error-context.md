# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: authenticated-pages.spec.js >> shop_owner notification navigation stays labeled and aligned in both themes
- Location: tests\Browser\authenticated-pages.spec.js:66:5

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByRole('link', { name: 'Notifications, 0 unread' })
Expected: visible
Timeout: 5000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" getByRole('link', { name: 'Notifications, 0 unread' }) with timeout 5000ms
  - waiting for getByRole('link', { name: 'Notifications, 0 unread' })

```

```yaml
- link "Skip to content":
  - /url: "#main-content"
- banner:
  - button "Open navigation"
  - link "Notifications, 1 unread":
    - /url: http://127.0.0.1:8877/shop/notifications
  - button "Browser shop owner"
- main:
  - region "Browser Test AutoCare":
    - text: Friday, October 16 approved
    - heading "Browser Test AutoCare" [level=1]
    - paragraph: Review today's visits and keep each repair moving.
    - link "Review appointments":
      - /url: http://127.0.0.1:8877/shop/appointments
    - link "View repair jobs":
      - /url: http://127.0.0.1:8877/shop/repair-jobs
  - region "Today at a glance":
    - heading "Today at a glance" [level=2]
    - paragraph: The work and issues that need your team’s attention.
    - link "0 Today’s visits":
      - /url: http://127.0.0.1:8877/shop/appointments
      - strong: "0"
      - text: Today’s visits
    - link "0 Waiting approval":
      - /url: http://127.0.0.1:8877/shop/appointments
      - strong: "0"
      - text: Waiting approval
    - link "2 Active repairs":
      - /url: http://127.0.0.1:8877/shop/repair-jobs
      - strong: "2"
      - text: Active repairs
    - link "0 Low-stock parts":
      - /url: http://127.0.0.1:8877/shop/inventory
      - strong: "0"
      - text: Low-stock parts
  - region "Today’s schedule":
    - heading "Today’s schedule" [level=2]
    - paragraph: Upcoming customers in appointment order.
    - link "Full schedule":
      - /url: http://127.0.0.1:8877/shop/appointments
    - heading "No visits scheduled today" [level=3]
    - paragraph: New booking requests will appear here once customers choose your shop.
  - region "Repairs in progress":
    - heading "Repairs in progress" [level=2]
    - paragraph: Recent jobs that are not yet released.
    - link "View all":
      - /url: http://127.0.0.1:8877/shop/repair-jobs
    - article:
      - heading "BROWSER-001 · Browser customer" [level=3]
      - paragraph: Complete Browser booking desktop
      - text: pending
    - article:
      - heading "BROWSER-001 · Browser customer" [level=3]
      - paragraph: Brake maintenance
      - text: repairing
  - region "Quick actions":
    - heading "Quick actions" [level=2]
    - paragraph: Common tasks for running the shop.
    - link "Quotations Review estimates":
      - /url: http://127.0.0.1:8877/shop/quotations
      - strong: Quotations
      - text: Review estimates
    - link "Payments 0 awaiting verification":
      - /url: http://127.0.0.1:8877/shop/invoices
      - strong: Payments
      - text: 0 awaiting verification
    - link "Inventory Check parts and stock":
      - /url: http://127.0.0.1:8877/shop/inventory
      - strong: Inventory
      - text: Check parts and stock
    - link "Messages Reply to customers":
      - /url: http://127.0.0.1:8877/shop/messages
      - strong: Messages
      - text: Reply to customers
- contentinfo: Minglanilla AutoCare Hub · Shop owner workspace
- navigation "Shop quick navigation":
  - link "Home":
    - /url: http://127.0.0.1:8877/shop/dashboard
  - link "Bookings":
    - /url: http://127.0.0.1:8877/shop/appointments
  - link "New job":
    - /url: http://127.0.0.1:8877/shop/repair-jobs/create
  - link "Repairs":
    - /url: http://127.0.0.1:8877/shop/repair-jobs
  - link "Messages, 0 unread":
    - /url: http://127.0.0.1:8877/shop/messages
    - text: Messages
```

# Test source

```ts
  1   | import { test, expect } from '@playwright/test';
  2   | 
  3   | test.skip(() => process.env.BROWSER_ISOLATED !== '1', 'Requires disposable browser database');
  4   | 
  5   | async function signIn(page, role) {
  6   |     const fixture = JSON.parse(process.env.BROWSER_FIXTURE);
  7   |     await page.goto('/login');
  8   |     await page.getByLabel('Email address').fill(role + '@browser.example.test');
  9   |     await page.getByLabel('Password', { exact: true }).fill(fixture.password);
  10  |     await page.getByRole('button', { name: 'Sign in', exact: true }).click();
  11  |     await expect(page).toHaveURL(/dashboard$/);
  12  |     return fixture;
  13  | }
  14  | 
  15  | const pages = {
  16  |     customer: ['dashboard', 'customer/vehicles', 'customer/appointments', 'customer/appointments/create', 'customer/quotations', 'customer/repairs', 'customer/invoices', 'customer/reminders', 'customer/messages', 'customer/reviews', 'customer/notifications', 'customer/inspections', 'customer/emergency-requests', 'customer/ai-assistant', 'customer/profile', 'customer/favorites', 'customer/service-history'],
  17  |     shop_owner: ['shop/dashboard', 'shop/profile', 'shop/services', 'shop/appointments', 'shop/repair-jobs', 'shop/quotations', 'shop/invoices', 'shop/inventory', 'shop/technicians', 'shop/messages', 'shop/reviews', 'shop/reports', 'shop/emergency-requests', 'shop/notifications'],
  18  |     admin: ['admin/dashboard', 'admin/shops', 'admin/users', 'admin/services', 'admin/service-categories', 'admin/barangays', 'admin/reviews', 'admin/reports', 'admin/activity-logs', 'admin/monitoring'],
  19  | };
  20  | 
  21  | for (const [role, paths] of Object.entries(pages)) {
  22  |     test(role + ' pages and navigation work at the configured viewport', async ({ page }, testInfo) => {
  23  |         test.setTimeout(120000);
  24  |         await signIn(page, role);
  25  |         const errors = [];
  26  |         page.on('pageerror', error => errors.push(error.message));
  27  |         for (const path of paths) {
  28  |             const response = await page.goto('/' + path);
  29  |             expect(response.status(), path).toBe(200);
  30  |             await expect(page.locator('h1'), path).toBeVisible();
  31  |             await expect(page.locator('#portalNavigation'), path).toBeAttached();
  32  |             await page.evaluate(() => document.fonts.ready);
  33  |             const layout = await page.evaluate(() => ({
  34  |                 viewport: document.documentElement.clientWidth,
  35  |                 page: document.documentElement.scrollWidth,
  36  |                 ancestors: [...document.querySelectorAll('.portal-workspace, .portal-content, .surface, .table-responsive')]
  37  |                     .slice(0, 8).map(element => ({ tag: element.className, left: Math.round(element.getBoundingClientRect().left),
  38  |                         right: Math.round(element.getBoundingClientRect().right), width: element.scrollWidth,
  39  |                         overflow: getComputedStyle(element).overflowX })),
  40  |                 overflowing: [...document.querySelectorAll('main, main *')]
  41  |                     .filter(element => element.getBoundingClientRect().right > document.documentElement.clientWidth + 1)
  42  |                     .slice(0, 8).map(element => `${element.tagName}.${element.className}=${Math.round(element.getBoundingClientRect().right)}`),
  43  |             }));
  44  |             expect(layout.page, `${path}: ${JSON.stringify(layout)}`).toBeLessThanOrEqual(layout.viewport + 1);
  45  |             if (path.endsWith('/reports')) {
  46  |                 const charts = page.locator('[data-report-chart]');
  47  |                 expect(await charts.count()).toBeGreaterThan(0);
  48  |                 for (const chart of await charts.all()) {
  49  |                     await expect(chart).toBeVisible();
  50  |                     await expect.poll(() => chart.evaluate(canvas => [...canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height).data].some(value => value > 0))).toBe(true);
  51  |                 }
  52  |             }
  53  |         }
  54  |         if (testInfo.project.name !== 'desktop') {
  55  |             await page.getByRole('button', { name: 'Open navigation' }).click();
  56  |             await expect(page.locator('#portalNavigation')).toHaveClass(/\bshow\b/);
  57  |             await page.getByRole('button', { name: 'Close navigation' }).click();
  58  |             await expect(page.locator('#portalNavigation')).not.toHaveClass(/\bshow\b/);
  59  |         }
  60  |         expect(errors).toEqual([]);
  61  |         await page.screenshot({ path: testInfo.outputPath(role + '.png'), fullPage: true });
  62  |     });
  63  | }
  64  | 
  65  | for (const [role, notificationsPath] of [['customer', '/customer/notifications'], ['shop_owner', '/shop/notifications']]) {
  66  |     test(role + ' notification navigation stays labeled and aligned in both themes', async ({ page }, testInfo) => {
  67  |         await signIn(page, role);
  68  |         const notificationLink = page.getByRole('link', { name: 'Notifications, 0 unread' });
> 69  |         await expect(notificationLink).toBeVisible();
      |                                        ^ Error: expect(locator).toBeVisible() failed
  70  |         await expect(notificationLink.locator('.portal-notifications-count')).toHaveText('0');
  71  |         expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(await page.evaluate(() => document.documentElement.clientWidth + 1));
  72  |         await page.screenshot({ path: testInfo.outputPath(role + '-notification-header-light.png'), fullPage: false });
  73  | 
  74  |         await notificationLink.click();
  75  |         await expect(page).toHaveURL(new RegExp(notificationsPath + '$'));
  76  |         await expect(page.getByRole('heading', { name: 'Notifications' })).toBeVisible();
  77  |         await expect(page.locator('.notification-summary')).toHaveText('0 unread');
  78  |         await page.evaluate(() => localStorage.setItem('autocare-theme', 'dark'));
  79  |         await page.reload();
  80  |         await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  81  |         await expect(page.getByRole('link', { name: 'Notifications, 0 unread' })).toBeVisible();
  82  |         await page.screenshot({ path: testInfo.outputPath(role + '-notifications-dark.png'), fullPage: false });
  83  |     });
  84  | }
  85  | 
  86  | test('customer creates a vehicle and opens its private passport', async ({ page }, testInfo) => {
  87  |     await signIn(page, 'customer');
  88  |     const plate = 'E2E-' + testInfo.project.name;
  89  |     await page.goto('/customer/vehicles/create');
  90  |     for (const [label, value] of [['Plate number', plate], ['Current mileage', '25000'], ['Brand', 'Toyota'], ['Model', 'Vios'], ['Year', '2022']]) {
  91  |         await page.getByLabel(label, { exact: true }).fill(value);
  92  |     }
  93  |     await page.locator('#photo').setInputFiles({
  94  |         name: 'browser-vehicle.png',
  95  |         mimeType: 'image/png',
  96  |         buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9JQqkAAAAASUVORK5CYII=', 'base64'),
  97  |     });
  98  |     await expect(page.locator('img[data-image-preview]')).toBeVisible();
  99  |     expect(await page.locator('img[data-image-preview]').evaluate(image => image.complete && image.naturalWidth > 0)).toBe(true);
  100 |     await expect(page.locator('.file-selection')).toContainText('Save to publish this image');
  101 |     await page.getByRole('button', { name: 'Save vehicle' }).click();
  102 |     await expect(page).toHaveURL(/customer\/vehicles$/);
  103 |     await page.reload();
  104 |     const vehicleCard = page.locator('article').filter({ hasText: plate });
  105 |     await expect(vehicleCard.getByText(plate, { exact: true })).toBeVisible();
  106 |     expect(await vehicleCard.locator('.vehicle-card-photo').evaluate(element => element.complete && element.naturalWidth > 0)).toBe(true);
  107 |     const fixture = JSON.parse(process.env.BROWSER_FIXTURE);
  108 |     await page.goto('/customer/vehicles/' + fixture.vehicle + '/passport');
  109 |     const qr = page.getByAltText('QR code linking to this private vehicle passport');
  110 |     await expect(qr).toBeVisible();
  111 |     expect(await qr.evaluate(image => image.complete && image.naturalWidth > 0)).toBe(true);
  112 | });
  113 | 
  114 | test('customer shares and revokes a temporary service passport for a verified shop', async ({ page, browser }, testInfo) => {
  115 |     test.setTimeout(90000);
  116 |     const fixture = await signIn(page, 'customer');
  117 |     await page.goto('/customer/vehicles/' + fixture.vehicle + '/passport');
  118 |     await page.getByRole('button', { name: 'Create 24-hour shop link' }).click();
  119 |     const sharedUrl = await page.getByLabel('Shop sharing link').inputValue();
  120 |     expect(sharedUrl).toContain('/shop/shared-passports/');
  121 |     await page.screenshot({ path: testInfo.outputPath('customer-shared-passport.png'), fullPage: true, mask: [page.locator('#passport-share-url')] });
  122 | 
  123 |     const shopContext = await browser.newContext();
  124 |     try {
  125 |         const shopPage = await shopContext.newPage();
  126 |         await signIn(shopPage, 'shop_owner');
  127 |         await shopPage.goto(sharedUrl);
  128 |         await expect(shopPage.getByRole('heading', { name: 'Shared service passport' })).toBeVisible();
  129 |         await expect(shopPage.getByText('BROWSER-001')).toBeVisible();
  130 |         await expect(shopPage.getByRole('link', { name: 'Review shop appointments' })).toBeVisible();
  131 |         await shopPage.screenshot({ path: testInfo.outputPath('shop-shared-passport.png'), fullPage: true });
  132 | 
  133 |         page.once('dialog', dialog => dialog.accept());
  134 |         await page.getByRole('button', { name: 'Revoke access' }).click();
  135 |         await expect(page.getByRole('button', { name: 'Create 24-hour shop link' })).toBeVisible();
  136 |         const revokedResponse = await shopPage.goto(sharedUrl);
  137 |         expect(revokedResponse.status()).toBe(404);
  138 |     } finally {
  139 |         await shopContext.close();
  140 |     }
  141 | });
  142 | 
  143 | test('booking shows the service price and checks the selected shop schedule', async ({ page }) => {
  144 |     const fixture = await signIn(page, 'customer');
  145 |     await page.goto('/customer/appointments/create');
  146 |     await page.locator('#shop_id').selectOption(String(fixture.shop));
  147 |     await page.locator('#service_id').selectOption(String(fixture.service));
  148 |     await expect(page.locator('#booking-service-summary')).toContainText('₱900.00');
  149 |     await page.locator('#appointment_date').fill(await page.locator('#appointment_date').getAttribute('min'));
  150 |     await expect(page.locator('#time-suggestions button').first()).toBeVisible();
  151 |     await page.locator('#time-suggestions button').first().click();
  152 |     await expect(page.locator('#appointment_time')).toHaveValue('08:00');
  153 |     await expect(page.locator('#slot-feedback')).toContainText('The shop still needs to confirm');
  154 | });
  155 | 
  156 | test('customer sees shop payment details and the review steps', async ({ page }) => {
  157 |     await signIn(page, 'customer');
  158 |     await page.goto('/customer/invoices');
  159 |     await expect(page.getByText('This form does not transfer money.')).toBeVisible();
  160 |     await page.locator('[data-payment-method]').first().selectOption('gcash');
  161 |     await expect(page.getByText('Send GCash to 09171234567')).toBeVisible();
  162 |     await page.locator('[data-payment-method]').first().selectOption('bank_transfer');
  163 |     await expect(page.getByText('Test Bank')).toBeVisible();
  164 | });
  165 | 
  166 | test('customer dashboard makes primary actions clear', async ({ page }, testInfo) => {
  167 |     await signIn(page, 'customer');
  168 |     await expect(page.getByRole('heading', { name: /Good (morning|afternoon|evening)/ })).toBeVisible();
  169 |     await expect(page.locator('#main-content').getByRole('link', { name: 'Find a nearby shop' })).toBeVisible();
```