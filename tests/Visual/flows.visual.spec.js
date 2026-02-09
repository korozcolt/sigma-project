import path from 'path';
import { test } from '@playwright/test';
import { roles, login, navigateAndCheck } from './helpers.js';

const flows = [
    { path: '/admin', name: '01-dashboard' },
    { path: '/admin/campaigns', name: '02-campaigns' },
    { path: '/admin/users', name: '03-users' },
    { path: '/admin/voters', name: '04-voters' },
    { path: '/admin/surveys', name: '05-surveys' },
    { path: '/admin/messages', name: '06-messages' },
    { path: '/admin/messages/message-templates', name: '07-message-templates' },
    { path: '/admin/messages/message-batches', name: '08-message-batches' },
    { path: '/admin/call-center', name: '09-call-center' },
    { path: '/admin/verification-calls', name: '10-verification-calls' },
    { path: '/admin/manage-election-events', name: '11-manage-election-events' },
    { path: '/admin/dia-d', name: '12-dia-d' },
    { path: '/admin/invitations', name: '13-invitations' },
    { path: '/admin/departments', name: '14-departments' },
    { path: '/admin/municipalities', name: '15-municipalities' },
    { path: '/admin/neighborhoods', name: '16-neighborhoods' },
];

const authDir = path.resolve('tests/Visual/.auth');

for (const role of roles) {
    const storageStatePath = path.join(authDir, `${role.name}.json`);

    test.describe(`${role.name} visual flows`, () => {
        test.use({ storageState: storageStatePath });

        test.beforeEach(async ({ page }) => {
            await page.goto('/admin');
        });

        for (const flow of flows) {
            test(`${role.name} ${flow.name}`, async ({ page }) => {
                await navigateAndCheck(page, flow.path, `${role.label}-${flow.name}.png`);
            });
        }
    });
}
