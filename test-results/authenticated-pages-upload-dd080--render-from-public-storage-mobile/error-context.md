# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: authenticated-pages.spec.js >> uploaded customer and shop images render from public storage
- Location: tests\Browser\authenticated-pages.spec.js:381:1

# Error details

```
Error: expect(received).toBe(expected) // Object.is equality

Expected: true
Received: false
```

# Page snapshot

```yaml
- generic [active] [ref=f3e1]:
  - link "Skip to content" [ref=f3e2] [cursor=pointer]:
    - /url: "#main-content"
  - generic [ref=f3e3]:
    - banner [ref=f3e4]:
      - button "Open navigation" [ref=f3e5] [cursor=pointer]:
        - generic [aria-hidden] [ref=f3e6]: 
      - text:   
      - generic [ref=f3e7]:
        - link "Notifications, 2 unread" [ref=f3e8] [cursor=pointer]:
          - /url: http://127.0.0.1:8877/customer/notifications
          - generic [aria-hidden] [ref=f3e9]: 
          - generic [aria-hidden] [ref=f3e10]: "2"
        - generic [ref=f3e11]:
          - button "Browser customer" [ref=f3e12] [cursor=pointer]
          - text:  
    - main [ref=f3e14]:
      - status [ref=f3e15]:
        - text: Your profile has been updated.
        - button "Dismiss notification" [ref=f3e16] [cursor=pointer]
      - generic [ref=f3e18]:
        - heading "My profile" [level=1] [ref=f3e19]
        - paragraph [ref=f3e20]: Keep your contact and location details current for smoother bookings and service updates.
      - generic [ref=f3e21]:
        - generic [ref=f3e23]:
          - generic [ref=f3e24]:
            - generic [ref=f3e25]:
              - generic [ref=f3e26]: Full name
              - textbox "Full name" [ref=f3e27]: Browser customer
            - generic [ref=f3e28]:
              - generic [ref=f3e29]: Mobile number
              - textbox "Mobile number" [ref=f3e30]
            - generic [ref=f3e31]:
              - generic [ref=f3e32]: Street address
              - textbox "Street address" [ref=f3e33]
            - generic [ref=f3e34]:
              - generic [ref=f3e35]: Barangay
              - combobox "Barangay" [ref=f3e36]:
                - option "Select barangay" [selected]
                - option "Madge Fork"
            - generic [ref=f3e37]:
              - generic [ref=f3e38]: Profile photo
              - button "Profile photo" [ref=f3e39] [cursor=pointer]
              - text: JPG, PNG, or WebP up to 2 MB. Preview before saving.
          - generic [ref=f3e40]:
            - button "Save profile" [ref=f3e41] [cursor=pointer]
            - link "Cancel" [ref=f3e42] [cursor=pointer]:
              - /url: http://127.0.0.1:8877/dashboard
        - complementary [ref=f3e43]:
          - generic [ref=f3e44]:
            - img "Browser customer profile photo" [ref=f3e45]
            - heading "Account email" [level=2] [ref=f3e46]
            - paragraph [ref=f3e47]: customer@browser.example.test
            - paragraph [ref=f3e48]: Your email is managed through the account security flow and is not changed here.
    - contentinfo [ref=f3e49]: Minglanilla AutoCare Hub · Customer workspace
  - navigation "Customer quick navigation" [ref=f3e50]:
    - link "Home" [ref=f3e51] [cursor=pointer]:
      - /url: http://127.0.0.1:8877/dashboard
      - generic [aria-hidden] [ref=f3e52]: 
    - link "Shops" [ref=f3e54] [cursor=pointer]:
      - /url: http://127.0.0.1:8877/map
      - generic [aria-hidden] [ref=f3e55]: 
    - link "Book" [ref=f3e57] [cursor=pointer]:
      - /url: http://127.0.0.1:8877/customer/appointments/create
      - generic [aria-hidden] [ref=f3e58]: 
    - link "Garage" [ref=f3e60] [cursor=pointer]:
      - /url: http://127.0.0.1:8877/customer/vehicles
      - generic [aria-hidden] [ref=f3e61]: 
    - link "Repairs" [ref=f3e63] [cursor=pointer]:
      - /url: http://127.0.0.1:8877/customer/repairs
      - generic [aria-hidden] [ref=f3e64]: 
  - generic:
    - text:    
    - button "Open AutoCare AI assistant" [ref=f3e66] [cursor=pointer]:
      - generic [aria-hidden] [ref=f3e68]: 
      - generic [ref=f3e69]:
        - strong [ref=f3e70]: Ask AutoCare AI
        - text: Need help?
```

# Test source

```ts
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
> 394 |     expect(await page.locator('.profile-photo-preview').evaluate(element => element.complete && element.naturalWidth > 0)).toBe(true);
      |                                                                                                                            ^ Error: expect(received).toBe(expected) // Object.is equality
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