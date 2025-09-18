import '@testing-library/cypress/add-commands.js';

import { realType } from 'cypress-real-events'; // eslint-disable-line  @typescript-eslint/no-unused-vars

import { queries } from '@testing-library/dom';

import { realDnd } from './realDnd.js';
import { onlyVisibleChars } from './utils.js';

// This selector gets the preview iframe ensuring that it is initialized and that it is the currently active/swapped in element.
const initializedReadyPreviewIframeSelector =
  '[data-test-canvas-content-initialized="true"][data-canvas-swap-active="true"]';

const commandAsWebserver = (command) => {
  if (Cypress.env('testWebserverUser')) {
    return `sudo -u ${Cypress.env('testWebserverUser')} -E ${command}`;
  }
  return command;
};

Cypress.Commands.add('debugPause', (message = '') => {
  if (Cypress.env('debugPauses')) {
    cy.log(`Debug Pause: ${message}`);
    cy.pause();
  }
});

Cypress.Commands.add(
  'drupalCreateUser',
  ({ name, password, permissions = [] }, callback) => {
    const roleName = Math.random()
      .toString(36)
      .replace(/[^\w\d]/g, '')
      .substring(2, 15);
    if (permissions.length) {
      cy.drupalCreateRole({ permissions, name: roleName });
    }
    cy.drupalLoginAsAdmin(() => {
      cy.drupalRelativeURL('/admin/people/create');
      cy.get('input[name="name"]').type(name);
      cy.get('input[name="pass[pass1]"]').type(password);
      cy.get('input[name="pass[pass2]"]').type(password);
      if (permissions.length) {
        cy.get(`input[name="roles[${roleName}]`).click();
      }
      cy.get('#user-register-form').submit();
      cy.get('[data-drupal-messages]').should(($message) => {
        expect($message.text()).to.contain(
          'Created a new user account',
          `User "${name}" was created successfully.`,
        );
      });
      if (typeof callback === 'function') {
        callback.call(this);
      }
    });
  },
);

Cypress.Commands.add(
  'drupalCreateRole',
  ({ permissions, name = null }, callback) => {
    const roleName = name || Math.random().toString(36).substring(2, 15);
    cy.drupalLoginAsAdmin(async () => {
      cy.drupalRelativeURL('/admin/people/roles/add');
      cy.get('input[name="label"]').type(roleName);
      Cypress.$('input[name="label"]').trigger('formUpdated');
      cy.get('.user-role-form .machine-name-value').should('be.visible');
      let theMachineName = '';
      cy.contains('.user-role-form .machine-name-value', /^[a-z0-9_]/, {
        timeout: 5000,
      })
        .invoke('text')
        .then((machineName) => {
          theMachineName = machineName;
          cy.get('form').submit('#user-role_form');

          cy.drupalRelativeURL('/admin/people/permissions');
          permissions.forEach((permission) => {
            cy.get(`input[name="${theMachineName}[${permission}]"]`).click();
          });
          cy.get('form').submit('#user-admin-permissions');
          cy.drupalRelativeURL('/admin/people/permissions');
          if (typeof callback === 'function') {
            callback.call(window.self, machineName);
          }
        });
    });
  },
);

Cypress.Commands.add(
  'drupalEnableTheme',
  (themeMachineName, adminTheme = false) => {
    cy.drupalLoginAsAdmin(() => {
      const path = adminTheme
        ? '/admin/theme/install_admin/'
        : '/admin/theme/install_default/';
      cy.drupalRelativeURL(`${path}${themeMachineName}`);
      cy.get('#theme-installed').should('exist');
    });
  },
);

Cypress.Commands.add('drupalEnableThemeForCanvas', (themeMachineName) => {
  cy.drupalLoginAsAdmin(() => {
    cy.drupalRelativeURL(`admin/appearance/settings/${themeMachineName}`);
    cy.get(`input[name="use_canvas"]`).click();
    cy.get('form').submit('#system-theme-settings');
    cy.get(`input[name="use_canvas"]`).should('be.checked');
  });
});

Cypress.Commands.add(
  'drupalCanvasInstall',
  (extraModules = [], options = {}, extraPermissions = []) => {
    cy.task('log', `The setup file ${Cypress.env('setupFile')}`);
    cy.task('log', `Extra modules ${extraModules}`);
    cy.drupalInstall({
      setupFile: Cypress.env('setupFile'),
      extraModules,
      options,
      extraPermissions,
    });
  },
);

Cypress.Commands.add(
  'drupalInstall',
  (
    {
      setupFile = '',
      installProfile = 'nightwatch_testing',
      langcode = '',
      extraModules = [],
      extraPermissions = [],
      options = {},
    } = {},
    callback,
  ) => {
    cy.clearCookies();
    try {
      setupFile = setupFile ? `--setup-file "${setupFile}"` : '';
      installProfile = `--install-profile "${installProfile}"`;
      extraModules = extraModules.length
        ? `CANVAS_EXTRA_MODULES=${extraModules.join()}`
        : '';

      const disableAggregationEnv =
        Object.prototype.hasOwnProperty.call(options, 'disableAggregation') &&
        options.disableAggregation
          ? 'CANVAS_DISABLE_AGGREGATION=true'
          : '';

      extraPermissions = extraPermissions.length
        ? `CANVAS_EXTRA_PERMISSIONS="${extraPermissions.join()}"`
        : '';

      const langcodeOption = langcode ? `--langcode "${langcode}"` : '';
      const dbOption = Cypress.env('dbUrl')
        ? `--db-url ${Cypress.env('dbUrl')}`
        : '';

      const installCommand = commandAsWebserver(
        `${extraModules} ${disableAggregationEnv} ${extraPermissions} php ${Cypress.env('coreDir')}/scripts/test-site.php install ${setupFile} ${installProfile} ${langcodeOption} --base-url ${Cypress.env('baseUrl')} ${dbOption} --json`,
      );
      cy.exec(installCommand).then((install) => {
        const installData = JSON.parse(install.stdout);
        const url = new URL(Cypress.env('baseUrl'));
        Cypress.env('drupalDbPrefix', installData.db_prefix);
        Cypress.env('drupalSitePath', installData.site_path);
        Cypress.env('userAgent', installData.user_agent);
        Cypress.env('host', url.hostname);
        cy.visit('/', { failOnStatusCode: false }).then(() => {
          cy.drupalSession();
        });
      });
    } catch (error) {
      cy.task('log', `Failed Installing Drupal ${error}`);
    }
  },
);

Cypress.Commands.add('drupalInstallModule', (modules, force, callback) => {
  cy.drupalLoginAsAdmin(() => {
    cy.drupalRelativeURL('/admin/modules');

    // Open any collapsed patterns in the modules page.
    cy.get(
      '[data-drupal-selector="system-modules"] > details > summary[aria-expanded="false"][aria-controls^="edit-modules"]',
    ).then(($closedDetails) => {
      $closedDetails.each((index, closed) => {
        Cypress.$(closed).click();
      });
    });

    let moduleList = modules;
    if (!Array.isArray(modules)) {
      moduleList = [modules];
    }
    moduleList.forEach((module) => {
      cy.get(`form.system-modules [name="modules[${module}][enable]"]`).check();
    });

    cy.get('form.system-modules').submit();
    if (force) {
      cy.get('body').then(($body) => {
        if ($body.find('#system-modules-confirm-form')) {
          cy.get('#system-modules-confirm-form').submit();
        }
      });
    }
    cy.drupalRelativeURL('/admin/modules');
    moduleList.forEach((module) => {
      cy.get(`form.system-modules [name="modules[${module}][enable]"]`).should(
        ($checkbox) => {
          expect($checkbox.is(':checked'), `The ${module} module is installed`)
            .to.be.true;
          expect(
            $checkbox.is(':disabled'),
            `The ${module} install checkbox can not be unchecked`,
          ).to.be.true;
        },
      );
    });
  });
});

Cypress.Commands.add('drupalLogAndEnd', ({ onlyOnError = true }, callback) => {
  console.log(
    'Not sure this is even needed as cypress logs differently but who knows',
  );
  if (typeof callback === 'function') {
    callback.call(this);
  }
});

Cypress.Commands.add('drupalLogin', (name, password) => {
  cy.drupalUserIsLoggedIn((sessionExists) => {
    // Log the current user out if necessary.
    if (sessionExists) {
      cy.drupalLogout();
    }
    cy.session(
      [name, password],
      () => {
        cy.drupalSession();
        cy.drupalRelativeURL('/user/login');
        cy.get('input[name="name"]').type(name);
        cy.get('input[name="pass"]').type(password);
        cy.get('#user-login-form').submit();
        cy.get('h1').contains(name);
      },
      {
        validate() {
          cy.request('/')
            .its('body')
            .then((body) => {
              // @todo👇Is there a better way to validate that someone is logged in.
              cy.expect(body).to.contain(name);
            });
        },
      },
    );
  });
});

Cypress.Commands.add('drupalLoginAsAdmin', (callback) => {
  cy.drupalUserIsLoggedIn((sessionExists) => {
    if (sessionExists) {
      cy.drupalLogout();
    }
    const execCommand = commandAsWebserver(
      `php ${Cypress.env('coreDir')}/scripts/test-site.php user-login 1 --site-path ${Cypress.env('drupalSitePath')}`,
    );
    cy.exec(execCommand).then((userLink) => {
      cy.drupalRelativeURL(userLink.stdout);
      cy.drupalUserIsLoggedIn((sessionExists) => {
        if (!sessionExists) {
          throw new Error('Logging in as an admin user failed.');
        }
      });
    });
    if (typeof callback === 'function') {
      callback.call(this);
    }
    cy.drupalLogout({ silent: true });
  });
});

Cypress.Commands.add('drupalLogout', ({ silent = false } = {}, callback) => {
  cy.getAllCookies().then((result) => {
    result.forEach((cookie) => {
      if (cookie.name.match(/^S?SESS/)) {
        cy.clearCookie(cookie.name);
      }
    });
  });

  cy.drupalUserIsLoggedIn((sessionExists) => {
    if (silent) {
      if (sessionExists || sessionExists !== false) {
        throw new Error('Logging out failed.');
      }
    } else {
      expect(sessionExists).to.be.false;
    }
  });

  if (typeof callback === 'function') {
    callback.call(this);
  }
});

Cypress.Commands.add('drupalRelativeURL', (pathname, callback) => {
  cy.visit(`${pathname}`, { failOnStatusCode: false });
  if (typeof callback === 'function') {
    callback.call(this);
  }
});

Cypress.Commands.add('drupalUninstall', (callback) => {
  // immediately leave Canvas - otherwise when running headed the auto-save poll can fire during/after the db wipe and leave
  // the env in a weird state.
  cy.visit('/');
  const prefix = Cypress.env('drupalDbPrefix');

  const dbOption = Cypress.env('dbUrl')
    ? `--db-url ${Cypress.env('dbUrl')}`
    : '';
  try {
    if (!prefix || !prefix.length) {
      throw new Error(
        'Missing database prefix parameter, unable to uninstall Drupal (the initial install was probably unsuccessful).',
      );
    }

    const tearDownCommand = commandAsWebserver(
      `php ${Cypress.env('coreDir')}/scripts/test-site.php tear-down ${prefix} ${dbOption}`,
    );
    cy.exec(tearDownCommand).then(() => {
      if (typeof callback === 'function') {
        callback.call(window.self);
      }
    });
  } catch (error) {
    throw new Error(error);
  }
});

Cypress.Commands.add('drupalUserIsLoggedIn', (callback) => {
  if (typeof callback === 'function') {
    cy.getCookies().then((cookies) => {
      const sessionExists = cookies.some((cookie) =>
        cookie.name.match(/^S?SESS/),
      );
      callback.call(this, sessionExists);
    });
  }
});

Cypress.Commands.add('clearAutoSave', (type = 'node', id = '1') => {
  cy.log('type', type);
  cy.request({
    method: 'GET',
    url: `/canvas-test/clear-auto-save/${type}/${id}`,
  }).then((response) => {
    expect(response.status).to.eq(200);
  });
});

Cypress.Commands.add('setKeyValue', (collection, values) => {
  cy.request({
    method: 'POST',
    url: `/canvas-test/set-key-value/${collection}`,
    body: values,
  }).then((response) => {
    expect(response.status).to.eq(200);
  });
});

Cypress.Commands.add('drupalSession', () => {
  cy.visit('/', { failOnStatusCode: false }).then(() => {
    // With this cookie set, visits to the test site will be directed to a
    // version of the site running a test database.
    cy.setCookie(
      'SIMPLETEST_USER_AGENT',
      encodeURIComponent(Cypress.env('userAgent')),
      { domain: Cypress.env('host'), path: '/' },
    );
  });
});

/**
 * Ensures that the preview iframe is initialized and has content before continuing. Can be called
 * after performing an action that refreshes the iFrame to ensure subsequent actions wait for the new
 * content to have loaded.
 *
 * @param {string} selector
 *   The selector of the iframe to get.
 */
Cypress.Commands.add(
  'previewReady',
  (iframeSelector = initializedReadyPreviewIframeSelector) => {
    // Not logging these assertions to try and keep the command log a bit tidier
    cy.get('.canvasEditorFrameScalingContainer', { log: false }).should(
      'have.css',
      'opacity',
      '1',
    );
    cy.get(iframeSelector, { log: false, timeout: 10000 }).as('iframe');
    cy.get(iframeSelector, { log: false }).its('0.contentDocument', {
      log: false,
    });
    cy.log(`Preview '${iframeSelector}' initialized and has content document.`);
    cy.debugPause('previewReady');
    return cy.get('@iframe');
  },
);

/**
 * Gets an iframe element once its content has loaded.
 *
 * @param {string} selector
 *   The selector of the iframe to get.
 *
 * @return
 *   The Cypress-wrapped iframe.
 */
Cypress.Commands.add('getIframe', (selector) => {
  return cy.get(selector).its('0.contentDocument').should('exist');
});

/**
 * Gets the body content of an iframe
 *
 * @param {string} selector
 *   The selector of the iframe to get
 *
 * @return {object}
 *  The Cypress-wrapped iframe body.
 */
Cypress.Commands.add(
  'getIframeBody',
  (selector = initializedReadyPreviewIframeSelector) => {
    return cy
      .getIframe(selector)
      .its('body')
      .should('not.be.undefined')
      .then(cy.wrap);
  },
);

/**
 * Waits for element matching a selector to be present in an iframe.
 *
 * @param {string} selector
 *   The selector of what to wait on in the iframe.
 * @param {string} iframeSelector
 *   The selector of the iframe to check inside. Defaults to the first preview.
 * @param {number|null} customTimeout
 *   Optional: If the time to wait for the element should differ from the
 *   Cypress retry default duration.
 */
Cypress.Commands.add(
  'waitForElementInIframe',
  (
    selector,
    iframeSelector = initializedReadyPreviewIframeSelector,
    customTimeout,
  ) => {
    cy.document().then((doc) => {
      cy.get(true, {
        timeout: customTimeout || Cypress.config('defaultCommandTimeout'),
      }).should(() => {
        const frameContent = doc
          .querySelector(iframeSelector)
          ?.contentWindow?.document?.body.querySelector(selector);
        expect(
          !!frameContent,
          `'${selector}' was found in iframe '${iframeSelector}'`,
        ).to.equal(true);
      });
    });
  },
);

/**
 * Waits for element matching a selector to not be present in an iframe.
 *
 * @param {string} selector
 *   The selector of what to wait on in the iframe.
 * @param {string} iframeSelector
 *   The selector of the iframe to check inside. Defaults to the first preview.
 * @param {number|null} customTimeout
 *   Optional: If the time to wait for the element should differ from the
 *   Cypress retry default duration.
 */
Cypress.Commands.add(
  'waitForElementNotInIframe',
  (
    selector,
    iframeSelector = initializedReadyPreviewIframeSelector,
    customTimeout,
  ) => {
    cy.document().then((doc) => {
      cy.get(true, {
        timeout: customTimeout || Cypress.config('defaultCommandTimeout'),
      }).should(() => {
        const frameContent = doc
          .querySelector(iframeSelector)
          ?.contentWindow?.document?.body.querySelector(selector);
        expect(
          !!frameContent,
          `'${selector}' should not be in iframe '${iframeSelector}'`,
        ).to.equal(false);
      });
    });
  },
);

Cypress.Commands.add(
  'waitForElementContentInIframe',
  (
    selector,
    textContent,
    iframeSelector = initializedReadyPreviewIframeSelector,
    customTimeout,
  ) => {
    cy.document().then((doc) => {
      cy.get(true, {
        timeout: customTimeout || Cypress.config('defaultCommandTimeout'),
      }).should(() => {
        const frameContent = doc
          .querySelector(iframeSelector)
          ?.contentWindow?.document?.body.querySelectorAll(selector);
        expect(
          frameContent.length,
          `'${selector}' was found in iframe '${iframeSelector}'`,
        ).to.be.greaterThan(0);
        expect(
          Array.from(frameContent).filter((el) =>
            el.textContent?.includes(textContent),
          ).length,
          `${iframeSelector} in iframe includes text ${textContent}`,
        ).to.be.greaterThan(0);
      });
    });
  },
);

Cypress.Commands.add(
  'waitForElementHTMLInIframe',
  (
    selector,
    textContent,
    iframeSelector = initializedReadyPreviewIframeSelector,
    customTimeout,
  ) => {
    cy.document().then((doc) => {
      cy.get(true, {
        timeout: customTimeout || Cypress.config('defaultCommandTimeout'),
      }).should(() => {
        const frameContent = doc
          .querySelector(iframeSelector)
          ?.contentWindow?.document?.body.querySelector(selector);
        expect(
          !!frameContent,
          `'${selector}' was found in iframe '${iframeSelector}'`,
        ).to.equal(true);
        expect(
          frameContent?.innerHTML?.includes(textContent),
          `${iframeSelector} in iframe includes HTML ${textContent}`,
        ).to.equal(true);
      });
    });
  },
);

Cypress.Commands.add(
  'waitForElementContentNotInIframe',
  (
    selector,
    textContent,
    iframeSelector = initializedReadyPreviewIframeSelector,
    customTimeout,
  ) => {
    cy.document().then((doc) => {
      cy.get(true, {
        timeout: customTimeout || Cypress.config('defaultCommandTimeout'),
      }).should(() => {
        const frameContent = doc
          .querySelector(iframeSelector)
          ?.contentWindow?.document?.body.querySelector(selector);
        expect(
          !!frameContent,
          `'${selector}' was found in iframe '${iframeSelector}'`,
        ).to.equal(true);
        expect(
          !!(!!frameContent && !frameContent.textContent.includes(textContent)),
          `${iframeSelector} in iframe should no longer include text ${textContent}, but there is ${frameContent?.textContent}`,
        ).to.equal(true);
      });
    });
  },
);

/**
 * Gets element(s) matching a selector within an iframe and sends to a callback.
 * @example
 * ```javascript
 * cy.testInIframe('#some-id', (result) => {
 *   expect(result.text).to.equal('Hello World')
 * });
 * ```
 *
 * @param {string} selector
 *   The selector of what to query in the iframe.
 * @param {function} callback
 *   User supplied callback that receives the `selector` result as the argument.
 * @param {string} iframeSelector
 *   The selector of the iframe. Defaults to the first preview iframe.
 */
Cypress.Commands.add(
  'testInIframe',
  (
    selector,
    callback,
    iframeSelector = initializedReadyPreviewIframeSelector,
  ) => {
    cy.getIframeBody(iframeSelector).should((previewIframe) => {
      const queryResult = previewIframe.querySelectorAll(selector);
      let callbackArg = queryResult;
      if (queryResult.length === 1) {
        callbackArg = queryResult[0];
      } else if (queryResult.length === 0) {
        callbackArg = null;
      }

      callback(callbackArg, previewIframe);
    });
  },
);

Cypress.Commands.add('openLibraryPanel', () => {
  // Going to Layers and then Library ensures that if the Library is already open the panel won't get closed when clicking the Add button.
  cy.findByTestId('canvas-side-menu').findByLabelText('Layers').click();
  cy.findByTestId('canvas-side-menu').findByLabelText('Add').click();
  cy.findByTestId('canvas-components-library-loading').should('not.exist');
});

Cypress.Commands.add('openLayersPanel', () => {
  // Going to Library and then Layers ensures that if Layers is already open the panel won't get closed when clicking the Layers button.
  cy.findByTestId('canvas-side-menu').findByLabelText('Add').click();
  cy.findByTestId('canvas-side-menu').findByLabelText('Layers').click();
});

/**
 * Sets the value of input[type="range"] that is controlled by React in a way that ensures that React is notified of the change.
 * using .val(101).trigger('change') or .trigger('input') does not seem to work. https://github.com/cypress-io/cypress/issues/1570
 * @example
 * ```javascript
 *    cy.findByLabelText('Select zoom level').setRangeValue('101');
 * ```
 */
Cypress.Commands.add(
  'setRangeValue',
  { prevSubject: 'element' },
  (subject, value) => {
    const range = subject[0];
    const nativeInputValueSetter = Object.getOwnPropertyDescriptor(
      window.HTMLInputElement.prototype,
      'value',
    ).set;
    nativeInputValueSetter.call(range, value);
    const event = new Event('input', { bubbles: true });
    range.dispatchEvent(event);
    return cy.wrap(subject); // Ensure the command is chainable
  },
);

// Simulates the user using the mousewheel while holding the Control key
Cypress.Commands.add(
  'triggerMouseWheelWithCtrl',
  { prevSubject: 'element' },
  (subject, deltaY) => {
    const event = new WheelEvent('wheel', {
      deltaY: deltaY,
      ctrlKey: true,
      bubbles: true,
      cancelable: true,
      view: window,
    });
    subject[0].dispatchEvent(event);
    return cy.wrap(subject); // Ensure the command is chainable
  },
);

/**
 * Loads the Canvas page and waits to ensure initial backend requests have been returned and that the preview
 * iFrame is initialized and ready to be interacted with.
 *
 * * @param {Object} options
 *  *   An options object to configure the command.
 *  * @param {string} options.url
 *  *   The URL you want to visit - defaults to '/canvas/editor/node/1'.
 *  * @param {boolean} options.clearAutoSave
 *  *   Can be set to false if you want the auto-save data to persist on loading a new page - defaults to true.
 *  */
Cypress.Commands.add('loadURLandWaitForCanvasLoaded', (options = {}) => {
  const { url = 'canvas/editor/node/1', clearAutoSave = true } = options;

  if (clearAutoSave) {
    const [, , entityType, entityId] = url.split('/');
    cy.clearAutoSave(entityType, entityId);
  }
  cy.drupalRelativeURL(url);

  cy.previewReady();
});

let formBuildId = {};

Cypress.Commands.add('recordFormBuildId', { prevSubject: true }, (subject) => {
  formBuildId[subject.attr('id')] = subject
    .find('input[name="form_build_id"]')
    .val();
});

Cypress.Commands.add(
  'shouldHaveUpdatedFormBuildId',
  {
    prevSubject: true,
  },
  (subject, customTimeout) => {
    cy.wrap(subject, {
      timeout: customTimeout || Cypress.config('defaultCommandTimeout'),
    }).should((subject) => {
      const newFormBuildId = subject.find('input[name="form_build_id"]').val();
      expect(newFormBuildId).not.to.equal(formBuildId[subject.attr('id')]);
      formBuildId[subject.attr('id')] = newFormBuildId;
    });
  },
);

// Helper function used by the realDnd command.
Cypress.Commands.add('realDndRaw', realDnd);

/**
 * Drag and drop an element.
 *
 *  @param {string} subject
 *  The selector of the item to drag.
 *  @param {string} destination
 *  The selector of where to drop the item.
 *  @param {object} opts
 *  Options for the drag and drop.
 *
 *  @see https://github.com/dmtrKovalenko/cypress-real-events/pull/17 */
Cypress.Commands.add(
  'realDnd',
  { prevSubject: true },
  (subject, destination, opts) => {
    if (typeof destination === 'string') {
      cy.get(destination).then((el) => {
        cy.realDndRaw(subject, el, opts);
      });
    } else {
      cy.realDndRaw(subject, destination, opts);
    }
  },
);

Cypress.Commands.add('getElementScaledDimensions', ($item) => {
  cy.findByTestId('canvas-editor-frame-scaling').then(($parent) => {
    const computedStyle = window.getComputedStyle($parent[0]);
    const matrix = computedStyle.transform;
    if (matrix !== 'none') {
      const values = matrix.match(/matrix\(([^)]+)\)/)[1].split(', ');
      const scaleX = parseFloat(values[0]); // scaleX from matrix
      const scaleY = parseFloat(values[3]); // scaleY from matrix
      // Get the original width and height (before scaling)
      const originalWidth = $item.offsetWidth;
      const originalHeight = $item.offsetHeight;

      // Calculate scaled dimensions
      const scaledWidth = originalWidth * scaleX;
      const scaledHeight = originalHeight * scaleY;

      return {
        width: scaledWidth,
        height: scaledHeight,
      };
    }
    return {
      width: 0,
      height: 0,
    };
  });
});

Cypress.Commands.add(
  'clickComponentInPreview',
  (componentName, index = 0, regionId = 'content') => {
    cy.get(
      `#canvasPreviewOverlay .canvas--viewport-overlay .canvas--region-overlay__${regionId}`,
    )
      .findAllByLabelText(componentName)
      .eq(index)
      .click({ scrollBehavior: 'center', force: true });
    cy.debugPause(`Click Component in Preview: ${componentName} ${index}`);
  },
);

Cypress.Commands.add(
  'getComponentInPreview',
  (componentName, index = 0, regionId = 'content') => {
    return cy
      .get(
        `#canvasPreviewOverlay .canvas--viewport-overlay .canvas--region-overlay__${regionId}`,
      )
      .findAllByLabelText(componentName)
      .eq(index);
  },
);

Cypress.Commands.add(
  'getAllComponentsInPreview',
  (componentName, regionId = 'content') => {
    return cy
      .get(
        `#canvasPreviewOverlay .canvas--viewport-overlay .canvas--region-overlay__${regionId}`,
      )
      .findAllByLabelText(componentName);
  },
);

Cypress.Commands.add(
  'waitForComponentNotInPreview',
  (
    componentName,
    iframeSelector = initializedReadyPreviewIframeSelector,
    customTimeout,
  ) => {
    cy.document().then((doc) => {
      cy.get(true, {
        timeout: customTimeout || Cypress.config('defaultCommandTimeout'),
      }).should(() => {
        const frameContent = doc
          .querySelector(iframeSelector)
          ?.contentWindow?.document?.body.querySelector(
            `[aria-label="${componentName}"]`,
          );
        expect(
          !!frameContent,
          `'${componentName}' was found in iframe '${iframeSelector}'`,
        ).not.to.equal(true);
      });
    });
  },
);

Cypress.Commands.add(
  'clickComponentInLayersView',
  (componentName, index = 0) => {
    cy.get('.primaryPanelContent')
      .findAllByLabelText(componentName)
      .eq(index)
      .click('top');
  },
);
Cypress.Commands.add(
  'clickOptionInContextMenuInLayers',
  (componentName, index = 0, menuOption) => {
    cy.clickComponentInLayersView(componentName, index);
    cy.get('.primaryPanelContent')
      .findAllByLabelText(componentName)
      .eq(index)
      .within(() => {
        // Force because the dots button has 0 height and width until you hover.
        cy.findAllByLabelText('Open contextual menu')
          .filter(':visible')
          .click({ force: true });
      });

    cy.findByRole('menuitem', {
      name: menuOption,
      exact: false,
    }).click();
  },
);

Cypress.Commands.add('checkSiblings', (firstQuery, secondQuery) => {
  // Function to resolve elements from a query (either string or chain)
  const resolveElement = (query) => {
    if (typeof query === 'string') {
      return cy.get(query);
    }
    return query;
  };

  // Resolve both elements
  resolveElement(firstQuery).then(($firstElements) => {
    resolveElement(secondQuery).then(($secondElements) => {
      let siblingPairs = [];

      // Iterate over each pair of elements to check sibling relationships
      $firstElements.each((_, firstElement) => {
        $secondElements.each((_, secondElement) => {
          const firstParent = Cypress.$(firstElement).parent();
          const secondParent = Cypress.$(secondElement).parent();

          // Check if they have the same parent
          if (firstParent[0] === secondParent[0]) {
            siblingPairs.push({ firstElement, secondElement });
          }
        });
      });

      // Expect that at least one pair of elements are siblings
      expect(siblingPairs.length).to.be.greaterThan(0);
    });
  });
});

/**
 * Hide the left, right, top and zoom control panels so that they don't get
 * in the way when cypress is performing visibility checks etc.
 */
Cypress.Commands.add('hidePanels', () => {
  function hide($el) {
    $el.css({ display: 'none' });
  }

  cy.findByTestId('canvas-primary-panel').then(hide);
  cy.findByTestId('canvas-topbar').then(hide);
  cy.findByTestId('canvas-contextual-panel').then(hide);
  cy.findByTestId('canvas-editor-frame-controls').then(hide);
});

/**
 * Show the left, right, top and zoom control panels after they have been hidden with cy.hidePanels();
 */
Cypress.Commands.add('showPanels', () => {
  function show($el) {
    $el.css({ display: '' });
  }

  cy.findByTestId('canvas-primary-panel').then(show);
  cy.findByTestId('canvas-topbar').then(show);
  cy.findByTestId('canvas-contextual-panel').then(show);
  cy.findByTestId('canvas-editor-frame-controls').then(show);
});

/**
 * Set the editor frame to be static and scrollable so that Cypress is better able to interact with elements in the editor frame.
 */
Cypress.Commands.add('disableEditorPanning', () => {
  cy.findByTestId('canvas-editor-frame').then(($editorFrame) => {
    $editorFrame.css({ padding: '100px 0 0 0' });
  });

  cy.findByTestId('canvas-editor-frame')
    .parent()
    .then(($parent) => {
      $parent.css({
        overflow: 'visible',
        display: 'block',
        position: 'static',
      });
    });
  cy.get('body').then(($body) => {
    $body.css({ overflow: 'visible' });
  });
});

/**
 * Reset the editor frame to its normal behavior after disabling it with cy.disableEditorPanning();
 */
Cypress.Commands.add('reEnableCanvasPanning', () => {
  cy.findByTestId('canvas-editor-frame').then(($editorFrame) => {
    $editorFrame.css({ padding: '' });
  });

  cy.findByTestId('canvas-editor-frame')
    .parent()
    .then(($parent) => {
      $parent.css({ overflow: '', display: '', position: '' });
    });
  cy.get('body').then(($body) => {
    $body.css({ overflow: '' });
  });
});

/**
 * Assert the state of a Toggle component.
 * @see ui/src/components/form/components/Toggle.tsx
 */
Cypress.Commands.add(
  'assertToggleState',
  { prevSubject: 'element' },
  (subject, expectedState) => {
    cy.wrap(subject).then(($el) => {
      const $button = $el.is('button') ? $el : $el.find('button');
      cy.wrap($button)
        .should(
          'have.attr',
          'data-state',
          expectedState ? 'checked' : 'unchecked',
        )
        .and('have.attr', 'aria-checked', expectedState ? 'true' : 'false');
    });
    return cy.wrap(subject);
  },
);

/**
 * Toggle the state of a Toggle component.
 * @see ui/src/components/form/components/Toggle.tsx
 */
Cypress.Commands.add('toggleToggle', { prevSubject: 'element' }, (subject) => {
  cy.wrap(subject).click();
  return cy.wrap(subject);
});

Cypress.Commands.add('editHeroComponent', () => {
  // The right panel has opened.
  cy.findByTestId('canvas-contextual-panel').should('exist');

  const expectedLabels = [
    'Heading',
    'Sub-heading',
    'CTA 1 text',
    'CTA 1 link',
    'CTA 2 text',
  ];

  // The drawer contains a component edit form.
  cy.get(
    '[class*="contextualPanel"] [data-drupal-selector="component-instance-form"]',
  ).within(() => {
    cy.findAllByLabelText('Heading').should('exist');
  });

  cy.get(
    '[class*="contextualPanel"] [data-drupal-selector="component-instance-form"]',
  ).then(($form) => {
    expect($form).to.exist;
    $form.find('label').each((index, label) => {
      expect(label.textContent).to.equal(expectedLabels[index]);
    });
  });

  cy.findByLabelText('Heading')
    .should('have.value', 'hello, world!')
    .invoke('attr', 'type')
    .should('eq', 'text');

  cy.findByLabelText('CTA 1 link').should('have.value', 'https://drupal.org');

  const heroSelectors = {
    Heading: '.my-hero__heading',
    'Sub-heading': 'h1 ~ p',
    'CTA 1 text': '.my-hero__cta:first-child',
    'CTA 2 text': '.my-hero__cta:last-child',
  };
  const heroBefore = {
    Heading: 'hello, world!',
    'Sub-heading': '',
    'CTA 1 text': '',
    'CTA 2 text': '',
  };

  // Confirm the current values of the first "My Hero" component so we can
  // be certain these values later change.
  cy.testInIframe('.my-hero__container', (heroes) => {
    const hero = heroes[0];
    Object.entries(heroSelectors).forEach(([prop, selector]) => {
      const heroText = onlyVisibleChars(
        hero.querySelector(selector).textContent,
      );
      if (heroBefore[prop]) {
        expect(heroText, `${prop} should be ${heroBefore[prop]}`).to.equal(
          heroBefore[prop],
        );
      } else {
        expect(heroText, `${prop} should be empty but it is "${heroText}"`).to
          .be.empty;
      }
    });
    expect(
      hero.querySelector(heroSelectors['CTA 1 text']).getAttribute('href'),
    ).to.equal('https://drupal.org');
  });

  const newValues = {
    Heading: 'You parked your car',
    'Sub-heading': 'Over the sidewalk',
    'CTA 1 text': 'ponytail',
    'CTA 2 text': 'stuck',
    'CTA 1 link': 'https://hoobastank.com',
  };

  // Monitor the endpoint that processes changed values in the prop edit form.
  cy.intercept('POST', '**/canvas/api/v0/layout/node/1').as('getPreview');
  cy.intercept('PATCH', '**/canvas/api/v0/layout/node/1').as('patchPreview');
  expectedLabels.forEach((label) => {
    // Type a new value into a given input.
    cy.findByLabelText(label).focus();
    cy.findByLabelText(label).clear({ force: true });
    cy.findByLabelText(label).type(newValues[label], { force: true });
    // If an autocomplete field is updated without choosing an autocomplete
    // suggestion, it will not update the store + preview until it is blurred.
    if (label === 'CTA 1 link') {
      cy.findByLabelText('Heading').focus();
    }
    // Wait for completion of the request triggered by our typing. This
    // ensures that the `testInIframe` ~10 lines down is working with an iframe that
    // has fully responded to these value changes.
    cy.wait('@patchPreview');
    // Confirm React is properly handling form state by confirming the input
    // has the value we typed into it.
    cy.findByLabelText(label).should('have.value', newValues[label]);
  });

  // New values were typed into the prop form inputs, now enter the iframe
  // and confirm the component reflects these new values.
  cy.waitForElementContentInIframe(heroSelectors.Heading, newValues.Heading);
  cy.waitForElementContentInIframe(
    heroSelectors['Sub-heading'],
    newValues['Sub-heading'],
  );
  cy.waitForElementContentInIframe(
    heroSelectors['CTA 1 text'],
    newValues['CTA 1 text'],
  );
  cy.waitForElementContentInIframe(
    heroSelectors['CTA 2 text'],
    newValues['CTA 2 text'],
  );
});

Cypress.Commands.add('expandComponentLayer', (componentName) => {
  cy.get('.primaryPanelContent').as('layersTree');
  // Open the component in the Tree of layers.
  cy.get('@layersTree')
    .findByLabelText(componentName)
    .findByLabelText('Expand component tree')
    .click();
});

Cypress.Commands.add('expandSlotLayer', (slotName) => {
  cy.get('.primaryPanelContent').as('layersTree');
  // Open the slot in the Tree of layers.
  cy.get('@layersTree')
    .findAllByLabelText(slotName)
    .first()
    .findByLabelText('Expand slot')
    .click();
});
Cypress.Commands.add('focusRegion', (regionName) => {
  cy.get('.primaryPanelContent').as('layersTree');
  // Focus the region
  cy.get('@layersTree').findByText(regionName).dblclick();
});
Cypress.Commands.add('returnToContentRegion', () => {
  cy.findByTestId('canvas-topbar')
    .findByLabelText('Back to Content region')
    .click();
});
Cypress.Commands.add('sendComponentToRegion', (componentName, regionName) => {
  cy.findByTestId('canvas-primary-panel').as('layersTree');
  cy.get('@layersTree')
    .findAllByText(componentName)
    .first()
    .trigger('contextmenu');
  cy.findByText('Move to global region').click();
  cy.get(`[data-region-name="${regionName}"]`).click();
});
Cypress.Commands.add(
  'publishAllPendingChanges',
  (titles, expectNoErrors = true) => {
    let titlesToMatch = titles;
    if (!Array.isArray(titles)) {
      titlesToMatch = [titles];
    }
    const changeCount = titlesToMatch.length;
    // Publish changes and make sure image persists.
    // Wait for any pending changes to refresh.
    cy.findByText(/Review \d+ change/, { timeout: 20000 }).should('exist');
    cy.get('button', { timeout: 20000 })
      .contains(`Review ${changeCount} change`, { timeout: 20000 })
      .as('review');
    // We break this up to allow for the pending changes refresh which can disable
    // the button whilst it is loading.
    cy.get('@review').click();
    // Enable extended debug output from failed publishing.
    cy.intercept('**/canvas/api/v0/auto-saves/publish');
    cy.findByTestId('canvas-publish-reviews-content')
      .as('publishReview')
      .should('exist');
    // We put the whole publish review step in a single should so it can be
    // retried as a group. Unfortunately this requires dropping down to raw
    // testing library queries because you can't make use of cypress commands
    // inside a should block.
    cy.get('@publishReview', { timeout: 15000 }).should(async (element) => {
      const container = element[0];
      const matchers = titlesToMatch.map((title) => {
        return async () => {
          const entity = await queries.findByText(container, title);
          // We use the existence of the entity to confirm
          expect(entity).to.exist;
        };
      });
      await Promise.all(matchers);
    });
    cy.findByTestId('canvas-publish-review-select-all').click();
    cy.findByText(`Publish ${changeCount} selected`).click();
    if (expectNoErrors) {
      cy.findByText('All changes published!').should('exist');
      cy.findByText('Errors').should('not.exist');
    }
  },
);

Cypress.Commands.add('waitForWindowProcess', (conditionFn, options = {}) => {
  const defaultOptions = {
    timeout: 10000,
    interval: 100,
  };

  const finalOptions = { ...defaultOptions, ...options };

  cy.window({ timeout: finalOptions.timeout }).should((win) => {
    expect(conditionFn(win)).to.be.true;
  });
});

/**
 * Wait for elements to stop being added to the DOM.
 *
 * @param {string} selector
 *   CSS selector to check for stabilization.
 * @param {number} timeout
 *   Maximum time to wait in milliseconds.
 * @param {number} checkInterval
 *   How frequently to check in milliseconds.
 * @param {number} stabilityThreshold
 *   Number of consistent checks required to consider stable.
 */
Cypress.Commands.add(
  'waitForElementsToStabilize',
  (selector, timeout = 10000, checkInterval = 100, stabilityThreshold = 3) => {
    return new Cypress.Promise((resolve) => {
      let lastCount = null;
      let stableCount = 0;
      const startTime = Date.now();

      const checkElements = () => {
        const currentCount = Cypress.$(selector).length;

        if (lastCount === currentCount) {
          stableCount++;
          if (stableCount >= stabilityThreshold) {
            resolve(currentCount);
            return;
          }
        } else {
          lastCount = currentCount;
          stableCount = 0;
        }

        if (Date.now() - startTime >= timeout) {
          // If timeout is exceeded, resolve regardless of stability status.
          resolve(currentCount);
          return;
        }
        setTimeout(checkElements, checkInterval);
      };
      checkElements();
    });
  },
);

Cypress.Commands.add('waitForAjax', () => {
  cy.waitForWindowProcess(
    (win) =>
      !win.Drupal.ajax.instances.some(
        (instance) => instance && instance.ajaxing === true,
      ),
  );
});
