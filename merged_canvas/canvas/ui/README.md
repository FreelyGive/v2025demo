# Drupal Canvas
This document outlines how to contribute to Drupal Canvas.

## Local development environment
There are two supported options for creating a local development environment: DDEV (containers) and native (or "bare metal").

- [**DDEV**](https://ddev.com/) is [the recommended solution](https://www.drupal.org/docs/official_docs/local-development-guide) for local Drupal development. <!-- c.f. https://www.drupal.org/project/ideas/issues/2965681 --> It requires minimal setup for this project (due to our custom add-on) and few specialized skills to set it up and maintain it. Its main drawback is that our add-on only officially supports macOS (though everything other than the Cypress tests will work fine anywhere). It has the benefits of isolation (Docker) and automation. It is recommended if you already use DDEV or aren't already invested in another solution.
- **Native development** involves installing system dependencies directly on your machine (i.e., not inside a container). Its main drawbacks are lack of isolation and the amount of manual configuration required to set it up. It's main benefit may be the number of core contributors that already work this way. There's no reason to switch away from this approach if you prefer it.

### DDEV
DDEV setup is fully automated through our custom add-on: https://github.com/TravisCarden/ddev-drupal-canvas-dev. Follow the instructions there to get started.

### Native development

#### Prerequisites
- Enable the Drupal Canvas module

#### Build steps
1. `npm install` from /modules/canvas/ui
2. `npm run build`

##### Development mode
1. `npm install` from /modules/canvas/ui
2. Next, you'll start a development server that runs at `http://localhost:5173` (ensure port is available)
    - To use a different URL (e.g., for DDEV containers), set `VITE_SERVER_ORIGIN` in `.env`
      - Note: this is already handled if you use the Canvas DDEV add-on ([`TravisCarden/ddev-drupal-canvas-dev`](https://github.com/TravisCarden/ddev-drupal-canvas-dev))
    - By default, the Vite dev server will allow cross-origin requests. To restrict cross-origin requests, set `VITE_SERVER_CORS_ALLOW_ORIGIN` in `.env`.
      - You may want to do this if you're developing in an environment where your Vite dev server is accessible on a public network, e.g. GitHub Codespaces.
      - See the [Vite docs](https://vite.dev/config/server-options#cors) for more information.
3. `npm run drupaldev`
4. Enable the Drupal Canvas Vite Integration module (`canvas_vite`)
5. Clear cache (`drush cr` or `/admin/config/development/performance`)
6. Navigate to `/canvas` to view app

#### Running Unit/Component Tests
- `npm run cy:component`

#### Running E2E Tests
- In your `.env` file, set `BASE_URL` and `DB_URL`. See `.env.example` for an example.
- The e2e tests use the application file in /ui/dist, which is only updated by
  running `npm run build`. Be sure to do this before running e2e tests.
- Then, _either_:
  - Use `npm run cy:open` to run e2e with the (very helpful) Cypress GUI test runner (do that in its own terminal). This runs the test in a visible browser.
  - Use `npm run cy:run` to run the same e2e tests in the terminal (this is also the command used by Gitlab CI). This runs the test in a "headless" browser.

#### Debugging E2E Tests
For debugging purposes, if you would like the tests to pause during key events, you can set the `debugPauses` config setting to true in ui/cypress.config.js.
Currently, this will pause the tests when a component is clicked in the preview, `clickComponentInPreview()`, and when the preview is ready, `previewReady()`.

You can add the `cy.debugPause()` command anywhere else you want to pause the test and log a message. The command accepts a message to log and calls [cy.pause()](https://docs.cypress.io/api/commands/pause).

## Testing Strategy
Our testing strategy leverages [Cypress.io](https://www.cypress.io) for both end-to-end (e2e) and component testing, integrated with [Testing Library](https://testing-library.com/) to ensure robust and maintainable tests.

### Principles
1. We are not testing Drupal core functionality outside the Drupal Canvas — any global setup tasks should be in a base install profile where possible
2. All specs are isolated and start from a fresh database and filesystem import created (e.g. no dependencies between tests)
3. Every spec file is responsible for setting up the test environment for that set of scenarios (e.g. package imports, enabling contrib modules outside the basic install)

### Why Cypress?
1. **Ease of Use:** Cypress is highly approachable and user-friendly, enabling contributors to quickly become productive.
2. **Consistency:** Using Cypress for e2e, component and unit testing ensures a consistent testing environment and reduces the learning curve.
3. **Debugging:** Cypress provides an intuitive interface for debugging, which is consistent across both e2e and component tests.
4. **Proven:** Cypress is a long-established and well-supported tool capable of meeting our needs.

Points 1 and 3 in particular have led to our choice to implement Cypress testing for this application over the Nightwatch-based solution provided by Drupal Core.

### Best Practices
To mitigate potential issues such as flakiness and to ensure our tests reflect actual user interactions as closely as possible we adhere to the following best practices:

1. **Avoid Direct DOM Manipulation:** We use [`@testing-library/cypress`](https://testing-library.com/docs/cypress-testing-library/intro) to interact with the DOM in a way that reflects user interactions. This means avoiding direct `querySelector` calls and instead using methods like `findByRole`, `findByText`, etc.
2. **ESLint Rules:** We enforce `eslint-plugin-testing-library` and `eslint-plugin-cypress` rules to ensure tests are written in a maintainable and user-centric manner.
3. **Centralize repeated actions:** In e2e test in particular, where possible, testing actions (such as logging in) should be centralized in a commands file in the `cypress/support/` directory.

Further documentation on best practices for writing Cypress tests can be found in the [Cypress documentation](https://docs.cypress.io/guides/core-concepts/introduction-to-cypress) and [Testing Library Guiding Principles](https://testing-library.com/docs/guiding-principles).

### Continuous Integration
We are working on integrating Cypress tests into our CI pipeline to ensure that all tests are run consistently and reliably. This includes setting up the necessary infrastructure and addressing any performance concerns.

We will periodically evaluate using Cypress for our **unit tests** and compare it with other testing frameworks (e.g. `vitest`) to ensure we are making the best trade-offs between ease of use, functionality and speed/performance.

## Styling

Drupal Canvas uses the [Radix Themes component library](https://www.radix-ui.com/themes/docs/overview/getting-started). Custom styling is done using [CSS modules](https://github.com/css-modules/css-modules) and relying on design tokens provided by Radix Themes as much as possible. Custom components should leverage [Radix primitives](https://www.radix-ui.com/primitives/docs/overview/introduction) as appropriate.


### Design Tokens

Design tokens are defined in the `ui/src/styles/tokens` directory. File naming conventions follow the [Radix Themes token naming conventions](https://www.radix-ui.com/themes/docs/theme/overview#tokens) ([source code](https://github.com/radix-ui/themes/tree/main/packages/radix-ui-themes/src/styles/tokens)).

* Customizing design tokens provided by Radix Themes should be done by redefining CSS variables under the `.radix-themes` class.
* New design tokens are added under the `.canvas-app` class.

### Styling Code Setup

Style definitions are imported in `ui/src/main.tsx`:

| Imported file  | Description |
| -------------- | ----------- |
| `ui/src/styles/radix-themes.tsx` | All style definitions from Radix Themes with the colors selectively imported to reduce bundle size. |
| `ui/src/styles/index.css` |  Design token overrides and additions, as well as some base style definitions and resets which are meant to be global, thus are not scoped as CSS modules. |


The [`<Theme>` component by Radix Themes](https://www.radix-ui.com/themes/docs/components/theme) is added in `ui/src/main.tsx`. We make little to no use of the customization props this component offers, however, it is required as a context provider for components of Radix Themes.
