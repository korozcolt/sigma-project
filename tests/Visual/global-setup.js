import fs from 'fs';
import path from 'path';
import { chromium } from '@playwright/test';
import { roles, login } from './helpers.js';

export default async function globalSetup() {
    const baseURL = process.env.VISUAL_BASE_URL || 'https://sigma-project.test';
    const authDir = path.resolve('tests/Visual/.auth');

    if (!fs.existsSync(authDir)) {
        fs.mkdirSync(authDir, { recursive: true });
    }

    const browser = await chromium.launch();

    for (const role of roles) {
        const storageStatePath = path.join(authDir, `${role.name}.json`);
        const context = await browser.newContext({ baseURL });
        const page = await context.newPage();
        await login(page, role);
        await context.storageState({ path: storageStatePath });
        await context.close();
    }

    await browser.close();
}
