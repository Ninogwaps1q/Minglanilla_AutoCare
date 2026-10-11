# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: authenticated-pages.spec.js >> AI assistant displays clean readable chat bubbles
- Location: tests\Browser\authenticated-pages.spec.js:121:1

# Error details

```
Error: expect(locator).toContainText(expected) failed

Locator: locator('.ai-message-user')
Expected substring: "oil warning light"
Error: strict mode violation: locator('.ai-message-user') resolved to 4 elements:
    1) <article class="ai-message ai-message-user">…</article> aka getByRole('article').filter({ hasText: 'You 12:43 AM What should I do' })
    2) <article class="ai-message ai-message-user">…</article> aka getByRole('article').filter({ hasText: 'You 12:44 AM Please book a' })
    3) <article class="ai-message ai-message-user">…</article> aka getByRole('article').filter({ hasText: 'You 12:46 AM Please book a' })
    4) <article class="ai-message ai-message-user">…</article> aka getByRole('article').filter({ hasText: 'You 12:49 AM Please book a' })

Call log:
  - Expect "toContainText" locator('.ai-message-user') with timeout 5000ms
  - waiting for locator('.ai-message-user')

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
      - generic [ref=f2e7]:
        - link "Notifications" [ref=f2e8] [cursor=pointer]:
          - /url: http://127.0.0.1:8877/customer/notifications
          - generic [aria-hidden] [ref=f2e9]: 
        - button "Browser customer" [ref=f2e11] [cursor=pointer]
    - main [ref=f2e13]:
      - generic [ref=f2e14]:
        - generic [ref=f2e15]:
          - heading "Ask AutoCare AI" [level=1] [ref=f2e16]
          - paragraph [ref=f2e17]: Get clear guidance about bookings, repairs, and caring for your vehicle.
        - generic [ref=f2e18]: Ready to help
      - region [ref=f2e20]:
        - generic [ref=f2e21]:
          - generic [aria-hidden] [ref=f2e23]: 
          - generic [ref=f2e24]:
            - heading "AutoCare Assistant" [level=2] [ref=f2e25]
            - paragraph [ref=f2e26]: Helpful guidance based on your AutoCare records
        - log [ref=f2e27]:
          - article [ref=f2e28]:
            - generic [ref=f2e30]: 
            - generic [ref=f2e31]:
              - generic [ref=f2e32]:
                - strong [ref=f2e33]: You
                - time [ref=f2e34]: 12:43 AM
              - paragraph [ref=f2e35]: What should I do if my oil warning light stays on?
          - article [ref=f2e36]:
            - generic [ref=f2e38]: 
            - generic [ref=f2e39]:
              - generic [ref=f2e40]:
                - strong [ref=f2e41]: AutoCare AI
                - time [ref=f2e42]: 12:43 AM
              - paragraph [ref=f2e43]: Recommended steps • Stop safely and switch off the engine. • Check the oil level when the engine is cool. Book a technician if the warning remains.
          - article [ref=f2e44]:
            - generic [ref=f2e46]: 
            - generic [ref=f2e47]:
              - generic [ref=f2e48]:
                - strong [ref=f2e49]: You
                - time [ref=f2e50]: 12:44 AM
              - paragraph [ref=f2e51]: Please book a service for my car
          - article [ref=f2e52]:
            - generic [ref=f2e54]: 
            - generic [ref=f2e55]:
              - generic [ref=f2e56]:
                - strong [ref=f2e57]: AutoCare AI
                - time [ref=f2e58]: 12:44 AM
              - paragraph [ref=f2e59]: I can start your service booking. You will still choose the shop, vehicle, service, date, and time before submitting.
              - link "Book a service" [ref=f2e60] [cursor=pointer]:
                - /url: http://127.0.0.1:8877/customer/appointments/create
                - generic [aria-hidden] [ref=f2e61]: 
                - text: Book a service
                - generic [aria-hidden] [ref=f2e62]: 
          - article [ref=f2e63]:
            - generic [ref=f2e65]: 
            - generic [ref=f2e66]:
              - generic [ref=f2e67]:
                - strong [ref=f2e68]: You
                - time [ref=f2e69]: 12:46 AM
              - paragraph [ref=f2e70]: Please book a service for my car
          - article [ref=f2e71]:
            - generic [ref=f2e73]: 
            - generic [ref=f2e74]:
              - generic [ref=f2e75]:
                - strong [ref=f2e76]: AutoCare AI
                - time [ref=f2e77]: 12:46 AM
              - paragraph [ref=f2e78]: I can start your service booking. You will still choose the shop, vehicle, service, date, and time before submitting.
              - link "Book a service" [ref=f2e79] [cursor=pointer]:
                - /url: http://127.0.0.1:8877/customer/appointments/create
                - generic [aria-hidden] [ref=f2e80]: 
                - text: Book a service
                - generic [aria-hidden] [ref=f2e81]: 
          - article [ref=f2e82]:
            - generic [ref=f2e84]: 
            - generic [ref=f2e85]:
              - generic [ref=f2e86]:
                - strong [ref=f2e87]: You
                - time [ref=f2e88]: 12:49 AM
              - paragraph [ref=f2e89]: Please book a service for my car
          - article [ref=f2e90]:
            - generic [ref=f2e92]: 
            - generic [ref=f2e93]:
              - generic [ref=f2e94]:
                - strong [ref=f2e95]: AutoCare AI
                - time [ref=f2e96]: 12:49 AM
              - paragraph [ref=f2e97]: I can start your service booking. You will still choose the shop, vehicle, service, date, and time before submitting.
              - link "Book a service" [ref=f2e98] [cursor=pointer]:
                - /url: http://127.0.0.1:8877/customer/appointments/create
                - generic [aria-hidden] [ref=f2e99]: 
                - text: Book a service
                - generic [aria-hidden] [ref=f2e100]: 
        - generic [ref=f2e101]:
          - generic [ref=f2e102]: Ask AutoCare AI a question
          - generic [ref=f2e103]:
            - textbox "Ask AutoCare AI a question" [ref=f2e104]:
              - /placeholder: Ask about your booking, repair, or vehicle care…
            - button "Send question" [ref=f2e105] [cursor=pointer]:
              - generic [aria-hidden] [ref=f2e106]: 
          - paragraph [ref=f2e107]:
            - generic [aria-hidden] [ref=f2e108]: 
            - text: For guidance only. A qualified technician should inspect safety or mechanical concerns.
    - contentinfo [ref=f2e109]: Minglanilla AutoCare Hub · Customer workspace
  - navigation "Customer quick navigation" [ref=f2e110]:
    - link "Home" [ref=f2e111] [cursor=pointer]:
      - /url: http://127.0.0.1:8877/dashboard
      - generic [aria-hidden] [ref=f2e112]: 
    - link "Shops" [ref=f2e114] [cursor=pointer]:
      - /url: http://127.0.0.1:8877/map
      - generic [aria-hidden] [ref=f2e115]: 
    - link "Book" [ref=f2e117] [cursor=pointer]:
      - /url: http://127.0.0.1:8877/customer/appointments/create
      - generic [aria-hidden] [ref=f2e118]: 
    - link "Garage" [ref=f2e120] [cursor=pointer]:
      - /url: http://127.0.0.1:8877/customer/vehicles
      - generic [aria-hidden] [ref=f2e121]: 
    - link "Repairs" [ref=f2e123] [cursor=pointer]:
      - /url: http://127.0.0.1:8877/customer/repairs
      - generic [aria-hidden] [ref=f2e124]: 
```

# Test source

```ts
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
  65  | test('customer creates a vehicle and opens its private passport', async ({ page }, testInfo) => {
  66  |     await signIn(page, 'customer');
  67  |     const plate = 'E2E-' + testInfo.project.name;
  68  |     await page.goto('/customer/vehicles/create');
  69  |     for (const [label, value] of [['Plate number', plate], ['Current mileage', '25000'], ['Brand', 'Toyota'], ['Model', 'Vios'], ['Year', '2022']]) {
  70  |         await page.getByLabel(label, { exact: true }).fill(value);
  71  |     }
  72  |     await page.locator('#photo').setInputFiles({
  73  |         name: 'browser-vehicle.png',
  74  |         mimeType: 'image/png',
  75  |         buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9JQqkAAAAASUVORK5CYII=', 'base64'),
  76  |     });
  77  |     await page.getByRole('button', { name: 'Save vehicle' }).click();
  78  |     await expect(page).toHaveURL(/customer\/vehicles$/);
  79  |     await page.reload();
  80  |     const vehicleCard = page.locator('article').filter({ hasText: plate });
  81  |     await expect(vehicleCard.getByText(plate, { exact: true })).toBeVisible();
  82  |     expect(await vehicleCard.locator('.vehicle-card-photo').evaluate(element => element.complete && element.naturalWidth > 0)).toBe(true);
  83  |     const fixture = JSON.parse(process.env.BROWSER_FIXTURE);
  84  |     await page.goto('/customer/vehicles/' + fixture.vehicle + '/passport');
  85  |     const qr = page.getByAltText('QR code linking to this private vehicle passport');
  86  |     await expect(qr).toBeVisible();
  87  |     expect(await qr.evaluate(image => image.complete && image.naturalWidth > 0)).toBe(true);
  88  | });
  89  | 
  90  | test('customer dashboard makes primary actions clear', async ({ page }, testInfo) => {
  91  |     await signIn(page, 'customer');
  92  |     await expect(page.getByRole('heading', { name: /Good (morning|afternoon|evening)/ })).toBeVisible();
  93  |     await expect(page.locator('#main-content').getByRole('link', { name: 'Find a nearby shop' })).toBeVisible();
  94  |     await expect(page.locator('#main-content').getByRole('link', { name: 'Book a service' })).toBeVisible();
  95  |     await expect(page.getByRole('heading', { name: 'Your care summary' })).toBeVisible();
  96  |     const aiLauncher = page.getByRole('button', { name: 'Open AutoCare AI assistant' });
  97  |     await expect(aiLauncher).toBeVisible();
  98  |     await expect(page.locator('#portalNavigation').getByRole('link', { name: 'Ask AutoCare AI' })).toHaveCount(0);
  99  |     const dashboardUrl = page.url();
  100 |     await aiLauncher.click();
  101 |     await expect(page.getByRole('region', { name: 'AutoCare AI chat' })).toBeVisible();
  102 |     await expect(page.getByLabel('Ask AutoCare AI')).toBeVisible();
  103 |     expect(page.url()).toBe(dashboardUrl);
  104 |     await page.getByLabel('Ask AutoCare AI').fill('Please book a service for my car');
  105 |     await page.getByRole('button', { name: 'Send question' }).click();
  106 |     const aiPanel = page.getByRole('region', { name: 'AutoCare AI chat' });
  107 |     await expect(aiPanel).toBeVisible();
  108 |     await expect(aiPanel.getByRole('link', { name: 'Book a service' }).last()).toBeVisible();
  109 |     expect(page.url()).toBe(dashboardUrl);
  110 |     await page.getByRole('button', { name: 'Close AutoCare AI chat' }).click();
  111 |     await expect(page.getByRole('region', { name: 'AutoCare AI chat' })).toBeHidden();
  112 |     await expect(page.getByRole('heading', { name: 'What’s next' })).toBeVisible();
  113 |     expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(await page.evaluate(() => document.documentElement.clientWidth + 1));
  114 |     if (testInfo.project.name !== 'desktop') {
  115 |         await expect(page.locator('.customer-mobile-nav')).toBeVisible();
  116 |         await expect(page.locator('.customer-mobile-nav').getByRole('link', { name: 'Book' })).toBeVisible();
  117 |     }
  118 |     await page.screenshot({ path: testInfo.outputPath('customer-dashboard.png'), fullPage: true });
  119 | });
  120 | 
  121 | test('AI assistant displays clean readable chat bubbles', async ({ page }, testInfo) => {
  122 |     await signIn(page, 'customer');
  123 |     await page.goto('/customer/ai-assistant');
  124 |     await expect(page.getByRole('heading', { name: 'Ask AutoCare AI' })).toBeVisible();
> 125 |     await expect(page.locator('.ai-message-user')).toContainText('oil warning light');
      |                                                    ^ Error: expect(locator).toContainText(expected) failed
  126 |     await expect(page.locator('.ai-message-assistant')).toContainText('Recommended steps');
  127 |     await expect(page.locator('.ai-message-assistant')).toContainText('• Stop safely');
  128 |     await expect(page.locator('.ai-message-assistant')).not.toContainText('# Recommended steps');
  129 |     await expect(page.locator('.ai-message-assistant')).not.toContainText('* Stop safely');
  130 |     await expect(page.getByLabel('Ask AutoCare AI a question')).toBeVisible();
  131 |     expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(await page.evaluate(() => document.documentElement.clientWidth + 1));
  132 |     await page.screenshot({ path: testInfo.outputPath('ai-assistant.png'), fullPage: true });
  133 | });
  134 | 
  135 | test('shop and admin dashboards make daily priorities clear', async ({ page }, testInfo) => {
  136 |     await signIn(page, 'shop_owner');
  137 |     await page.goto('/shop/dashboard');
  138 |     await expect(page.getByRole('heading', { name: 'Browser Test AutoCare' })).toBeVisible();
  139 |     await expect(page.getByRole('heading', { name: 'Today at a glance' })).toBeVisible();
  140 |     await expect(page.getByRole('link', { name: 'Review appointments' })).toBeVisible();
  141 |     await expect(page.getByRole('link', { name: 'Create repair job' })).toBeVisible();
  142 |     await expect(page.getByRole('heading', { name: 'Quick actions' })).toBeVisible();
  143 |     expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(await page.evaluate(() => document.documentElement.clientWidth + 1));
  144 |     if (testInfo.project.name !== 'desktop') {
  145 |         await expect(page.locator('.shop-mobile-nav')).toBeVisible();
  146 |         await expect(page.locator('.shop-mobile-nav').getByRole('link', { name: 'New job' })).toBeVisible();
  147 |     }
  148 |     await page.screenshot({ path: testInfo.outputPath('shop-dashboard.png'), fullPage: true });
  149 | 
  150 |     await page.context().clearCookies();
  151 |     await signIn(page, 'admin');
  152 |     await page.goto('/admin/dashboard');
  153 |     await expect(page.getByRole('heading', { name: 'System overview' })).toBeVisible();
  154 |     await expect(page.getByRole('heading', { name: 'Shop registrations to review' })).toBeVisible();
  155 |     await expect(page.getByRole('heading', { name: 'Operations requiring attention' })).toBeVisible();
  156 |     await expect(page.getByRole('link', { name: 'Open operations monitor' })).toBeVisible();
  157 |     expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(await page.evaluate(() => document.documentElement.clientWidth + 1));
  158 |     if (testInfo.project.name !== 'desktop') {
  159 |         await expect(page.locator('.admin-mobile-nav')).toBeVisible();
  160 |         await expect(page.locator('.admin-mobile-nav').getByRole('link', { name: 'Monitor' })).toBeVisible();
  161 |     }
  162 |     await page.screenshot({ path: testInfo.outputPath('admin-dashboard.png'), fullPage: true });
  163 | });
  164 | 
  165 | test('customer books, shop confirms, and admin monitors the same appointment', async ({ browser }, testInfo) => {
  166 |     const context = await browser.newContext(testInfo.project.use);
  167 |     const page = await context.newPage();
  168 |     try {
  169 |         const fixture = await signIn(page, 'customer');
  170 |         const reason = 'Browser booking ' + testInfo.project.name;
  171 |         const date = new Date(Date.now() + 86400000).toISOString().slice(0, 10);
  172 |         const time = { desktop: '10:00', mobile: '11:00', 'small-mobile': '12:00' }[testInfo.project.name];
  173 |         await page.goto('/customer/appointments/create');
  174 |         await page.getByLabel('Service shop', { exact: true }).selectOption(String(fixture.shop));
  175 |         await page.getByLabel('Service', { exact: true }).selectOption(String(fixture.service));
  176 |         await page.getByLabel('Vehicle', { exact: true }).selectOption(String(fixture.vehicle));
  177 |         await page.getByLabel('Preferred date').fill(date);
  178 |         await page.getByLabel('Preferred time').fill(time);
  179 |         await page.getByLabel('What does your vehicle need?').fill(reason);
  180 |         await page.getByRole('button', { name: 'Request appointment' }).click();
  181 |         await expect(page).toHaveURL(/customer\/appointments$/);
  182 |         await expect(page.getByText(/requested successfully/)).toBeVisible();
  183 |         const confirmation = await page.getByText(/requested successfully/).textContent();
  184 |         const appointmentId = confirmation.match(/Appointment #(\d+)/)[1];
  185 | 
  186 |         await context.clearCookies();
  187 |         await signIn(page, 'shop_owner');
  188 |         await page.goto('/shop/appointments');
  189 |         const row = page.getByRole('row').filter({ hasText: reason });
  190 |         await expect(row.getByRole('option', { name: 'Complete', exact: true })).toHaveCount(0);
  191 |         await row.getByLabel('Appointment status').selectOption('confirmed');
  192 |         await row.getByRole('button', { name: 'Save', exact: true }).click();
  193 |         await expect(row.locator('.badge')).toHaveText('confirmed');
  194 |         await expect(row.getByRole('option', { name: 'Confirm', exact: true })).toHaveCount(0);
  195 | 
  196 |         await context.clearCookies();
  197 |         await signIn(page, 'admin');
  198 |         await page.goto('/admin/monitoring?module=appointments&status=confirmed');
  199 |         const monitored = page.getByRole('row').filter({ has: page.getByRole('rowheader', { name: '#' + appointmentId, exact: true }) });
  200 |         await expect(monitored.getByRole('cell', { name: 'Browser Test AutoCare', exact: true })).toBeVisible();
  201 |         await expect(monitored.getByRole('cell', { name: 'Confirmed', exact: true })).toBeVisible();
  202 |     } finally {
  203 |         await context.close();
  204 |     }
  205 | });
  206 | 
  207 | test('admin manages categories and suspends then restores a public shop', async ({ page }, testInfo) => {
  208 |     test.setTimeout(60000);
  209 |     const fixture = await signIn(page, 'admin');
  210 |     const category = 'Browser category ' + testInfo.project.name;
  211 |     await page.goto('/admin/service-categories');
  212 |     await page.getByLabel('Category name', { exact: true }).fill(category);
  213 |     await page.getByRole('button', { name: 'Add category', exact: true }).click();
  214 |     const section = page.getByRole('region', { name: category, exact: true });
  215 |     await expect(section).toBeVisible();
  216 |     await section.getByLabel(/Category name/).fill(category + ' updated');
  217 |     await section.getByRole('button', { name: 'Save category' }).click();
  218 |     const updated = page.getByRole('region', { name: category + ' updated', exact: true });
  219 |     await expect(updated).toBeVisible();
  220 |     page.once('dialog', dialog => dialog.accept());
  221 |     await updated.getByRole('button', { name: 'Delete unused category' }).click();
  222 |     await expect(updated).toHaveCount(0);
  223 | 
  224 |     await page.goto('/admin/shops');
  225 |     const shop = page.getByRole('row').filter({ hasText: 'Browser Test AutoCare' });
```