import { expect } from '@playwright/test';

export const roles = [
    { name: 'super_admin', email: 'ing.korozco@gmail.com', password: 'Admin123', label: 'SuperAdmin' },
    { name: 'admin_campaign', email: 'admin.campaign@sigma.test', password: 'Admin123', label: 'AdminCampaign' },
    { name: 'coordinator', email: 'coordinator@sigma.test', password: 'Admin123', label: 'Coordinator' },
    { name: 'leader', email: 'leader@sigma.test', password: 'Admin123', label: 'Leader' },
    { name: 'reviewer', email: 'reviewer@sigma.test', password: 'Admin123', label: 'Reviewer' },
];

export async function login(page, role) {
    await page.goto('/admin');
    await page.getByRole('textbox', { name: 'Correo electrónico' }).fill(role.email);
    await page.getByRole('textbox', { name: 'Contraseña' }).fill(role.password);
    await page.getByRole('button', { name: 'Iniciar sesión' }).click();
    await page.waitForURL(/\/(admin|leader|coordinator)/);
}

export async function visualCheck(page, name) {
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(1000);
    await expect(page).toHaveScreenshot(name, { fullPage: true });
}

export async function navigateAndCheck(page, path, name) {
    await page.goto(path, { waitUntil: 'domcontentloaded' });
    await visualCheck(page, name);
}
