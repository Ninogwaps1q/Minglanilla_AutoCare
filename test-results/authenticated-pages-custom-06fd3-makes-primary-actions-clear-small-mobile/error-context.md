# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: authenticated-pages.spec.js >> customer dashboard makes primary actions clear
- Location: tests\Browser\authenticated-pages.spec.js:166:1

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: locator('#main-content').getByRole('link', { name: 'Find a nearby shop' })
Expected: visible
Timeout: 5000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" locator('#main-content').getByRole('link', { name: 'Find a nearby shop' }) with timeout 5000ms
  - waiting for locator('#main-content').getByRole('link', { name: 'Find a nearby shop' })

```

```yaml
- link "Skip to content":
  - /url: "#main-content"
- banner:
  - button "Open navigation"
  - link "Notifications, 2 unread":
    - /url: http://127.0.0.1:8877/customer/notifications
  - button "Browser customer"
- main:
  - region "Good morning, Browser.":
    - paragraph: Friday, October 16
    - heading "Good morning, Browser." [level=1]
    - paragraph: You have a payment to review. Open your invoices to see the amount and next step.
    - link "Review payments":
      - /url: http://127.0.0.1:8877/customer/invoices
    - link "Book a service":
      - /url: http://127.0.0.1:8877/customer/appointments/create
  - region "Your care summary":
    - heading "Your care summary" [level=2]
    - paragraph: A quick look at what needs your attention.
    - link "4 My vehicles":
      - /url: http://127.0.0.1:8877/customer/vehicles
      - strong: "4"
      - text: My vehicles
    - link "0 Upcoming visits":
      - /url: http://127.0.0.1:8877/customer/appointments
      - strong: "0"
      - text: Upcoming visits
    - link "3 Active repairs":
      - /url: http://127.0.0.1:8877/customer/repairs
      - strong: "3"
      - text: Active repairs
    - link "0 Saved shops":
      - /url: http://127.0.0.1:8877/customer/favorites
      - strong: "0"
      - text: Saved shops
  - region "What’s next":
    - heading "What’s next" [level=2]
    - paragraph: Your nearest scheduled activity and current repair progress.
    - link "All appointments":
      - /url: http://127.0.0.1:8877/customer/appointments
    - heading "No upcoming service visit" [level=3]
    - paragraph: Choose a verified shop and request a time that works for you.
    - link "Book your first service":
      - /url: http://127.0.0.1:8877/customer/appointments/create
    - article:
      - strong: Pending
      - heading "BROWSER-001 is with Browser Test AutoCare" [level=3]
      - paragraph: Complete Browser booking mobile
      - link "Track repair progress":
        - /url: http://127.0.0.1:8877/customer/repairs
  - region "My garage":
    - heading "My garage" [level=2]
    - paragraph: Your most recently added vehicle.
    - img "E2E-small-mobile vehicle photo"
    - strong: 2022 Toyota Vios
    - text: E2E-small-mobile · 25,000 km
    - link "Open vehicle details":
      - /url: http://127.0.0.1:8877/customer/vehicles/4
  - region "Need attention":
    - heading "Need attention" [level=2]
    - link "Unpaid invoices 1":
      - /url: http://127.0.0.1:8877/customer/invoices
      - text: Unpaid invoices
      - strong: "1"
    - link "Updates":
      - /url: http://127.0.0.1:8877/customer/notifications
    - link "Roadside help":
      - /url: http://127.0.0.1:8877/customer/emergency-requests
- contentinfo: Minglanilla AutoCare Hub · Customer workspace
- navigation "Customer quick navigation":
  - link "Home":
    - /url: http://127.0.0.1:8877/dashboard
  - link "Shops":
    - /url: http://127.0.0.1:8877/map
  - link "Book":
    - /url: http://127.0.0.1:8877/customer/appointments/create
  - link "Garage":
    - /url: http://127.0.0.1:8877/customer/vehicles
  - link "Repairs":
    - /url: http://127.0.0.1:8877/customer/repairs
- button "Open AutoCare AI assistant":
  - strong: Ask AutoCare AI
  - text: Need help?
```

# Test source

```ts
  69  |         await expect(notificationLink).toBeVisible();
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
> 169 |     await expect(page.locator('#main-content').getByRole('link', { name: 'Find a nearby shop' })).toBeVisible();
      |                                                                                                   ^ Error: expect(locator).toBeVisible() failed
  170 |     await expect(page.locator('#main-content').getByRole('link', { name: 'Book a service' })).toBeVisible();
  171 |     await expect(page.getByRole('heading', { name: 'Your care summary' })).toBeVisible();
  172 |     const aiLauncher = page.getByRole('button', { name: 'Open AutoCare AI assistant' });
  173 |     await expect(aiLauncher).toBeVisible();
  174 |     await expect(page.locator('#portalNavigation').getByRole('link', { name: 'Ask AutoCare AI' })).toHaveCount(0);
  175 |     const dashboardUrl = page.url();
  176 |     await aiLauncher.click();
  177 |     await expect(page.getByRole('region', { name: 'AutoCare AI chat' })).toBeVisible();
  178 |     await expect(page.getByLabel('Ask AutoCare AI')).toBeVisible();
  179 |     expect(page.url()).toBe(dashboardUrl);
  180 |     await page.getByLabel('Ask AutoCare AI').fill('Please book a service for my car');
  181 |     await page.getByRole('button', { name: 'Send question' }).click();
  182 |     const aiPanel = page.getByRole('region', { name: 'AutoCare AI chat' });
  183 |     await expect(aiPanel).toBeVisible();
  184 |     await expect(aiPanel.getByRole('link', { name: 'Book a service' }).last()).toBeVisible();
  185 |     expect(page.url()).toBe(dashboardUrl);
  186 |     await page.getByRole('button', { name: 'Close AutoCare AI chat' }).click();
  187 |     await expect(page.getByRole('region', { name: 'AutoCare AI chat' })).toBeHidden();
  188 |     await expect(page.getByRole('heading', { name: 'What’s next' })).toBeVisible();
  189 |     expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(await page.evaluate(() => document.documentElement.clientWidth + 1));
  190 |     if (testInfo.project.name !== 'desktop') {
  191 |         await expect(page.locator('.customer-mobile-nav')).toBeVisible();
  192 |         await expect(page.locator('.customer-mobile-nav').getByRole('link', { name: 'Book' })).toBeVisible();
  193 |     }
  194 |     await page.screenshot({ path: testInfo.outputPath('customer-dashboard.png'), fullPage: true });
  195 | });
  196 | 
  197 | test('dark theme keeps customer shop and admin workspaces readable', async ({ page }, testInfo) => {
  198 |     await page.addInitScript(() => localStorage.setItem('autocare-theme', 'dark'));
  199 | 
  200 |     for (const [role, path] of [['customer', '/dashboard'], ['shop_owner', '/shop/dashboard'], ['admin', '/admin/dashboard']]) {
  201 |         await signIn(page, role);
  202 |         await page.goto(path);
  203 |         await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  204 |         await expect(page.locator('html')).toHaveAttribute('data-bs-theme', 'dark');
  205 |         expect(await page.locator('.portal-content').evaluate(element => getComputedStyle(element).color)).toBe('rgb(242, 246, 251)');
  206 |         const secondaryText = page.locator('.portal-content .text-secondary').first();
  207 |         if (await secondaryText.count()) {
  208 |             expect(await secondaryText.evaluate(element => getComputedStyle(element).color)).toBe('rgb(194, 206, 222)');
  209 |         }
  210 |         expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(await page.evaluate(() => document.documentElement.clientWidth + 1));
  211 |         await page.screenshot({ path: testInfo.outputPath(`${role}-dark.png`), fullPage: true });
  212 |         await page.context().clearCookies();
  213 |     }
  214 | });
  215 | 
  216 | test('AI assistant starts without saved database chat history', async ({ page }, testInfo) => {
  217 |     await signIn(page, 'customer');
  218 |     await page.goto('/customer/ai-assistant');
  219 |     await expect(page.getByRole('heading', { name: 'Ask AutoCare AI' })).toBeVisible();
  220 |     await expect(page.getByRole('heading', { name: 'How can I help with your vehicle today?' })).toBeVisible();
  221 |     await expect(page.locator('.ai-message-user')).toHaveCount(0);
  222 |     await expect(page.locator('.ai-message-assistant')).toHaveCount(0);
  223 |     await expect(page.getByLabel('Ask AutoCare AI a question')).toBeVisible();
  224 |     expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(await page.evaluate(() => document.documentElement.clientWidth + 1));
  225 |     await page.screenshot({ path: testInfo.outputPath('ai-assistant.png'), fullPage: true });
  226 | });
  227 | 
  228 | test('shop and admin dashboards make daily priorities clear', async ({ page }, testInfo) => {
  229 |     await signIn(page, 'shop_owner');
  230 |     await page.goto('/shop/dashboard');
  231 |     await expect(page.getByRole('heading', { name: 'Browser Test AutoCare' })).toBeVisible();
  232 |     await expect(page.getByRole('heading', { name: 'Today at a glance' })).toBeVisible();
  233 |     await expect(page.getByRole('link', { name: 'Review appointments' })).toBeVisible();
  234 |     await expect(page.getByRole('link', { name: 'Create repair job' })).toBeVisible();
  235 |     await expect(page.getByRole('heading', { name: 'Quick actions' })).toBeVisible();
  236 |     expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(await page.evaluate(() => document.documentElement.clientWidth + 1));
  237 |     if (testInfo.project.name !== 'desktop') {
  238 |         await expect(page.locator('.shop-mobile-nav')).toBeVisible();
  239 |         await expect(page.locator('.shop-mobile-nav').getByRole('link', { name: 'New job' })).toBeVisible();
  240 |     }
  241 |     await page.screenshot({ path: testInfo.outputPath('shop-dashboard.png'), fullPage: true });
  242 | 
  243 |     await page.context().clearCookies();
  244 |     await signIn(page, 'admin');
  245 |     await page.goto('/admin/dashboard');
  246 |     await expect(page.getByRole('heading', { name: 'System overview' })).toBeVisible();
  247 |     await expect(page.getByRole('heading', { name: 'Shop registrations to review' })).toBeVisible();
  248 |     await expect(page.getByRole('heading', { name: 'Operations requiring attention' })).toBeVisible();
  249 |     await expect(page.getByRole('link', { name: 'Open operations monitor' })).toBeVisible();
  250 |     expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(await page.evaluate(() => document.documentElement.clientWidth + 1));
  251 |     if (testInfo.project.name !== 'desktop') {
  252 |         await expect(page.locator('.admin-mobile-nav')).toBeVisible();
  253 |         await expect(page.locator('.admin-mobile-nav').getByRole('link', { name: 'Monitor' })).toBeVisible();
  254 |     }
  255 |     await page.screenshot({ path: testInfo.outputPath('admin-dashboard.png'), fullPage: true });
  256 | });
  257 | 
  258 | test('customer books, shop confirms, and admin monitors the same appointment', async ({ browser }, testInfo) => {
  259 |     test.setTimeout(120000);
  260 |     const context = await browser.newContext(testInfo.project.use);
  261 |     const page = await context.newPage();
  262 |     try {
  263 |         const fixture = await signIn(page, 'customer');
  264 |         const reason = 'Browser booking ' + testInfo.project.name;
  265 |         const date = new Date(Date.now() + 86400000).toISOString().slice(0, 10);
  266 |         const time = { desktop: '10:00', mobile: '11:00', 'small-mobile': '12:00' }[testInfo.project.name];
  267 |         await page.goto('/customer/appointments/create');
  268 |         await page.getByLabel('Service shop', { exact: true }).selectOption(String(fixture.shop));
  269 |         await page.getByLabel('Service', { exact: true }).selectOption(String(fixture.service));
```