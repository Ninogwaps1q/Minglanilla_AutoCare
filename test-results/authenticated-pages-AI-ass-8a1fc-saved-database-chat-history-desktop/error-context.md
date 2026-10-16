# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: authenticated-pages.spec.js >> AI assistant starts without saved database chat history
- Location: tests\Browser\authenticated-pages.spec.js:216:1

# Error details

```
Error: expect(locator).toBeVisible() failed

Locator: getByLabel('Ask AutoCare AI a question')
Expected: visible
Timeout: 5000ms
Error: element(s) not found

Call log:
  - Expect "toBeVisible" getByLabel('Ask AutoCare AI a question') with timeout 5000ms
  - waiting for getByLabel('Ask AutoCare AI a question')

```

```yaml
- link "Skip to content":
  - /url: "#main-content"
- complementary "Your workspace":
  - link "Minglanilla AutoCare Hub":
    - /url: http://127.0.0.1:8877/dashboard
    - text: Minglanilla
    - strong: AutoCare Hub
  - navigation "Customer navigation":
    - text: Start here
    - link "Home":
      - /url: http://127.0.0.1:8877/dashboard
    - link "Find nearby shops":
      - /url: http://127.0.0.1:8877/map
    - link "Book a service":
      - /url: http://127.0.0.1:8877/customer/appointments/create
    - text: Track my service
    - link "Appointments":
      - /url: http://127.0.0.1:8877/customer/appointments
    - link "Repair progress":
      - /url: http://127.0.0.1:8877/customer/repairs
    - link "Estimates":
      - /url: http://127.0.0.1:8877/customer/quotations
    - link "Payments":
      - /url: http://127.0.0.1:8877/customer/invoices
    - group: My vehicle
    - group: Help & account
  - link "Public website":
    - /url: http://127.0.0.1:8877
- banner:
  - text: Customer portal
  - strong: Ask AutoCare AI
  - navigation "Customer quick actions":
    - link "Find shop":
      - /url: http://127.0.0.1:8877/map
    - link "Book service":
      - /url: http://127.0.0.1:8877/customer/appointments/create
  - link "Notifications, 0 unread":
    - /url: http://127.0.0.1:8877/customer/notifications
    - text: Notifications
  - button "Browser customer"
- main:
  - heading "Ask AutoCare AI" [level=1]
  - paragraph: Get clear guidance about bookings, repairs, and caring for your vehicle.
  - text: Ready to help
  - region "AutoCare Assistant":
    - heading "AutoCare Assistant" [level=2]
    - paragraph: Helpful guidance based on your AutoCare records
    - log:
      - heading "How can I help with your vehicle today?" [level=3]
      - paragraph: Try asking how to book a service, understand a repair status, or prepare for maintenance.
    - text: Your question for AutoCare AI
    - textbox "Your question for AutoCare AI":
      - /placeholder: Ask about your booking, repair, or vehicle care…
    - button "Send question": Send
    - paragraph: For guidance only. A qualified technician should inspect safety or mechanical concerns.
- contentinfo: Minglanilla AutoCare Hub · Customer workspace
```

# Test source

```ts
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
> 223 |     await expect(page.getByLabel('Ask AutoCare AI a question')).toBeVisible();
      |                                                                 ^ Error: expect(locator).toBeVisible() failed
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
  270 |         await page.getByLabel('Vehicle', { exact: true }).selectOption(String(fixture.vehicle));
  271 |         await page.getByLabel('Preferred date').fill(date);
  272 |         await page.getByLabel('Preferred time').fill(time);
  273 |         await page.getByLabel('What does your vehicle need?').fill(reason);
  274 |         await page.getByRole('button', { name: 'Request appointment' }).click();
  275 |         await expect(page).toHaveURL(/customer\/appointments$/);
  276 |         await expect(page.getByText(/requested successfully/)).toBeVisible();
  277 |         const confirmation = await page.getByText(/requested successfully/).textContent();
  278 |         const appointmentId = confirmation.match(/Appointment #(\d+)/)[1];
  279 | 
  280 |         await context.clearCookies();
  281 |         await signIn(page, 'shop_owner');
  282 |         await page.goto('/shop/appointments');
  283 |         const row = page.getByRole('row').filter({ hasText: reason });
  284 |         await expect(row.getByRole('option', { name: 'Complete', exact: true })).toHaveCount(0);
  285 |         await row.getByLabel('Appointment status').selectOption('confirmed');
  286 |         await row.getByRole('button', { name: 'Update status', exact: true }).click();
  287 |         await expect(row.locator('.badge')).toHaveText('Ready for repair');
  288 |         const startRepairLink = row.getByRole('link', { name: 'Start repair job' });
  289 |         await expect(startRepairLink).toBeVisible();
  290 |         expect(await startRepairLink.evaluate(link => link.getBoundingClientRect().right <= document.documentElement.clientWidth + 1)).toBe(true);
  291 |         await page.screenshot({ path: testInfo.outputPath('confirmed-shop-appointment.png'), fullPage: true });
  292 |         await expect(row.getByRole('option', { name: 'Confirm', exact: true })).toHaveCount(0);
  293 | 
  294 |         await context.clearCookies();
  295 |         await signIn(page, 'admin');
  296 |         await page.goto('/admin/monitoring?module=appointments&status=confirmed');
  297 |         const monitored = page.getByRole('row').filter({ has: page.getByRole('rowheader', { name: '#' + appointmentId, exact: true }) });
  298 |         await expect(monitored.getByRole('cell', { name: 'Browser Test AutoCare', exact: true })).toBeVisible();
  299 |         await expect(monitored.getByRole('cell', { name: 'Confirmed', exact: true })).toBeVisible();
  300 | 
  301 |         await context.clearCookies();
  302 |         await signIn(page, 'shop_owner');
  303 |         await page.goto('/shop/appointments?status=confirmed');
  304 |         await page.getByRole('row').filter({ hasText: reason }).getByRole('link', { name: 'Start repair job' }).click();
  305 |         await expect(page.locator('#appointment_id')).toHaveValue(appointmentId);
  306 |         await page.getByLabel('Work description').fill('Complete ' + reason);
  307 |         await page.getByRole('button', { name: 'Create repair job' }).click();
  308 |         await expect(page.getByText(/Repair job .* created/)).toBeVisible();
  309 |         const jobNumber = (await page.getByText(/Repair job .* created/).textContent()).match(/Repair job (\S+) created/)[1];
  310 |         await page.goto('/shop/appointments?status=in_progress');
  311 |         await expect(page.getByRole('row').filter({ hasText: reason }).getByRole('link', { name: 'View repair' })).toBeVisible();
  312 | 
  313 |         await context.clearCookies();
  314 |         await signIn(page, 'customer');
  315 |         await page.goto('/customer/appointments?status=in_progress');
  316 |         const customerAppointment = page.locator('article').filter({ hasText: reason });
  317 |         await expect(customerAppointment.getByRole('link', { name: 'View repair progress' })).toBeVisible();
  318 |         await page.screenshot({ path: testInfo.outputPath('customer-appointment-in-repair.png'), fullPage: true });
  319 |         await customerAppointment.getByRole('link', { name: 'View repair progress' }).click();
  320 |         await expect(page.getByRole('heading', { name: jobNumber })).toBeVisible();
  321 |     } finally {
  322 |         await context.close();
  323 |     }
```