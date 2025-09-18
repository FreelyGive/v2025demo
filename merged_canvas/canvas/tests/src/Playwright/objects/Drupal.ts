import * as fs from 'node:fs';
import * as nodePath from 'node:path';
import { expect } from '@playwright/test';

import { exec, execDrush } from '../utilities/DrupalExec';
import { getModuleDir, getRootDir } from '../utilities/DrupalFilesystem';

import type { Page } from '@playwright/test';
import type { DrupalSite } from '../fixtures/DrupalSite';

export class Drupal {
  readonly page: Page;
  readonly drupalSite: DrupalSite;

  constructor({ page, drupalSite }: { page: Page; drupalSite: DrupalSite }) {
    this.page = page;
    this.drupalSite = drupalSite;
  }

  async setTestCookie() {
    const context = await this.page.context();
    const simpletestCookie = {
      name: 'SIMPLETEST_USER_AGENT',
      value: encodeURIComponent(this.drupalSite.userAgent),
      url: this.drupalSite.url,
    };
    const playwrightCookie = {
      name: 'CANVAS_PLAYWRIGHT',
      value: 'true',
      url: this.drupalSite.url,
    };
    await context.addCookies([simpletestCookie, playwrightCookie]);
  }

  hasDrush() {
    return this.drupalSite.hasDrush;
  }

  disableDrush() {
    this.drupalSite.hasDrush = false;
  }

  enableDrush() {
    this.drupalSite.hasDrush = true;
  }

  setDrush(enabled: boolean) {
    this.drupalSite.hasDrush = enabled;
  }

  async drush(command: string) {
    return await execDrush(command, this.drupalSite);
  }

  // @todo deprecate this as it sets the site up into a weird state.
  async setupCanvasTestSite() {
    const moduleDir = await getModuleDir();
    await this.enableTestExtensions();
    await this.writeBaseUrl();
    await this.applyRecipe(`${moduleDir}/canvas/tests/fixtures/recipes/base`);
    await this.applyRecipe(
      `${moduleDir}/canvas/tests/fixtures/recipes/test_site`,
    );
  }

  async createCanvasPage(title: string, alias: string) {
    await this.drush(
      `php-eval "Drupal\\canvas\\Entity\\Page::create(['title' => '${title}', 'type' => 'canvas_page', 'path' => ['alias' => '${alias}', 'langcode' => 'en']])->save();"`,
    );
  }

  async loginAsAdmin() {
    const stdout = await exec(
      `php core/scripts/test-site.php user-login 1 --site-path ${this.drupalSite.sitePath}`,
    );
    await this.page.goto(`${this.drupalSite.url}${stdout.toString()}`);
    await expect(this.page.locator('h1')).toHaveText('admin');
  }

  async login(
    { username, password }: { username: string; password?: string } = {
      username: this.drupalSite.username,
      password: this.drupalSite.password,
    },
  ) {
    if (!this.drupalSite.hasDrush && !password) {
      throw new Error('Password is required when drush is not available.');
    }
    const page = this.page;
    if (this.drupalSite.hasDrush) {
      const loginUrl = await this.drush(
        `user:login --name=${username} --no-browser`,
      );
      await page.goto(loginUrl);
    } else {
      await page.goto(`${this.drupalSite.url}/user/login`);
      await page.locator('[data-drupal-selector="edit-name"]').fill(username);
      await page.locator('[data-drupal-selector="edit-pass"]').fill(password);
      await page.locator('[data-drupal-selector="edit-submit"]').click();
    }
    await expect(page.locator('h1')).toHaveText(username);
  }

  async logout() {
    const page = this.page;
    await page.goto(`${this.drupalSite.url}/user/logout/confirm`);
    await page.locator('[data-drupal-selector="edit-submit"]').click();
    let cookies = await page.context().cookies();
    cookies = cookies.filter(
      (cookie) =>
        cookie.name.startsWith('SESS') || cookie.name.startsWith('SSESS'),
    );
    await expect(cookies).toHaveLength(0);
  }

  async createRole({ name }: { name: string }) {
    if (this.drupalSite.hasDrush) {
      await this.drush(`role:create ${name}`);
    } else {
      const page = this.page;
      await page.goto(`${this.drupalSite.url}/admin/people/roles/add`);
      await page.locator('[data-drupal-selector="edit-label"]').fill(name);
      await page.locator('[data-drupal-selector="edit-submit"]').click();
      await expect(page.locator('//*[@data-drupal-messages]')).toContainText(
        'has been added.',
      );
      await expect(page.locator('//*[@data-drupal-messages]')).toContainText(
        name,
      );
    }
  }

  async addPermissions({
    role,
    permissions,
  }: {
    role: string;
    permissions: string[];
  }) {
    if (this.drupalSite.hasDrush) {
      await this.drush(`role:perm:add ${role} '${permissions.join(',')}'`);
    } else {
      const page = this.page;
      await page.goto(`${this.drupalSite.url}/admin/people/permissions`);
      for (const permission of permissions) {
        await page
          .locator(
            `[data-drupal-selector="edit-${this.normalizeAttribute(
              role,
            )}-${this.normalizeAttribute(permission)}"]`,
          )
          .check();
      }
      await page.locator('[data-drupal-selector="edit-submit"]').click();
      await expect(page.locator('//*[@data-drupal-messages]')).toContainText(
        'The changes have been saved',
      );
    }
  }

  async createUser({
    username,
    password,
    email,
    roles,
  }: {
    username: string;
    password: string;
    email: string;
    roles: string[];
  }): Promise<number> {
    if (this.drupalSite.hasDrush) {
      await this.drush(
        `user:create ${username} --password=${password} --mail=${email}`,
      );
      for (const role of roles) {
        await this.drush(`user:role:add ${role} ${username}`);
      }
    } else {
      const page = this.page;
      await page.goto(`${this.drupalSite.url}/admin/people/create`);
      await page.locator('[data-drupal-selector="edit-mail"]').fill(email);
      await page.locator('[data-drupal-selector="edit-name"]').fill(username);
      await page
        .locator('[data-drupal-selector="edit-pass-pass1"]')
        .fill(password);
      await page
        .locator('[data-drupal-selector="edit-pass-pass2"]')
        .fill(password);
      for (const role of roles) {
        await page
          .locator(
            `[data-drupal-selector="edit-roles-${this.normalizeAttribute(
              role,
            )}"]`,
          )
          .check();
      }
      await page.locator('[data-drupal-selector="edit-submit"]').click();
      await expect(page.locator('//*[@data-drupal-messages]')).toContainText(
        'Created a new user account for',
      );
      await expect(page.locator('//*[@data-drupal-messages]')).toContainText(
        username,
      );
      const href = await page
        .locator('//*[@data-drupal-messages]//a')
        .getAttribute('href');
      const match = href?.match(/\/user\/(\d+)/);
      const userId = parseInt(match[1]);
      if (isNaN(userId)) {
        throw new Error(`No user ID found for ${username}`);
      }
      return userId;
    }
  }

  async installModules(modules: string[]) {
    if (this.drupalSite.hasDrush) {
      await this.drush(`pm:enable ${modules.join(' ')}`);
    } else {
      const page = this.page;
      await page.goto(`${this.drupalSite.url}/admin/modules`);
      for (const module of modules) {
        await page
          .locator(
            `[data-drupal-selector="edit-modules-${this.normalizeAttribute(
              module,
            )}-enable"]`,
          )
          .check();
      }
      await page.locator('[data-drupal-selector="edit-submit"]').click();
      for (const module of modules) {
        const checkbox = page.locator(
          `[data-drupal-selector="edit-modules-${this.normalizeAttribute(
            module,
          )}-enable"]`,
        );
        expect(checkbox).toBeTruthy();
        await expect(checkbox).toBeDisabled();
      }
      await expect(page.locator('//*[@data-drupal-messages]')).toContainText(
        `been installed`,
      );
    }
  }

  async enableTestExtensions() {
    const settingsFile = nodePath.resolve(
      getRootDir(),
      `${this.drupalSite.sitePath}/settings.php`,
    );
    fs.chmodSync(settingsFile, 0o775);
    return await exec(
      `echo '$settings["extension_discovery_scan_tests"] = TRUE;' >> ${settingsFile}`,
    );
  }

  async writeBaseUrl() {
    // \Drupal\Core\StreamWrapper\PublicStream::baseUrl needs a base-url set,
    // otherwise it will default to $GLOBALS['base_url']. When a recipe is being
    // run via core/scripts/drupal, that defaults to core/scripts/drupal 😭.
    const settingsFile = nodePath.resolve(
      getRootDir(),
      `${this.drupalSite.sitePath}/settings.php`,
    );
    fs.chmodSync(settingsFile, 0o775);
    return await exec(
      `echo '$settings["file_public_base_url"] = "${this.drupalSite.url}/${this.drupalSite.sitePath}/files";' >> ${settingsFile}`,
    );
  }

  async applyRecipe(path: string) {
    return await exec(
      `DRUPAL_DEV_SITE_PATH=${this.drupalSite.sitePath} php core/scripts/drupal recipe ${path}`,
    );
  }

  async setPreprocessing({
    css,
    javascript,
  }: {
    css?: boolean;
    javascript?: boolean;
  }) {
    let changedCss, changedJs;

    if (this.hasDrush()) {
      if (css !== undefined) {
        changedCss = true;
        await this.drush(
          `config:set system.performance css.preprocess ${css ? '1' : '0'}`,
        );
      }
      if (javascript !== undefined) {
        changedJs = true;
        await this.drush(
          `config:set system.performance js.preprocess ${javascript ? '1' : '0'}`,
        );
      }
      if (changedCss || changedJs) {
        await this.drush('cache:clear css-js');
      }
    } else {
      await this.page.goto('/admin/config/development/performance');
      if (css !== undefined) {
        changedCss = true;
        await this.page.getByLabel('Aggregate CSS files').setChecked(css);
      }
      if (javascript !== undefined) {
        changedJs = true;
        await this.page
          .getByLabel('Aggregate JavaScript files')
          .setChecked(javascript);
      }
      await this.page
        .getByRole('button', { name: 'Save configuration' })
        .click();
    }

    // If either CSS or JS was changed, verify the setting is applied
    if (changedCss || changedJs) {
      await this.page.goto(`/admin/config/development/performance`);
      if (changedCss) {
        await expect(this.page.getByLabel('Aggregate CSS files')).toBeChecked({
          checked: !!css,
        });
      }
      if (changedJs) {
        await expect(
          this.page.getByLabel('Aggregate JavaScript files'),
        ).toBeChecked({ checked: !!javascript });
      }
    }
  }

  async addMediaGenericFile(path: string) {
    await this.page
      .locator(
        '[data-testid="canvas-contextual-panel"] input[value="Add media"]',
      )
      .first() // @todo shouldn't need this but Canvas is currently rendering two fields
      .click();
    await this.page
      .locator(
        'form[data-drupal-selector^="media-library-add-form-upload"] input[name="files[upload]"]',
      )
      .setInputFiles(nodePath.join(__dirname, path));
    await this.page.getByRole('button', { name: 'Save', exact: true }).click();
    // @todo select the item we just uploaded rather than the first.
    await this.page
      .locator(
        '.media-library-widget-modal input[data-drupal-selector^="edit-media-library-select-form"]',
      )
      .first()
      .setChecked(true, { force: true });
    await this.page
      .getByRole('button', { name: 'Insert selected', exact: true })
      .click();
    await expect(
      this.page.locator(
        '[data-testid="canvas-contextual-panel"] .js-media-library-item input[data-canvas-media-remove-button="true"]',
      ),
    ).toBeVisible();
  }

  async addMediaImage(path: string, alt: string) {
    await this.page.getByRole('button', { name: 'Add media' }).click();

    await this.page
      .locator(
        'form[data-drupal-selector^="media-library-add-form-upload"] input[name="files[upload]"]',
      )
      .setInputFiles(nodePath.join(__dirname, path));

    // It should be possible to set the alt text with the following, but there's currently a bug
    // await this.page.getByLabel('Alternative text').fill('A cute dog');
    // instead we use the evaluate method to set the value directly.
    // https://www.drupal.org/project/canvas/issues/3535215
    await this.page
      .locator('input[name="media[0][fields][field_media_image][0][alt]"]')
      .evaluate((el: HTMLInputElement, value) => {
        el.value = value;
      }, alt);

    await this.page.getByRole('button', { name: 'Save', exact: true }).click();
    // @todo select the item we just uploaded rather than the first.
    await this.page
      .locator(
        '.media-library-widget-modal input[data-drupal-selector^="edit-media-library-select-form"]',
      )
      .first()
      .setChecked(true, { force: true });
    await this.page
      .getByRole('button', { name: 'Insert selected', exact: true })
      .click();
    await expect(
      this.page.locator(
        '[data-testid="canvas-contextual-panel"] .js-media-library-item-preview img',
      ),
    ).toHaveAttribute('alt', alt);
  }

  async getSettings() {
    const value = await this.page.evaluate(() => {
      return window.drupalSettings;
    });
    return value;
  }

  async selectMedia(media: string) {
    await this.page
      .locator(
        '[data-testid="canvas-contextual-panel"] input[value="Add media"]',
      )
      .first() // @todo Remove this line in https://www.drupal.org/project/canvas/issues/3535220
      .click();
    await this.page
      .locator(`div.canvas-media-preview-label:has-text("${media}")`)
      .click();
    await this.page
      .getByRole('button', { name: 'Insert selected', exact: true })
      .click();
    expect(
      this.page.locator(
        '[data-testid="canvas-contextual-panel"] .js-media-library-item-preview img',
      ),
    );
  }

  normalizeAttribute(attribute: string) {
    return attribute.replaceAll(' ', '-').replaceAll('_', '-');
  }
}
