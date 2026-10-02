import { expect, test } from '@playwright/test';

/**
 * E2E smoke (DEC-095 #34): sign in → dashboard → booking list → a "coming soon" menu target, with no error page on the
 * way. Needs E2E_USER / E2E_PASSWORD (a test account) in the environment.
 */
const user = process.env.E2E_USER ?? '';
const password = process.env.E2E_PASSWORD ?? '';

test('the sign-in page renders without an error', async ({ page }) => {
    const response = await page.goto('admin/login');
    expect(response?.status()).toBe(200);
    await expect(page.locator('#username')).toBeVisible();
    await expect(page.locator('#password')).toBeVisible();
    await expect(page.locator('body')).not.toContainText(/Server Error|Whoops/i);
});

test('a user signs in and opens the main screens', async ({ page }) => {
    test.skip(!user || !password, 'Set E2E_USER and E2E_PASSWORD to run the signed-in smoke.');
    await page.goto('admin/login');
    await page.locator('#username').fill(user);
    await page.locator('#password').fill(password);
    await page.getByRole('button', { name: /login/i }).click();

    await expect(page).toHaveURL(/admin\/(dashboard|home)/);
    await expect(page.locator('body')).not.toContainText(/Server Error|Whoops|Page not found/i);

    for (const path of ['admin/sales/booking', 'admin/coming-soon?feature=E2E']) {
        const response = await page.goto(path);
        expect(response?.status(), path).toBeLessThan(400);
        await expect(page.locator('body')).not.toContainText(/Server Error|Whoops/i);
    }
});
