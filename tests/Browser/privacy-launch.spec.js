import { expect, test } from '@playwright/test';

test('legal pages and preferences stay usable across screen widths and themes', async ({ page }, testInfo) => {
    test.skip(process.env.BROWSER_ISOLATED !== '1', 'Requires the disposable browser fixture');
    test.setTimeout(90000);
    await page.goto('/privacy', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Privacy Policy', exact: true })).toBeVisible();

    for (const [width, theme, locale, language, heading] of [
        [360, 'light', 'ceb', 'Change language', 'Palisiya sa pribasiya'],
        [768, 'dark', 'fil', 'Usba ang pinulongan', 'Patakaran sa Privacy'],
    ]) {
        await page.setViewportSize({ width, height: 850 });
        await page.evaluate(value => localStorage.setItem('autocare-theme', value), theme);
        const switcher = page.locator('#public-language');
        await switcher.selectOption(locale);
        await switcher.focus();
        await page.keyboard.press('Tab');
        const apply = page.getByRole('button', { name: language, exact: true });
        await expect(apply).toBeFocused();
        await page.keyboard.press('Enter');
        await expect(page.locator('html')).toHaveAttribute('lang', locale);
        await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
        await expect(page.getByRole('heading', { name: heading, exact: true })).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1)).toBe(true);
        await page.screenshot({ path: testInfo.outputPath(`privacy-${locale}-${theme}.png`) });
    }

    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/register', { waitUntil: 'domcontentloaded' });
    const agreement = page.getByRole('checkbox', { name: 'Sumasang-ayon ako sa mga Tuntunin at Patakaran sa Privacy' });
    await agreement.focus();
    await page.keyboard.press('Space');
    await expect(agreement).toBeChecked();

    const fixture = JSON.parse(process.env.BROWSER_FIXTURE);
    await page.goto('/login', { waitUntil: 'domcontentloaded' });
    await page.getByLabel('Email address').fill('customer@browser.example.test');
    await page.getByLabel('Password', { exact: true }).fill(fixture.password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/dashboard$/);
    await page.goto('/settings/notifications', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: 'Mga pagpipilian sa abiso', exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Mga update sa serbisyo', exact: true })).toBeVisible();
    await expect(page.locator('#sms-inspection')).toBeVisible();
    await expect(page.locator('#sms-release')).toBeVisible();
    await expect(page.locator('#sms-roadside')).toHaveCount(0);
    await expect(page.locator('#sms-consent')).not.toBeChecked();
    await expect(page.getByRole('button', { name: 'Payagan ang push sa device na ito' })).toHaveCount(0);
    await page.setViewportSize({ width: 360, height: 820 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1)).toBe(true);
    await page.screenshot({ path: testInfo.outputPath('notification-preferences-mobile-dark.png'), fullPage: true });
    await page.evaluate(() => localStorage.setItem('autocare-theme', 'light'));
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    await page.locator('#email-inspection').focus();
    await page.keyboard.press('Tab');
    await expect(page.locator('#sms-inspection')).toBeFocused();
    await page.screenshot({ path: testInfo.outputPath('notification-preferences-mobile-light.png'), fullPage: true });
    await page.goto('/customer/appointments/create', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('#slot-feedback')).toContainText('Piliin ang shop, serbisyo at petsa');
    await expect(page.getByRole('heading', { name: 'Mag-book ng serbisyo', exact: true })).toBeVisible();
});
