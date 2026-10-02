import { defineConfig, devices } from '@playwright/test';

/**
 * End-to-end tests (DEC-095 #34, to-do W18m / Q3). Dev-only; runs against a running local app, never UAT / production.
 *
 *   npm i -D @playwright/test && npx playwright install chromium     (once)
 *   E2E_BASE_URL=http://localhost/xlrm/public E2E_USER=… E2E_PASSWORD=… npx playwright test
 *
 * Use a dedicated test account; credentials only from the environment, never committed.
 */
export default defineConfig({
    testDir: './tests/E2E',
    timeout: 60_000,
    retries: 0,
    reporter: [['list']],
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://localhost/xlrm/public',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
