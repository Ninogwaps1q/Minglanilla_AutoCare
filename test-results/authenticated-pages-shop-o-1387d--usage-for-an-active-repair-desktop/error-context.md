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
  - complementary "Your workspace" [ref=f2e3]:
    - generic [ref=f2e4]:
      - link [ref=f2e5] [cursor=pointer]:
        - /url: http://127.0.0.1:8877/shop/dashboard
        - generic [ref=f2e6]:
          - text: Minglanilla
          - strong [ref=f2e7]: AutoCare Hub
      - navigation "Shop owner navigation" [ref=f2e8]:
        - generic [ref=f2e9]:
          - generic [ref=f2e10]: Today’s work
          - link "Home" [ref=f2e11] [cursor=pointer]:
            - /url: http://127.0.0.1:8877/shop/dashboard
            - generic [aria-hidden] [ref=f2e12]: 
          - link "Appointments" [ref=f2e14] [cursor=pointer]:
            - /url: http://127.0.0.1:8877/shop/appointments
            - generic [aria-hidden] [ref=f2e15]: 
          - link "Repair jobs" [ref=f2e17] [cursor=pointer]:
            - /url: http://127.0.0.1:8877/shop/repair-jobs
            - generic [aria-hidden] [ref=f2e18]: 
          - link "Estimates" [ref=f2e20] [cursor=pointer]:
            - /url: http://127.0.0.1:8877/shop/quotations
            - generic [aria-hidden] [ref=f2e21]: 
          - link "Bills & payments" [ref=f2e23] [cursor=pointer]:
            - /url: http://127.0.0.1:8877/shop/invoices
            - generic [aria-hidden] [ref=f2e24]: 
        - group [ref=f2e27]:
          - generic "Run the shop" [ref=f2e28] [cursor=pointer]:
            - text: Run the shop
            - generic [aria-hidden] [ref=f2e29]: 
          - link "Shop profile" [ref=f2e30] [cursor=pointer]:
            - /url: http://127.0.0.1:8877/shop/profile
            - generic [aria-hidden] [ref=f2e31]: 
          - link "Services & pricing" [ref=f2e33] [cursor=pointer]:
            - /url: http://127.0.0.1:8877/shop/services
            - generic [aria-hidden] [ref=f2e34]: 
          - link "Parts inventory" [ref=f2e36] [cursor=pointer]:
            - /url: http://127.0.0.1:8877/shop/inventory
            - generic [aria-hidden] [ref=f2e37]: 
          - link "Team" [ref=f2e39] [cursor=pointer]:
            - /url: http://127.0.0.1:8877/shop/technicians
            - generic [aria-hidden] [ref=f2e40]: 
          - link "Business reports" [ref=f2e42] [cursor=pointer]:
            - /url: http://127.0.0.1:8877/shop/reports
            - generic [aria-hidden] [ref=f2e43]: 
        - group [ref=f2e46]:
          - generic "Customer care" [ref=f2e47] [cursor=pointer]:
            - text: Customer care
            - generic [aria-hidden] [ref=f2e48]: 
          - text:    
      - link "Public website" [ref=f2e49] [cursor=pointer]:
        - /url: http://127.0.0.1:8877
        - generic [aria-hidden] [ref=f2e50]: 
        - text: Public website
  - generic [ref=f2e51]:
    - banner [ref=f2e52]:
      - text: 
      - generic [ref=f2e53]:
        - generic [aria-hidden] [ref=f2e54]: 
        - generic [ref=f2e55]:
          - generic [ref=f2e56]: Shop owner portal
          - strong [ref=f2e57]: Parts inventory
      - generic [ref=f2e58]:
        - link "Notifications, 1 unread" [ref=f2e59] [cursor=pointer]:
          - /url: http://127.0.0.1:8877/shop/notifications
          - generic [aria-hidden] [ref=f2e60]: 
          - generic [ref=f2e61]: Notifications
          - generic [aria-hidden] [ref=f2e62]: "1"
        - generic [ref=f2e63]:
          - button "Browser shop owner" [ref=f2e64] [cursor=pointer]:
            - generic [aria-hidden] [ref=f2e65]: 
          - text:  
    - main [ref=f2e67]:
      - generic [ref=f2e68]:
        - heading "Parts inventory" [level=1] [ref=f2e69]
        - paragraph [ref=f2e70]: Add spare parts, manage stock, and record the parts used for each active repair.
      - generic [ref=f2e71]:
        - generic [ref=f2e72]:
          - generic [ref=f2e73]: Part name
          - textbox "Part name" [ref=f2e74]
        - generic [ref=f2e75]:
          - generic [ref=f2e76]: Part number
          - textbox "Part number" [ref=f2e77]
        - generic [ref=f2e78]:
          - generic [ref=f2e79]: Brand
          - textbox "Brand" [ref=f2e80]
        - generic [ref=f2e81]:
          - generic [ref=f2e82]: Opening stock
          - spinbutton "Opening stock" [ref=f2e83]: "0"
        - generic [ref=f2e84]:
          - generic [ref=f2e85]: Minimum stock
          - spinbutton "Minimum stock" [ref=f2e86]: "0"
        - generic [ref=f2e87]:
          - generic [ref=f2e88]: Purchase price
          - spinbutton "Purchase price" [ref=f2e89]: "0"
        - generic [ref=f2e90]:
          - generic [ref=f2e91]: Selling price
          - spinbutton "Selling price" [ref=f2e92]: "0"
        - generic [ref=f2e93]:
          - generic [ref=f2e94]: Supplier
          - textbox "Supplier" [ref=f2e95]
        - button "Add part" [ref=f2e97] [cursor=pointer]
      - region "Scrollable table" [ref=f2e99]:
        - table [ref=f2e100]:
          - rowgroup [ref=f2e101]:
            - row [ref=f2e102]:
              - columnheader "Part" [ref=f2e103]
              - columnheader "Stock" [ref=f2e104]
              - columnheader "Prices" [ref=f2e105]
              - columnheader "Adjust stock" [ref=f2e106]
              - columnheader "Use for repair" [ref=f2e107]
          - rowgroup [ref=f2e108]:
            - row [ref=f2e109]:
              - rowheader [ref=f2e110]:
                - strong [ref=f2e111]: Browser Brake Pad
              - cell "10 / min 2" [ref=f2e112]
              - cell "PHP 100.00 → PHP 150.00" [ref=f2e113]
              - cell [ref=f2e114]:
                - generic [ref=f2e115]:
                  - generic [ref=f2e116]:
                    - generic [ref=f2e117]: Stock change (+/−)
                    - spinbutton "Stock change (+/−)" [ref=f2e118]
                  - button "Update stock" [ref=f2e119] [cursor=pointer]
              - cell [ref=f2e120]:
                - generic [ref=f2e121]:
                  - generic [ref=f2e122]:
                    - generic [ref=f2e123]: Repair job
                    - combobox "Repair job" [ref=f2e124]:
                      - option "Select repair" [selected]
                      - option "JOB-BROWSER-STOCK"
                  - generic [ref=f2e125]:
                    - generic [ref=f2e126]: Units used
                    - spinbutton "Units used" [ref=f2e127]
                  - button "Record usage" [ref=f2e128] [cursor=pointer]
    - contentinfo [ref=f2e129]: Minglanilla AutoCare Hub · Shop owner workspace
  - text:     
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