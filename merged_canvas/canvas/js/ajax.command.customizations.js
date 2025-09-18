/**
 * @file
 * Customizations to AJAX commands.
 */

/* global csstree */
(function (Drupal, csstree, drupalSettings) {
  // Keeps track of shorthand properties and their corresponding longhand
  // properties.
  const shorthands = {
    margin: ['margin-top', 'margin-right', 'margin-bottom', 'margin-left'],
    'margin-inline': ['margin-inline-start', 'margin-inline-end'],
    'margin-block': ['margin-block-start', 'margin-block-end'],
    padding: ['padding-top', 'padding-right', 'padding-bottom', 'padding-left'],
    'padding-inline': ['padding-inline-start', 'padding-inline-end'],
    'padding-block': ['padding-block-start', 'padding-block-end'],
    border: ['border-width', 'border-style', 'border-color'],
    'border-radius': [
      'border-top-left-radius',
      'border-top-right-radius',
      'border-bottom-right-radius',
      'border-bottom-left-radius',
    ],
    background: [
      'background-color',
      'background-image',
      'background-repeat',
      'background-attachment',
      'background-position',
      'background-size',
    ],
    font: [
      'font-style',
      'font-variant',
      'font-weight',
      'font-size',
      'line-height',
      'font-family',
    ],
    'list-style': [
      'list-style-type',
      'list-style-position',
      'list-style-image',
    ],
    animation: [
      'animation-name',
      'animation-duration',
      'animation-timing-function',
      'animation-delay',
      'animation-iteration-count',
      'animation-direction',
      'animation-fill-mode',
      'animation-play-state',
    ],
    transition: [
      'transition-property',
      'transition-duration',
      'transition-timing-function',
      'transition-delay',
    ],
    flex: ['flex-grow', 'flex-shrink', 'flex-basis'],
    grid: [
      'grid-template',
      'grid-template-rows',
      'grid-template-columns',
      'grid-template-areas',
      'grid-auto-rows',
      'grid-auto-columns',
      'grid-auto-flow',
      'grid-column-gap',
      'grid-row-gap',
    ],
    'place-content': ['align-content', 'justify-content'],
    'place-items': ['align-items', 'justify-items'],
    'place-self': ['align-self', 'justify-self'],
    overflow: ['overflow-x', 'overflow-y'],
    columns: ['column-width', 'column-count'],
    outline: ['outline-width', 'outline-style', 'outline-color'],
    inset: ['top', 'bottom', 'right', 'left'],
    'inset-block': ['inset-block-end', 'inset-block-start'],
    'inset-inline': ['inset-inline-end', 'inset-inline-start'],
    mask: [
      'mask-clip',
      'mask-composite',
      'mask-image',
      'mask-mode',
      'mask-origin',
      'mask-position',
      'mask-repeat',
      'mask-size',
    ],
  };

  /**
   *
   * @param {object} styleSheetData
   *   Data about a stylesheet, as passed to the `add_css` Ajax Command.
   * @param scopeSelector
   * @return {Promise<boolean>}
   */
  const scopeCss = async function (
    styleSheetData,
    scopeSelector,
    selectorsToSkip = [],
  ) {
    let css = '';

    // Keeps track of variable values declared in this stylesheet. This is for
    // instances where the variable is used within the same file, thus
    // window.getComputedStyle() has no effect.
    const variableValueCache = {};

    // If the asset was already added this way, there is no need to
    // do it again.
    if (
      document.querySelector(
        `[data-dialog-style-from="${styleSheetData.href}"]`,
      )
    ) {
      return;
    }

    let fetchHref = styleSheetData.href;

    // Check if the href is relative and needs the path processed.
    if (
      !fetchHref.startsWith(window.location.protocol) &&
      styleSheetData?.processPaths
    ) {
      // If it begins with a slash, remove so baseUrl can be used instead.
      if (fetchHref.startsWith('/')) {
        fetchHref = styleSheetData.href.substring(1);
      }
      // Make the URL absolute.
      fetchHref = `${window.location.origin}${drupalSettings.path.baseUrl}${fetchHref}`;
    }

    try {
      const res = await fetch(fetchHref);
      css = await res.text();
    } catch (err) {
      console.warn(`Could not fetch ${styleSheetData.href}`, err);
    }

    const styleElement = document.createElement('style');
    // This attribute keeps track of the CSS file the styles
    // originate from.
    styleElement.setAttribute('data-dialog-style-from', styleSheetData.href);

    // CSSStyleSheet has difficulty parsing shorthand styles that also
    // include CSS variables, so we populate those values in advance
    // when possible. We begin by parsing getting the AST of the CSS.
    const ast = csstree.parse(css);

    /**
     * Updates AST nodes of CSS variables with their values when available.
     *
     * @param {Object} node
     *   An AST node
     */
    const updateVariableNode = (node) => {
      const documentComputedStyles = window.getComputedStyle(
        document.documentElement,
      );

      // Keep track of any fallback values found in case a primary value does
      // not resolve. The var() call will be replaced with the value of the
      // fallback.
      let fallback = null;

      // If this is a variable with a default value, process the
      // default and replace any var() calls with values when they are
      // available.
      if (
        node?.children?.head?.next?.data?.type === 'Operator' &&
        node?.children?.head?.next?.data?.value === ',' &&
        node?.children?.head?.next?.next?.data &&
        node?.children?.head?.next?.next?.data.type === 'Raw'
      ) {
        // If the current node met the above condition, then the value at this
        // position is the value of the CSS variable fallback.
        const { value } = node.children.head.next.next.data;

        // The CSS variable fallback exists in the AST as a raw string that
        // might contain one or more CSS variables. Get every CSS variable in
        // this string.
        const matches = value.matchAll(
          /var\((\s)*(--[_a-zA-Z]+[_a-zA-Z0-9-]*)/gm,
        );
        const variables = [...matches].map((aMatch) => aMatch?.[2]);

        // Limit the array to only variables that can be resolved to values.
        const variablesWithValues = variables.filter((vr) =>
          documentComputedStyles.getPropertyValue(vr),
        );

        // Replace the call to var() with the value of the first variable that
        // can be resolved.
        if (variablesWithValues.length > 0) {
          fallback = documentComputedStyles.getPropertyValue(
            variablesWithValues[0],
          );
          if (fallback) {
            node.children.head.next.next.data.value = fallback;
          }
        }
      }

      // Get the CSS variable name and see if it can be resolved to a value.
      const varName = node?.children?.head?.data?.name;
      let cssVarValue =
        documentComputedStyles.getPropertyValue(varName) ||
        variableValueCache?.[varName];
      let depth = 0;

      // Account for variables that are referencing other variables.
      while (cssVarValue && cssVarValue.trim().includes('var(') && depth < 5) {
        const varTree = csstree.parse(cssVarValue, { context: 'value' });
        const valueNode = csstree.find(
          varTree,
          (node) => node.type === 'Identifier' && node.name.startsWith('--'),
        );
        if (valueNode?.name) {
          depth += 1;
          cssVarValue =
            documentComputedStyles.getPropertyValue(valueNode.name) ||
            variableValueCache?.[valueNode.name];
        } else {
          cssVarValue = false;
        }
      }

      if (cssVarValue || fallback) {
        // Convert the CSS variable value into AST.
        const valueAst = csstree.parse(cssVarValue || fallback, {
          context: 'value',
        });

        // If the value AST has a `next` property, it is a structure too
        // complex to handle the way we currently replace the AST node.
        // This is most commonly found with box shadow and gradient values.
        // @todo Find a way to replace the AST node that can handle this
        // kind of value.
        const hasNextPleaseSkip = valueAst?.children?.head?.next;

        // Replace the var() calling node with the actual value.
        if (valueAst?.children?.head?.data && !hasNextPleaseSkip) {
          // Replace individual properties so prototype properties such as
          // position in the AST tree are preserved.
          Object.entries(valueAst.children.head.data).forEach(
            ([key, value]) => {
              node[key] = value;
            },
          );
        }
      }
    };

    // Get the values of all variables declared in this file in case they are
    // needed within the same file (because same file means computed properties
    // are not yet available).
    csstree
      .findAll(
        ast,
        (node) =>
          node.type === 'Declaration' && node?.property.startsWith('--'),
      )
      .forEach((declarationNode) => {
        variableValueCache[declarationNode.property || '-'] = csstree.generate(
          declarationNode.value,
        );
      });

    // Traverse the AST tree and check for nodes that might need variables
    // replaces with actual values due to their use in a shorthand declaration
    // accompanied by one or more sibling longhand equivalents.
    let currentDeclaration = '';
    let hasConflictingLonghand = false;
    csstree.walk(ast, (node, item, list) => {
      if (node.type === 'Declaration') {
        currentDeclaration = node.property;
      }
      // If this is the first declaration in a set of them.
      if (node.type === 'Declaration' && item && !item.prev) {
        const declarations = [];
        // Find the names of all sibling declarations and add them to the
        // declarations array.
        list
          .filter((item) => item.type === 'Declaration')
          .forEach((item) => declarations.push(item.property));

        // Find any shorthand declarations present in this group.
        const shorthandDeclarations = declarations.filter((declaration) =>
          Object.keys(shorthands).includes(declaration),
        );

        // True if the group has any longhand declarations that are also
        // modifiable by one of the existing shorthand ones.
        hasConflictingLonghand = shorthandDeclarations.some(
          (shorthandDeclaration) =>
            declarations.some((declaration) =>
              shorthands[shorthandDeclaration].includes(declaration),
            ),
        );
      }
      // If this is a variable function and the current declaration is CSS
      // shorthand, and we have identified it as having sibling longhand
      // declarations that effect the same styles, we must replace the variable
      // call with the actual value.
      if (
        Object.keys(shorthands).includes(currentDeclaration) &&
        hasConflictingLonghand &&
        node.type === 'Function' &&
        node.name === 'var'
      ) {
        updateVariableNode(node);
      }
    });

    // Create a CSS string from the ast with processed variables.
    const newCss = csstree.generate(ast);

    // Create a CSSStyleSheet object that contains the styles
    // provided by the CSS file that was going to be added.
    const stylesheet = new CSSStyleSheet();
    await stylesheet.replace(newCss);

    /**
     * Get the string value of a CSS rule with potentially changed scope.
     *
     * @param {CSSRule} rule
     *   The CSS rule
     * @return {*|string}
     *   The CSS rule as a string.
     */
    const processRule = (rule) => {
      let { cssText } = rule;

      // If @scope is not supported it's best to use the default CSS despite it
      // introducing a risk of styles leaking. Without @scope, we run into
      // situations where the selector-fenced styles override styles that are
      // essential to functionality such as visibility state.
      if (typeof CSSScopeRule === 'undefined') {
        return cssText;
      }

      // Create an AST tree of the rule so we can identify various use cases.
      const ruleTree = csstree.parse(cssText);

      // If the CSS has relative URLs, make them absolute.
      const urls = csstree.findAll(
        ruleTree,
        (node, item, list) => node.type === 'Url' && node.value.startsWith('.'),
      );
      if (urls.length) {
        const pathParts = styleSheetData.href
          .split('/')
          .filter((part) => part !== '' && part !== '.');
        urls.forEach((url) => {
          if (url.value.startsWith('./')) {
            const urlNoPathPrefix = url.value.replace('./', '');
            const newUrl = `/${pathParts.slice(0, -1).join('/')}/${urlNoPathPrefix}`;
            cssText = cssText.replace(url.value, newUrl);
          }
          if (url.value.startsWith('../')) {
            const countUpDirs = (url.value.match(/\.\./g) || []).length;
            const urlNoPathPrefix = url.value.replaceAll('../', '');
            const newUrl = `/${pathParts.slice(0, -1 - countUpDirs).join('/')}/${urlNoPathPrefix}`;
            cssText = cssText.replace(url.value, newUrl);
          }
        });
      }

      // If the rule defines a CSS variable anywhere within, do not scope it.
      const declaresVars = csstree.findAll(
        ruleTree,
        (node) => node.type === 'Declaration' && node.property.startsWith('--'),
      );
      if (declaresVars.length) {
        return cssText;
      }

      // If the rule begins with a media query, do not scope it.
      const atRules = csstree.findAll(
        ruleTree,
        (node) => node.type === 'Atrule',
      );
      if (atRules.length) {
        return cssText;
      }

      // The topLevelSelectors accounts for selectors that are supposed
      // to appear before the scope selector, such as html and body tags
      // or the .js class.
      const topLevelSelectors = ['html', 'body', 'main'];
      topLevelSelectors.forEach((tagName) => {
        document.querySelector(tagName)?.classList.forEach((aClass) => {
          topLevelSelectors.push(`.${aClass}`);
        });
      });

      // If a rule is scoped to root, return the unaltered string.
      if (rule.cssText.includes(':root')) {
        return cssText;
      }

      // If the rule begins with the scopeSelector or the default dialog class,
      // it is effectively wrapped already, return the unaltered string.
      if (
        [...selectorsToSkip, scopeSelector].some((selector) =>
          rule.cssText.trim().startsWith(selector),
        )
      ) {
        return cssText;
      }

      // If the rule begins with a higher level selector that needs
      // to precede the scope selector, return the rule as a string with
      // the scope selector positioned after the broader selector.
      const beginsWithTopLevel = topLevelSelectors.filter((possibleSelector) =>
        rule.cssText.startsWith(possibleSelector),
      );
      if (beginsWithTopLevel.length) {
        const selector = beginsWithTopLevel[0].match(/[^\s]+/);
        return (
          // eslint-disable-next-line prefer-template
          cssText.replace(selector, `@scope(${selector}) { ${scopeSelector}`) +
          ' } '
        );
      }

      // Otherwise, return the rule as string scoped within `scopeSelector`.
      return `@scope(${scopeSelector}) { ${cssText} }`;
    };

    // Make the dialog-scoped CSS the contents of the style element.
    styleElement.innerHTML = [...stylesheet.cssRules].reduce(
      // eslint-disable-next-line prefer-template
      (accumulated, rule) => accumulated + processRule(rule) + ' ',
      '',
    );
    const priorAdditions = document.querySelectorAll(
      '[data-dialog-style-from]',
    );

    // If this is the first style element added by this method, add it
    // to the beginning of `<head>`.
    if (priorAdditions.length === 0) {
      document.querySelector('head').prepend(styleElement);
    } else {
      // Place any new CSS asset directly after the most recent asset
      // added via this process so load order is maintained, but they
      // still appear before pre-existing CSS so utility classes will
      // get prioritized in situations of otherwise identical specificity.
      const mostRecentAddition = [...priorAdditions].pop();
      mostRecentAddition.insertAdjacentElement('afterend', styleElement);
    }
    return true;
  };

  /**
   * Customizing the add_css AjaxCommand for Drupal Canvas.
   *
   * @type {{attach(): void}}
   */
  Drupal.behaviors.enhanceAddCssForDialogsUsingAdminTheme = {
    attach() {
      // Copy the original add_css method so it can be called from the overridden
      // version added below.
      const originalAddCss = Drupal.AjaxCommands.prototype.add_css;

      Drupal.AjaxCommands.prototype.scope_css = scopeCss;

      // Overrides the existing add_css to facilitate scoping certain styles
      // within specific selectors.
      Drupal.AjaxCommands.prototype.add_css = function (...args) {
        const [ajax, response] = args;

        // If this is in an AJAX dialog and the dialog trigger specified
        // useAdminTheme, add the CSS assets differently.
        if (
          (ajax?.dialogType === 'ajax' && ajax?.useAdminTheme) ||
          ajax?.dialog?.useAdminTheme
        ) {
          // The scope selector is what wraps the styles so they are only
          // applied within the dialog. `.ui-dialog` is the default class.
          // @see \Drupal\Core\Ajax\OpenDialogCommand::$dialogOptions
          const scopeSelector = ajax?.scopeSelector || '.ui-dialog';
          const selectorsToSkip = ajax?.selectorsToSkip
            ? JSON.parse(ajax.selectorsToSkip)
            : [];

          return new Promise((resolve) => {
            // Although it's typically discouraged to use await within loops, it
            // is done here to ensure every stylesheet in the list is fully
            // added to the DOM before the process begins for the next one in
            // the array. By using Promise.all(), we run into scenarios where
            // the process looks for CSS variables that are not yet available.
            // Having the CSS variables already loaded is necessary due to
            // limitations of CSSStyleSheet() not being able to parse styles
            // that use CSS variables in shorthand in a style that also includes
            // a longhand property of that shorthand. The workaround is
            // populating those values via JavaScript.
            response.data.reduce(async (promise, styleSheetData, index) => {
              // Wait for the prior call to scopeCss to complete so the loading
              // order is preserved;
              await promise;
              await scopeCss(styleSheetData, scopeSelector, selectorsToSkip);

              // When the last item is completed, resolve the promise so the
              // AJAX dialog opens with the scoped CSS already present.
              if (response.data.length === index + 1) {
                resolve();
              }
            }, Promise.resolve());
          });
        }

        // If the CSS assets were not designated to be scoped within an admin
        // theme rendered dialog, use default `add_css` from ajax.js.
        return originalAddCss.apply(this, args);
      };

      /**
       * Override the update_build_id command to trigger a `ajaxUpdateFormBuildId` event.
       *
       * The event properties are:
       * - `formId`: the ID of the form whose build ID is being updated
       * - `oldFormBuildId`: the previous build ID
       * - `newFormBuildId`: the new build ID
       */
      const originalUpdateBuildId =
        Drupal.AjaxCommands.prototype.update_build_id;

      /**
       * A map to keep track of the association between form build ID and the
       * form ID.
       *
       * @type {{}}
       */
      const formBuildIdMap = {};

      const getFormId = (oldId) => {
        const oldInput = document.querySelector(
          `input[name="form_build_id"][value="${oldId}"]`,
        );
        if (oldInput) {
          // We found an existing input with the old form ID. We can retrieve
          // the formId from the data-form-id attribute.
          return oldInput.dataset.formId;
        }
        // We may have encountered this form build ID before and have a record
        // of the form it relates to saved.
        if (oldId in formBuildIdMap) {
          return formBuildIdMap[oldId];
        }
        return null;
      };

      Drupal.AjaxCommands.prototype.update_build_id = function (...args) {
        const [, response] = args;
        const formId = getFormId(response.old);
        if (!formId) {
          return;
        }
        // Keep a record of the association between these form build IDs and the
        // form ID they relate to.
        formBuildIdMap[response.old] = formId;
        formBuildIdMap[response.new] = formId;
        originalUpdateBuildId.apply(this, args);
        // Notify the application that the form build ID has changed.
        const event = new CustomEvent('ajaxUpdateFormBuildId', {
          detail: {
            oldFormBuildId: response.old,
            newFormBuildId: response.new,
            formId,
          },
        });
        document.dispatchEvent(event);
      };
    },
  };
})(Drupal, csstree, drupalSettings);
