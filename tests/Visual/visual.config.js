import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: '.',
    timeout: 120000,
    globalSetup: './global-setup.js',
    expect: {
        timeout: 10000,
        toHaveScreenshot: {
            maxDiffPixelRatio: 0.01,
        },
    },
    use: {
        baseURL: process.env.VISUAL_BASE_URL || 'https://sigma-project.test',
        headless: true,
        viewport: { width: 1366, height: 768 },
        ignoreHTTPSErrors: true,
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
    outputDir: 'output/playwright',
    reporter: [
        ['list'],
        ['html', { outputFolder: 'output/playwright-report', open: 'never' }],
    ],
});
