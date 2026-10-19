import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser',
    outputDir: './storage/framework/testing/playwright-results',
    workers: 1,
    retries: 0,
    globalSetup: process.env.BROWSER_ISOLATED === '1' ? './tests/Browser/isolated-setup.js' : undefined,
    reporter: 'list',
    use: {
        baseURL: process.env.BROWSER_ISOLATED === '1' ? 'http://127.0.0.1:8877' : (process.env.BROWSER_TEST_URL || 'http://127.0.0.1:8000'),
        channel: 'chrome',
        headless: true,
        screenshot: 'only-on-failure',
    },
    projects: [
        { name: 'desktop', use: { viewport: { width: 1440, height: 1000 } } },
        { name: 'mobile', use: { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } },
        { name: 'small-mobile', use: { viewport: { width: 320, height: 720 }, isMobile: true, hasTouch: true } },
    ],
});
