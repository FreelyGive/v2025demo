import { expect } from '@playwright/test';

import { test } from './fixtures/DrupalSite';

/**
 * Tests the site install and all the basic commands in Drupal.ts
 *
 * If drush is present, it will test all the commands both with and without it.
 *
 * The assertions for checking the results are what we expected from drush are
 * done here and not in Drupal.ts because it makes it slower than doing it via
 * the browser, and we can be reasonably confident that the results are correct.
 */
test.describe('Canary', () => {
  test('Site install', async ({ page }) => {
    await page.goto('/user/login');
    await expect(page).toHaveTitle('Log in | Drupal');
  });

  test('Login as admin', async ({ page, drupal }) => {
    const hasDrush = drupal.hasDrush();
    if (drupal.hasDrush()) {
      await drupal.loginAsAdmin();
      await drupal.logout();
      await page.goto('/user/login');
      await expect(page.locator('h1')).not.toHaveText('admin');
      // Turn drush off for the next test.
      drupal.disableDrush();
    }
    await drupal.loginAsAdmin();
    await drupal.logout();
    await page.goto('/user/login');
    await expect(page.locator('h1')).not.toHaveText('admin');
    // Reset drush status to its original value.
    drupal.setDrush(hasDrush);
  });

  test('Create users, roles, and permissions', async ({ page, drupal }) => {
    await drupal.loginAsAdmin();
    const hasDrush = drupal.hasDrush();

    if (hasDrush) {
      // Create a role.
      await drupal.createRole({ name: 'content_editor' });
      const roles = await drupal.drush(`role:list --field=rid`);
      expect(roles.split('\n')).toContain('content_editor');
      // Create a user.
      const contentEditor = {
        email: 'editor@example.com',
        username: 'editor',
        password: 'superstrongpassword1337',
        roles: ['content_editor'],
      };
      await drupal.createUser(contentEditor);
      const userRolesJson = await drupal.drush(
        `user:information editor --format=json`,
      );
      const uid = await drupal.drush(`user:information editor --field=uid`);
      const userRoles = JSON.parse(userRolesJson);
      expect(userRoles[uid].roles).toEqual(
        expect.arrayContaining(['content_editor']),
      );
      // Add permissions to the role.
      await drupal.addPermissions({
        role: 'content_editor',
        permissions: ['access content overview'],
      });
      const rolePermissionsJson = await drupal.drush(
        `role:list --filter='rid=content_editor' --format=json`,
      );
      const rolePermissions = JSON.parse(rolePermissionsJson);
      expect(rolePermissions.content_editor.perms).toEqual(
        expect.arrayContaining(['access content overview']),
      );
      // Turn drush off for the next test.
      drupal.disableDrush();
    }
    await drupal.createRole({ name: 'moderator' });
    const moderator = {
      email: 'moderator@example.com',
      username: 'moderator',
      password: 'superstrongpassword1337',
      roles: ['moderator'],
    };
    await drupal.createUser(moderator);
    await drupal.addPermissions({
      role: 'moderator',
      permissions: ['access content overview'],
    });
    await drupal.logout();
    await drupal.login(moderator);
    await page.goto('/admin/content');
    await expect(page.locator('h1')).toHaveText('Content');
    // Reset drush status to its original value.
    drupal.setDrush(hasDrush);
  });

  test('Install modules', async ({ drupal }) => {
    const hasDrush = drupal.hasDrush();
    if (hasDrush) {
      await drupal.installModules(['ban']);
      const enabledModules = await drupal.drush(
        `pm:list --type=module --status=enabled --field=name`,
      );
      expect(enabledModules.split('\n')).toEqual(
        expect.arrayContaining(['ban']),
      );
      // Turn drush off for the next test.
      drupal.disableDrush();
    }
    await drupal.loginAsAdmin();
    const modules = ['views', 'views_ui'];
    await drupal.installModules(modules);
    // Reset drush status to its original value.
    drupal.setDrush(hasDrush);
  });
});
