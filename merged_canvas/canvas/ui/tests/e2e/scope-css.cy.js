// Note that in the styles, several instances of `background` are accompanied
// by `background-size` in order to trigger the variable replacement that
// happens when CSS shorthand declarations are accompanied by longhand operating
// within the same style areas.
const testCss = {
  assortment: `
  .dialog {
    color: #444;
  }
  .dialog .inside-dialog {
    color: #555;
  }
  
  .regular-old-class {
    color: #666;
  }
  .js .somewhere-inside {
    color: #777;
  }
  html .somewhere-else {
    color: #888;
  }`,
  variables: `
  :root {
    --color-foo: #112233;
    --color-bar: #223344;
    --color-beep: #8899AA;
    --color-bop: #BBCCDD;
  }
  `,
  usesVariables: `
  .variable-ordinary {
    color: var(--color-foo);
  }
  .non-exist-variable-ordinary {
      color: var(--color-noop);
  }
  .variable-shorthand {
    background: no-repeat var(--color-bar) 50% 50%;
  }
  .non-exist-variable-shorthand {
    background: no-repeat var(--color-noop) 50% 50%;
  }
  .variable-shorthand-with-non-shorthand-too {
    background: no-repeat var(--color-bar) 50% 50%;
    background-size: 100% 100%;
  }
  .non-exist-variable-shorthand-with-non-shorthand-too {
    background: no-repeat var(--color-noop) 50% 50%;
    background-size: 100% 100%;
  }
  .whitespace {
    background: var( --color-foo );
    background-size: 100% 100%;
  }
  .default-values {
    background: var(--color-gone, var(--color-beep));
    background-size: 100% 100%;
  }
  .nested-whitespace-and-default {
    background: var( --color-nah , var( --color-nope, var(--color-noop , var(--color-bop ) )));
    background-size: 100% 100%;
  }
  `,
};

describe('The Scope CSS utility', () => {
  beforeEach(() => {
    cy.drupalCanvasInstall();
    cy.drupalLogin('canvasUser', 'canvasUser');
    // Intercept allowing us to load CSS from the `testCSS` object declared
    // above as if it were loading file contents.
    cy.intercept(`/test-css/*`, (req) => {
      const propertyName = `${req.url.split('/').pop()}`.replace('.css', '');
      req.reply({
        statusCode: 200,
        body: testCss[propertyName],
      });
    });
  });

  afterEach(() => {
    cy.drupalUninstall();
  });

  const strip = (str) =>
    str
      .replace(/(?:\r\n|\r|\n)/g, '')
      .replace(/\s+/g, ' ')
      .replace(/\u00a0/g, ' ')
      .trim();

  it('Wraps styles and accounts for top level selectors', () => {
    cy.loadURLandWaitForCanvasLoaded();

    cy.waitForElementsToStabilize('[data-dialog-style-from]');
    cy.window().then((win) => {
      win.Drupal.AjaxCommands.prototype.scope_css(
        {
          href: '/test-css/assortment.css',
        },
        '.dialog',
      );
    });
    cy.get('[data-dialog-style-from="/test-css/assortment.css"]').should(
      ($addedStyle) => {
        expect($addedStyle).to.have.length(1);
        const stylesheet = new CSSStyleSheet();
        stylesheet.replaceSync($addedStyle.text());
        expect(
          stylesheet.cssRules[0].cssText,
          'The style begins with .dialog, which is the same as the scope wrapper, so it is not wrapped',
        ).to.equal('.dialog { color: rgb(68, 68, 68); }');
        expect(
          stylesheet.cssRules[1].cssText,
          'The style begins with .dialog, which is the same as the scope wrapper, so it is not wrapped',
        ).to.equal('.dialog .inside-dialog { color: rgb(85, 85, 85); }');
        expect(
          strip(stylesheet.cssRules[2].cssText),
          'The style is wrapped in @scope (.dialog)',
        ).to.equal(
          '@scope (.dialog) { .regular-old-class { color: rgb(102, 102, 102); }}',
        );
        expect(
          strip(stylesheet.cssRules[3].cssText),
          'The style begins with a class present in <html> so the scope selector appears after it',
        ).to.equal(
          `@scope (.js) { .dialog .somewhere-inside { color: rgb(119, 119, 119); }}`,
        );
        expect(
          strip(stylesheet.cssRules[4].cssText),
          'The style begins with an <html> tag so the scope selector appears after it',
        ).to.equal(
          `@scope (html) { .dialog .somewhere-else { color: rgb(136, 136, 136); }}`,
        );
      },
    );
  });

  it('Handles CSS variables and :root', () => {
    cy.loadURLandWaitForCanvasLoaded();
    cy.get('[data-dialog-style-from]').should('exist');
    cy.waitForElementsToStabilize('[data-dialog-style-from]');
    // Add a class to the AJAX added CSS on load to distinguish them from the
    // stylesheets added below.
    cy.get('[data-dialog-style-from]').then(($addedStyles) => {
      $addedStyles.addClass('added-on-load');
    });

    cy.window().then((win) => {
      win.Drupal.AjaxCommands.prototype.scope_css(
        {
          href: '/test-css/variables.css',
        },
        '.cool-scope',
      );
      win.Drupal.AjaxCommands.prototype.scope_css(
        {
          href: '/test-css/usesVariables.css',
        },
        '.cool-scope',
      );
    });

    cy.get('[data-dialog-style-from]:not(.added-on-load)').should(
      ($addedStyles) => {
        expect($addedStyles).to.have.length(2);
        const varsStylesheet = new CSSStyleSheet();
        const usesVarsStylesheet = new CSSStyleSheet();
        varsStylesheet.replaceSync($addedStyles.eq(0).text());
        usesVarsStylesheet.replaceSync($addedStyles.eq(1).text());
        expect(varsStylesheet.cssRules.length).to.equal(1);
        expect(usesVarsStylesheet.cssRules.length).to.equal(9);
        expect(
          varsStylesheet.cssRules[0].cssText,
          ':root scope + CSS variables do not get prefixed',
        ).to.equal(
          ':root { --color-foo: #112233; --color-bar: #223344; --color-beep: #8899AA; --color-bop: #BBCCDD; }',
        );
        expect(
          usesVarsStylesheet.cssRules[0].cssText,
          'CSS variable with value keeps its value as it is not in shorthand declaration with adjacent conflicting longhand',
        ).to.equal(
          '@scope (.cool-scope) {\n  .variable-ordinary { color: var(--color-foo); }\n}',
        );
        expect(
          usesVarsStylesheet.cssRules[1].cssText,
          'non existent CSS variable remains in the style awaiting it someday existing',
        ).to.equal(
          '@scope (.cool-scope) {\n  .non-exist-variable-ordinary { color: var(--color-noop); }\n}',
        );
        expect(
          usesVarsStylesheet.cssRules[2].cssText,
          'shorthand with an existing variable has the variable call intact because there is no conflicting longhand for background',
        ).to.equal(
          '@scope (.cool-scope) {\n  .variable-shorthand { background: no-repeat var(--color-bar) 50% 50%; }\n}',
        );
        expect(
          usesVarsStylesheet.cssRules[3].cssText,
          'shorthand using a non-existing variable without a sibling non-shorthand will preserve the style',
        ).to.equal(
          '@scope (.cool-scope) {\n  .non-exist-variable-shorthand { background: no-repeat var(--color-noop) 50% 50%; }\n}',
        );
        expect(
          usesVarsStylesheet.cssRules[4].cssText,
          'The background shorthand remains intact because `scope_css()` swapped out the CSS variable with its value',
        ).to.equal(
          '@scope (.cool-scope) {\n  .variable-shorthand-with-non-shorthand-too { background: 50% 50% / 100% 100% no-repeat rgb(34, 51, 68); }\n}',
        );
        expect(
          usesVarsStylesheet.cssRules[5].cssText,
          'using CSS shorthand + a non-shorthand property with a non-existing variable demonstrates the bug. No background-color or other shorthand configured properties present.',
        ).to.equal(
          '@scope (.cool-scope) {\n  .non-exist-variable-shorthand-with-non-shorthand-too { background-size: 100% 100%; }\n}',
        );
        expect(
          usesVarsStylesheet.cssRules[6].cssText,
          'Variable replacement can handle whitespace',
        ).to.equal(
          '@scope (.cool-scope) {\n  .whitespace { background: 0% 0% / 100% 100% rgb(17, 34, 51); }\n}',
        );
        expect(
          usesVarsStylesheet.cssRules[7].cssText,
          'Variable placement can handle default values',
        ).to.equal(
          '@scope (.cool-scope) {\n  .default-values { background: 0% 0% / 100% 100% rgb(136, 153, 170); }\n}',
        );
        expect(
          usesVarsStylesheet.cssRules[8].cssText,
          'Variable replacement can handle default values many levels deep with random whitespace scattered throughout',
        ).to.equal(
          '@scope (.cool-scope) {\n  .nested-whitespace-and-default { background: 0% 0% / 100% 100% rgb(187, 204, 221); }\n}',
        );
      },
    );
  });
});
