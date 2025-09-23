# Playwright

[Playwright](https://playwright.dev/) provides cross-browser functional testing.
These tests are in the `tests/src/Playwright` folder and make use of the recipes
located in `tests/fixtures/recipes`.

>>>
❗ Setup
First follow the global [setup instructions](./setup.md) to ensure you have all
the required PHP dependencies.
>>>

## Running Tests

If you are not using ddev, copy `.env.defaults` to `.env` and modify the values
as appropriate.

From the root of this module, install the dependencies:
```
npm install
# Install system dependencies
npx playwright install-deps
# Install browsers
npx playwright install
```

Then run the tests:
```
npm run test:playwright
```
This is the same script which will run in the CI (and will ensure that the
one-time `playwright install` installation command has been run). You can also
run more specific Playwright commands. For example, a single test:
```
npx playwright test canary.spec.ts
```

Run your tests with UI Mode for a better developer experience with time travel
debugging, watch mode and more:

```
npx playwright test canary.spec.ts --ui
```

Run a test in a specific browser
```
npx playwright test --project firefox
```

A full list of commands is available here:
https://playwright.dev/docs/running-tests#running-tests

## Writing Tests

Import the `DrupalSite` fixture and this will setup and manage a minimal Drupal
site install for testing. All tests in one file are run in serial, whilst
separate files are run in parallel. You can override this per [test file](https://playwright.dev/docs/test-parallel#parallelize-tests-in-a-single-file)
and opt into fully parallel mode, however keep in mind that for each test worker
that starts it will install a new Drupal site. You will also incur any
additional overhead required to set up Drupal Canvas for each worker.

However, you should still write each test as if it could run in parallel i.e.
do not rely on a state from the previous test. This is because when a test fails,
crashes, or flakes, then it can retry. It will do this by first running the
`beforeAll` function, and then skipping straight to the test in question.

```typescript
import { expect } from '@playwright/test';
import { test } from './fixtures/DrupalSite';

test.describe('Canary', () => {

  test('Login', async ({ page, drupal }) => {
    await page.goto('/user/login');
    await expect(page).toHaveTitle('Log in | Drupal');
    await drupal.loginAsAdmin();
    await expect(page.locator('h1')).not.toHaveText('admin');
  });
});
```

A [`drupal` object](../../tests/src/Playwright/objects/Drupal.ts) is available
using Playwright's [Page object model](https://playwright.dev/docs/pom)
structure that contains many useful functions.

## DDEV

No additional setup is needed for this to run within DDEV in headless mode,
however if you want to use the UI mode within DDEV or watch the tests run in
headed mode then you can install https://github.com/justafish/ddev-drupal-playwright

e.g.
```
ddev exec -d /var/www/html/modules/contrib/canvas npx playwright test canary.spec.ts --headed
```

and then watch via web VNC at http://drupal.ddev.site:7905/ or connect directly
on http://drupal.ddev.site:5905

## Test Recipe
If you would like to setup a copy of the site locally to be in the same state as
running `drupal.setupCanvasTestSite()` you can do so with the following commands:

```
drush site:install minimal
drush recipe modules/contrib/canvas/tests/fixtures/recipes/base
```
You will then need to allow test modules to be enabled, if you're using `ddev-drupal-canvas-dev` you can do this with:
```
ddev drupal test:extensions-enable
```
or manually add the following to `settings.php`:
```
$settings['extension_discovery_scan_tests'] = TRUE;
```

Then install the test modules and test site content:
```
drush pm:install canvas_test_sdc canvas_test_code_components
drush recipe modules/contrib/canvas/tests/fixtures/recipes/test_site
```

See the [core recipes documentation](https://www.drupal.org/docs/extending-drupal/drupal-recipes)
if you would like to make changes to the test site.
