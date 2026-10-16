# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: authenticated-pages.spec.js >> shop owner records part usage for an active repair
- Location: tests\Browser\authenticated-pages.spec.js:354:1

# Error details

```
Test timeout of 30000ms exceeded.
```

```
Error: locator.selectOption: Test timeout of 30000ms exceeded.
Call log:
  - waiting for getByRole('row').filter({ hasText: 'Browser Brake Pad' }).getByLabel('Repair job for Browser Brake Pad')

```

# Page snapshot

```yaml
- generic [active] [ref=f2e1]:
  - link "Skip to content" [ref=f2e2] [cursor=pointer]:
    - /url: "#main-content"
  - generic [ref=f2e3]:
    - banner [ref=f2e4]:
      - button "Open navigation" [ref=f2e5] [cursor=pointer]:
        - generic [aria-hidden] [ref=f2e6]: 
      - text: 
      - generic [ref=f2e7]:
        - link "Notifications, 2 unread" [ref=f2e8] [cursor=pointer]:
          - /url: http://127.0.0.1:8877/shop/notifications
          - generic [aria-hidden] [ref=f2e9]: 
          - generic [aria-hidden] [ref=f2e10]: "2"
        - generic [ref=f2e11]:
          - button "Browser shop owner" [ref=f2e12] [cursor=pointer]:
            - generic [aria-hidden] [ref=f2e13]: 
          - text:  
    - main [ref=f2e15]:
      - generic [ref=f2e16]:
        - heading "Parts inventory" [level=1] [ref=f2e17]
        - paragraph [ref=f2e18]: Add spare parts, manage stock, and record the parts used for each active repair.
      - generic [ref=f2e19]:
        - generic [ref=f2e20]:
          - generic [ref=f2e21]: Part name
          - textbox "Part name" [ref=f2e22]
        - generic [ref=f2e23]:
          - generic [ref=f2e24]: Part number
          - textbox "Part number" [ref=f2e25]
        - generic [ref=f2e26]:
          - generic [ref=f2e27]: Brand
          - textbox "Brand" [ref=f2e28]
        - generic [ref=f2e29]:
          - generic [ref=f2e30]: Opening stock
          - spinbutton "Opening stock" [ref=f2e31]: "0"
        - generic [ref=f2e32]:
          - generic [ref=f2e33]: Minimum stock
          - spinbutton "Minimum stock" [ref=f2e34]: "0"
        - generic [ref=f2e35]:
          - generic [ref=f2e36]: Purchase price
          - spinbutton "Purchase price" [ref=f2e37]: "0"
        - generic [ref=f2e38]:
          - generic [ref=f2e39]: Selling price
          - spinbutton "Selling price" [ref=f2e40]: "0"
        - generic [ref=f2e41]:
          - generic [ref=f2e42]: Supplier
          - textbox "Supplier" [ref=f2e43]
        - button "Add part" [ref=f2e45] [cursor=pointer]
      - region "Scrollable table" [ref=f2e47]:
        - table [ref=f2e48]:
          - rowgroup [ref=f2e49]:
            - row [ref=f2e50]:
              - columnheader "Part" [ref=f2e51]
              - columnheader "Stock" [ref=f2e52]
              - columnheader "Prices" [ref=f2e53]
              - columnheader "Adjust stock" [ref=f2e54]
              - columnheader "Use for repair" [ref=f2e55]
          - rowgroup [ref=f2e56]:
            - row [ref=f2e57]:
              - rowheader [ref=f2e58]:
                - strong [ref=f2e59]: Browser Brake Pad
              - cell "10 / min 2" [ref=f2e60]
              - cell "PHP 100.00 → PHP 150.00" [ref=f2e61]
              - cell [ref=f2e62]:
                - generic [ref=f2e63]:
                  - generic [ref=f2e64]:
                    - generic [ref=f2e65]: Stock change (+/−)
                    - spinbutton "Stock change (+/−)" [ref=f2e66]
                  - button "Update stock" [ref=f2e67] [cursor=pointer]
              - cell [ref=f2e68]:
                - generic [ref=f2e69]:
                  - generic [ref=f2e70]:
                    - generic [ref=f2e71]: Repair job
                    - combobox "Repair job" [ref=f2e72]:
                      - option "Select repair" [selected]
                      - option "JOB-BROWSER-STOCK"
                  - generic [ref=f2e73]:
                    - generic [ref=f2e74]: Units used
                    - spinbutton "Units used" [ref=f2e75]
                  - button "Record usage" [ref=f2e76] [cursor=pointer]
    - contentinfo [ref=f2e77]: Minglanilla AutoCare Hub · Shop owner workspace
  - navigation "Shop quick navigation" [ref=f2e78]:
    - link "Home" [ref=f2e79] [cursor=pointer]:
      - /url: http://127.0.0.1:8877/shop/dashboard
      - generic [aria-hidden] [ref=f2e80]: 
    - link "Bookings" [ref=f2e82] [cursor=pointer]:
      - /url: http://127.0.0.1:8877/shop/appointments
      - generic [aria-hidden] [ref=f2e83]: 
    - link "New job" [ref=f2e85] [cursor=pointer]:
      - /url: http://127.0.0.1:8877/shop/repair-jobs/create
      - generic [aria-hidden] [ref=f2e86]: 
    - link "Repairs" [ref=f2e88] [cursor=pointer]:
      - /url: http://127.0.0.1:8877/shop/repair-jobs
      - generic [aria-hidden] [ref=f2e89]: 
    - link "Messages, 0 unread" [ref=f2e91] [cursor=pointer]:
      - /url: http://127.0.0.1:8877/shop/messages
      - generic [aria-hidden] [ref=f2e92]: 
      - generic [ref=f2e93]: Messages
```

# Test source

```ts
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
  345 |     await shop.getByLabel('Reason for suspending Browser Test AutoCare').fill('Browser moderation test');
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
> 359 |     await row.getByLabel('Repair job for Browser Brake Pad').selectOption(String(fixture.repairJob));
      |                                                              ^ Error: locator.selectOption: Test timeout of 30000ms exceeded.
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