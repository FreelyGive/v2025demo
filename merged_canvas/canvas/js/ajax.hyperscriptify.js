(function (Drupal) {
  /**
   * This is for hyperscriptifying elements added to the DOM via Drupal AJAX.
   *
   * @see /ui/src/local_packages/hyperscriptify/
   *
   * @type {{attach(*, *): void}}
   */
  Drupal.behaviors.jsxAjaxProcess = {
    attach(context, settings) {
      // After hyperscriptifying a context, we send it through Drupal
      // behaviors. The doNotReinvoke flag indicates already-scriptified
      // content that does not need to proceed further.
      if (settings.doNotReinvoke) {
        return;
      }
      // If no templates are mapped to components, there's no need to continue.
      if (!Drupal.JSXComponents) {
        return;
      }

      context.querySelectorAll('drupal-html-fragment').forEach((fragment) => {
        setTimeout(() => {
          // Clean out Drupal HTML fragments after they've been scriptified so there
          // aren't matching selectors for elements that have already served
          // their purpose in guiding the scriptification process.
          if (fragment.hasAttribute('data-drupal-scriptified')) {
            fragment.innerHTML = '';
          }
        });
      });

      const componentNames = Object.keys(Drupal.JSXComponents);
      const allJSXComponentInstances = [
        ...context.querySelectorAll(componentNames.join()),
      ];

      // Hyperscriptify is applied to children of the components it processes,
      // so we only need to identify JSX components that are not children of
      // other JSX components.
      let topLevelComponents = allJSXComponentInstances.filter((el) => {
        return !allJSXComponentInstances.some(
          (parent) => parent !== el && parent.contains(el),
        );
      });

      // Special functionality in situations where tabledrag settings exist and
      // the context is an AJAX wrapper.
      // This means we should potentially be hyperscriptifying the entire
      // tabledrag table, instead individually targeting the custom elements
      // inside it.
      if (
        drupalSettings.tableDrag &&
        context?.querySelector &&
        !!context.querySelector(':scope > [data-canvas-multiple-values]') &&
        !!context.querySelector('[data-canvas-tabledrag]')
      ) {
        context
          .querySelectorAll('.ajax-new-content')
          .forEach((ajaxNewContent) => {
            // If .ajax-new-content is still present, the element was sent to
            // be hyperscriptified before its show operation had completed, which
            // can cause the hyperscriptification to occur before the opacity has
            // fully reverted to the default value. This effectively freezes the
            // opacity value wherever it happened to be during this operation,
            // so we instead set it to null.
            ajaxNewContent.style.opacity = null;
          });
        const commonParents = [];
        // This identifies JSX elements that are children of a tabledrag table
        // and removes them from the array of elements to hyperscriptify.
        // At the same time, the parent element of the tabledrag table is added
        // to the array, so every removed JSX component is still
        // hyperscriptified as a descendent of the tabledrag table.
        topLevelComponents = topLevelComponents.filter((el) => {
          let dragParent = false;
          // See if any element descends from a tabledrag table.
          Object.keys(drupalSettings.tableDrag).forEach((dragId) => {
            if (!dragParent) {
              dragParent = el.closest(`#${dragId}`);
            }
          });
          // If the element does descend from a tabledrag table, remove it from
          // the array of elements to hyperscriptify and add the table to an
          // array of parent elements that should be hyperscriptified instead.
          if (dragParent) {
            if (
              !commonParents.find((subEl) => subEl === dragParent.parentElement)
            ) {
              commonParents.push(dragParent.parentElement);
            }
            return false;
          }
          return true;
        });
        topLevelComponents = [...commonParents, ...topLevelComponents];
      }

      // Component instances with attributes mean they are likely a form field,
      // so add it to the list of updated input elements so the store can be
      // updated accordingly after any component rendering occurs.
      const updatedInputElements = allJSXComponentInstances.filter((el) =>
        el.hasAttribute('attributes') && !el.hasAttribute('data-drupal-scriptified'),
      );

      // Keeps track of if behaviors have been attached.
      let attachBehaviorsCalled = false;

      // If the top-level element in context is a JSX component
      if (
        context.tagName &&
        componentNames.includes(context.tagName.toLowerCase())
      ) {
        if (
          !context.tagName.toLowerCase().includes('fragment') &&
          !context.hasAttribute('data-drupal-scriptified')
        ) {
          Drupal.HyperscriptifyAdditional(
            Drupal.Hyperscriptify(context),
            context,
            settings,
          );
          attachBehaviorsCalled = true;
        }
      } else {
        topLevelComponents.forEach((component) => {
          if (!component.hasAttribute('data-drupal-scriptified')) {
            Drupal.HyperscriptifyAdditional(
              Drupal.Hyperscriptify(component),
              component,
              settings,
            );
            attachBehaviorsCalled = true;
          }
        });
      }

      if (updatedInputElements.length > 0) {
        // Notify the application that form fields have changed.
        Drupal.HyperscriptifyUpdateStore(updatedInputElements);
      }

      // If Drupal.attachBehaviors has not yet been called, but the context is inside
      // the contextual panel, it will be called here.
      if (
        !attachBehaviorsCalled &&
        context?.closest &&
        context.closest('[data-testid="canvas-contextual-panel"]')
      ) {
        Drupal.attachBehaviorsAfterAjaxing(context, settings);
      }
    },
  };
})(Drupal);
