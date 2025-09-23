// ***********************************************************
// This example support/e2e.js is processed and
// loaded automatically before your test files.
//
// This is a great place to put global configuration and
// behavior that modifies Cypress.
//
// You can change the location of this file or turn off
// automatically serving support files with the
// 'supportFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/configuration
// ***********************************************************

// Import commands.js using ES2015 syntax:
import '@cypress/skip-test/support.js';
import './commands.js';
import 'cypress-axe';
import 'cypress-real-events';

import installLogsCollector from 'cypress-terminal-report/src/installLogsCollector.js';

installLogsCollector();

// Alternatively you can use CommonJS syntax:
// require('./commands')

Cypress.on('uncaught:exception', (err, runnable) => {
  // This is safe to ignore, and often is with Cypress E2E tests.
  // @see https://github.com/w3c/csswg-drafts/issues/6173
  // @see https://github.com/w3c/csswg-drafts/issues/6185
  if (
    err.message.includes('ResizeObserver loop limit exceeded') ||
    err.message.includes('ResizeObserver loop completed')
  ) {
    return false;
  }
});
