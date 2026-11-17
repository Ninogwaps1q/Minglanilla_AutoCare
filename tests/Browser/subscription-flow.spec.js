import { test, expect } from '@playwright/test';
import { resolve } from 'node:path';

test.skip(() => process.env.BROWSER_ISOLATED !== '1', 'Requires disposable browser database and storage');

async function signIn(page, role, password) {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(role + '@browser.example.test');
    await page.getByLabel('Password', { exact: true }).fill(password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/dashboard$/);
}

async function captureThemes(page, testInfo, name) {
    for (const theme of ['light', 'dark']) {
        await page.evaluate(value => localStorage.setItem('autocare-theme', value), theme);
        await page.reload();
        await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1)).toBe(true);
        await page.screenshot({ path: testInfo.outputPath(name + '-' + theme + '.png'), fullPage: true });
    }
}

test('owners submit a plan payment directly and admins review its receipt', async ({ page, browser }, testInfo) => {
    test.setTimeout(90000);
    const fixture = JSON.parse(process.env.BROWSER_FIXTURE);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await signIn(page, 'admin', fixture.password);
    await page.goto('/admin/shop-plans');
    await page.getByLabel('Set plan', { exact: true }).selectOption('free');
    await page.getByRole('button', { name: 'Save access', exact: true }).click();
    const basicSettings = page.locator('details').filter({ has: page.locator('summary', { hasText: 'Basic' }) });
    await basicSettings.locator('summary').click();
    await basicSettings.getByLabel('Account name', { exact: true }).fill('Browser AutoCare Billing');
    await basicSettings.getByLabel('Account number', { exact: true }).fill('001234567890');
    await basicSettings.getByLabel('Payment instructions', { exact: true }).fill('Test transfer instructions for the browser fixture.');
    const image = resolve('public/images/Minglanilla AutoCare Hub Logo.png');
    await basicSettings.getByLabel('Payment QR image', { exact: true }).setInputFiles(image);
    await basicSettings.getByRole('button', { name: 'Save Basic plan', exact: true }).click();
    await expect(page.getByRole('status')).toContainText('Basic pricing and payment details updated.');

    const ownerContext = await browser.newContext({
        baseURL: 'http://127.0.0.1:8877', viewport: testInfo.project.use.viewport,
        isMobile: Boolean(testInfo.project.use.isMobile), hasTouch: Boolean(testInfo.project.use.hasTouch),
    });
    const ownerPage = await ownerContext.newPage();
    ownerPage.on('pageerror', error => errors.push(error.message));
    try {
        await signIn(ownerPage, 'shop_owner', fixture.password);
        await ownerPage.goto('/shop/pricing?plan=premium');
        await ownerPage.getByRole('link', { name: 'Choose Basic', exact: true }).click();
        await expect(ownerPage.getByRole('heading', { name: 'Pay for Basic', exact: true })).toBeVisible();
        await expect(ownerPage.getByText('Browser AutoCare Billing', { exact: true })).toBeVisible();
        await expect(ownerPage.getByRole('img', { name: 'Payment QR for Basic', exact: true })).toBeVisible();
        await expect.poll(() => ownerPage.getByRole('img', { name: 'Payment QR for Basic', exact: true }).evaluate(img => img.complete && img.naturalWidth > 0)).toBe(true);
        await captureThemes(ownerPage, testInfo, 'owner-upgrade');
        await ownerPage.getByLabel('Payment reference', { exact: true }).fill('BROWSER-' + testInfo.project.name);
        await ownerPage.getByLabel('Receipt or payment proof', { exact: true }).setInputFiles(image);
        await ownerPage.getByRole('button', { name: 'Submit Basic payment for review', exact: true }).click();
        await expect(ownerPage.getByText('Pending payment review', { exact: true })).toBeVisible();
        await expect(ownerPage.locator('input[name="proof"]')).toHaveCount(0);

        await page.reload();
        await expect(page.getByRole('img', { name: 'Payment receipt from Browser Test AutoCare', exact: true })).toBeVisible();
        await expect.poll(() => page.getByRole('img', { name: 'Payment receipt from Browser Test AutoCare', exact: true }).evaluate(img => img.complete && img.naturalWidth > 0)).toBe(true);
        await captureThemes(page, testInfo, 'admin-payment-review');
        await page.getByRole('button', { name: 'Approve payment and activate', exact: true }).click();
        await expect(page.getByRole('status')).toContainText('Plan payment review saved.');
        await ownerPage.reload();
        await expect(ownerPage.getByRole('heading', { name: 'Current plan: Basic', exact: true })).toBeVisible();
        await expect(ownerPage.getByText('Paid plan active', { exact: true })).toBeVisible();
        await expect(ownerPage.getByText('Pending payment review', { exact: true })).toHaveCount(0);
        expect(errors).toEqual([]);
    } finally {
        await ownerContext.close();
    }
});
