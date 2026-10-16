# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: authenticated-pages.spec.js >> admin manages categories and suspends then restores a public shop
- Location: tests\Browser\authenticated-pages.spec.js:326:1

# Error details

```
Test timeout of 60000ms exceeded.
```

```
Error: locator.fill: Test timeout of 60000ms exceeded.
Call log:
  - waiting for getByRole('row').filter({ hasText: 'Browser Test AutoCare' }).getByLabel('Reason for suspending Browser Test AutoCare')

```

# Page snapshot

```yaml
- generic [active] [ref=f6e1]:
  - link "Skip to content" [ref=f6e2] [cursor=pointer]:
    - /url: "#main-content"
  - generic [ref=f6e3]:
    - banner [ref=f6e4]:
      - button "Open navigation" [ref=f6e5] [cursor=pointer]:
        - generic [aria-hidden] [ref=f6e6]: 
      - text: 
      - generic [ref=f6e8]:
        - button "Browser admin" [ref=f6e9] [cursor=pointer]:
          - generic [aria-hidden] [ref=f6e10]: 
        - text:  
    - main [ref=f6e12]:
      - generic [ref=f6e13]:
        - heading "Shop verification" [level=1] [ref=f6e14]
        - paragraph [ref=f6e15]: Review business documents, decide on registrations, and manage approved shops.
      - generic [ref=f6e16]:
        - generic [ref=f6e17]:
          - generic [ref=f6e18]: Show shops
          - combobox "Show shops" [ref=f6e19]:
            - option "All statuses" [selected]
            - option "Awaiting review"
            - option "Approved"
            - option "Rejected"
        - button "Apply filter" [ref=f6e20] [cursor=pointer]
      - article [ref=f6e22]:
        - generic [ref=f6e23]:
          - generic [ref=f6e24]:
            - heading "Browser Test AutoCare" [level=2] [ref=f6e25]
            - paragraph [ref=f6e26]: Browser shop owner · Madge Fork
          - generic [ref=f6e27]:
            - generic [ref=f6e28]: approved
            - generic [ref=f6e29]: active
        - generic [ref=f6e30]:
          - strong [ref=f6e31]: "Document check:"
          - text: One or both required permits are missing, expired, or not verified. Approval still requires your own review and decision.
        - group [ref=f6e32]:
          - generic "Review documents (0)" [ref=f6e33] [cursor=pointer]
        - generic [ref=f6e35]:
          - generic [ref=f6e36]:
            - generic [ref=f6e37]: Reason for suspending Browser Test AutoCare
            - textbox "Reason for suspending Browser Test AutoCare" [ref=f6e38]:
              - /placeholder: Shared with the shop owner
          - button "Suspend shop" [ref=f6e39] [cursor=pointer]
    - contentinfo [ref=f6e40]: Minglanilla AutoCare Hub · Administration workspace
  - navigation "Admin quick navigation" [ref=f6e41]:
    - link "Overview" [ref=f6e42] [cursor=pointer]:
      - /url: http://127.0.0.1:8877/admin/dashboard
      - generic [aria-hidden] [ref=f6e43]: 
    - link "Shops" [ref=f6e45] [cursor=pointer]:
      - /url: http://127.0.0.1:8877/admin/shops
      - generic [aria-hidden] [ref=f6e46]: 
    - link "Monitor" [ref=f6e48] [cursor=pointer]:
      - /url: http://127.0.0.1:8877/admin/monitoring
      - generic [aria-hidden] [ref=f6e49]: 
    - link "Users" [ref=f6e51] [cursor=pointer]:
      - /url: http://127.0.0.1:8877/admin/users
      - generic [aria-hidden] [ref=f6e52]: 
    - link "Reports" [ref=f6e54] [cursor=pointer]:
      - /url: http://127.0.0.1:8877/admin/reports
      - generic [aria-hidden] [ref=f6e55]: 
```

# Test source

```ts
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
  324 | });
  325 | 
  326 | test('admin manages categories and suspends then restores a public shop', async ({ page }, testInfo) => {
  327 |     test.setTimeout(60000);
  328 |     const fixture = await signIn(page, 'admin');
  329 |     const category = 'Browser category ' + testInfo.project.name;
  330 |     await page.goto('/admin/service-categories');
  331 |     await page.getByLabel('Category name', { exact: true }).fill(category);
  332 |     await page.getByRole('button', { name: 'Add category', exact: true }).click();
  333 |     const section = page.getByRole('region', { name: category, exact: true });
  334 |     await expect(section).toBeVisible();
  335 |     await section.getByLabel(/Category name/).fill(category + ' updated');
  336 |     await section.getByRole('button', { name: 'Save category' }).click();
  337 |     const updated = page.getByRole('region', { name: category + ' updated', exact: true });
  338 |     await expect(updated).toBeVisible();
  339 |     page.once('dialog', dialog => dialog.accept());
  340 |     await updated.getByRole('button', { name: 'Delete unused category' }).click();
  341 |     await expect(updated).toHaveCount(0);
  342 | 
  343 |     await page.goto('/admin/shops');
  344 |     const shop = page.getByRole('row').filter({ hasText: 'Browser Test AutoCare' });
> 345 |     await shop.getByLabel('Reason for suspending Browser Test AutoCare').fill('Browser moderation test');
      |                                                                          ^ Error: locator.fill: Test timeout of 60000ms exceeded.
  346 |     await shop.getByRole('button', { name: 'Suspend shop', exact: true }).click();
  347 |     await expect(shop.getByRole('button', { name: 'Reactivate shop' })).toBeVisible();
  348 |     expect((await page.request.get('/shops/' + fixture.shop)).status()).toBe(404);
  349 |     await shop.getByRole('button', { name: 'Reactivate shop' }).click();
  350 |     await expect(shop.getByRole('button', { name: 'Suspend shop', exact: true })).toBeVisible();
  351 |     expect((await page.request.get('/shops/' + fixture.shop)).status()).toBe(200);
  352 | });
  353 | 
  354 | test('shop owner records part usage for an active repair', async ({ page }) => {
  355 |     const fixture = await signIn(page, 'shop_owner');
  356 |     await page.goto('/shop/inventory');
  357 |     const row = page.getByRole('row').filter({ hasText: 'Browser Brake Pad' });
  358 |     const stock = Number((await row.getByRole('cell').first().textContent()).match(/\d+/)[0]);
  359 |     await row.getByLabel('Repair job for Browser Brake Pad').selectOption(String(fixture.repairJob));
  360 |     await row.getByLabel('Units of Browser Brake Pad used').fill('1');
  361 |     await row.getByRole('button', { name: 'Record usage' }).click();
  362 |     await expect(page.getByText('Part usage recorded for the repair job.')).toBeVisible();
  363 |     await expect(row.getByRole('cell').first()).toContainText(String(stock - 1));
  364 | });
  365 | 
  366 | test('customer and shop owner can download their invoice PDF', async ({ page }) => {
  367 |     const fixture = JSON.parse(process.env.BROWSER_FIXTURE);
  368 |     for (const [role, path] of [['customer', 'customer'], ['shop_owner', 'shop']]) {
  369 |         await signIn(page, role);
  370 |         await page.goto(`/${path}/invoices/${fixture.invoice}`);
  371 |         await expect(page.getByText('Browser brake service')).toBeVisible();
  372 |         const downloadPromise = page.waitForEvent('download');
  373 |         await page.getByRole('link', { name: 'Download PDF' }).click();
  374 |         const download = await downloadPromise;
  375 |         expect(download.suggestedFilename()).toBe(`invoice-${fixture.invoice}.pdf`);
  376 |         expect(await download.failure()).toBeNull();
  377 |         await page.context().clearCookies();
  378 |     }
  379 | });
  380 | 
  381 | test('uploaded customer and shop images render from public storage', async ({ page }) => {
  382 |     const fixture = await signIn(page, 'customer');
  383 |     const image = {
  384 |         name: 'browser-profile.png',
  385 |         mimeType: 'image/png',
  386 |         buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9JQqkAAAAASUVORK5CYII=', 'base64'),
  387 |     };
  388 | 
  389 |     await page.goto('/customer/profile');
  390 |     await page.locator('#profile_photo').setInputFiles(image);
  391 |     await page.getByRole('button', { name: 'Save profile' }).click();
  392 |     await expect(page.getByText('Your profile has been updated.')).toBeVisible();
  393 |     await expect(page.locator('.profile-photo-preview')).toBeVisible();
  394 |     expect(await page.locator('.profile-photo-preview').evaluate(element => element.complete && element.naturalWidth > 0)).toBe(true);
  395 |     expect(await page.locator('.account-avatar').evaluate(element => element.complete && element.naturalWidth > 0)).toBe(true);
  396 | 
  397 |     await page.context().clearCookies();
  398 |     await signIn(page, 'shop_owner');
  399 |     await page.goto('/shop/profile');
  400 |     await page.locator('#logo').setInputFiles({ ...image, name: 'browser-logo.png' });
  401 |     await page.locator('#cover_photo').setInputFiles({ ...image, name: 'browser-cover.png' });
  402 |     await page.getByRole('button', { name: 'Save profile' }).click();
  403 |     await expect(page.getByText('Shop profile updated.')).toBeVisible();
  404 |     await page.goto('/shops/' + fixture.shop);
  405 |     for (const selector of ['.shop-public-logo', '.shop-public-cover']) {
  406 |         await expect(page.locator(selector)).toBeVisible();
  407 |         expect(await page.locator(selector).evaluate(element => element.complete && element.naturalWidth > 0)).toBe(true);
  408 |     }
  409 | });
  410 | 
```