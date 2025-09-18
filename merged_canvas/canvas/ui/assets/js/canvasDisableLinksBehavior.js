/**
 * Drupal behavior for disabling all the links inside the preview in Canvas
 * @type {Drupal~behavior}
 */
(
  function (Drupal, once) {
    function interceptLink(url) {
      // Send a post-message to the parent window with the URL of the clicked link.
      window.parent.postMessage({ canvasPreviewClickedUrl: url }, '*');
    }

    /**
     * Stop link clicks from navigating the user away from the preview.
     * @param event
     */
    function handleClick(event) {
      const element = event.target.closest('a[href]');
      if (element && !element.target?.includes('_blank')) {
        event.preventDefault();
        interceptLink(element.href);
      }
    }

    /**
     * Stop links from navigating the user away from the preview when Enter is pressed.
     * @param event
     */
    function handleKeydown(event) {
      const element = event.target.closest('a[href]');
      if (element && event.key === 'Enter') {
        event.preventDefault();
        interceptLink(element.href);
      }
    }

    /**
     * Stop form submissions from navigating the user away from the preview.
     * @param event
     */
    function handleSubmit(event) {
      const element = event.target.closest('form');
      if (element) {
        // Prevent form submission and show a popup.
        event.preventDefault();
        window.parent.postMessage(
          {
            canvasPreviewFormSubmitted:
              'Form submission is not supported in the preview.',
          },
          '*',
        );
      }
    }

    /**
     * Disable the right click context menu to prevent using it for navigation in the preview iframe.
     * @param event
     */
    function handleContextmenu(event) {
      event.preventDefault();
    }

    Drupal.behaviors.canvasDisableLinks = {
      attach(context) {
        // Binding these events to the body means they will handle dynamically/asynchronously added elements.

        once('canvasDisableLinksClick', context.body).forEach((el) => {
          el.addEventListener('click', handleClick);
        });
        once('canvasDisableLinksKeydown', context.body).forEach((el) => {
          el.addEventListener('keydown', handleKeydown);
        });
        once('canvasDisableLinksSubmit', context.body).forEach((el) => {
          el.addEventListener('submit', handleSubmit);
        });
        once('canvasDisableLinksContextmenu', context.body).forEach((el) => {
          el.addEventListener('contextmenu', handleContextmenu);
        });
      },
      detach(context) {
        context.body.removeEventListener('click', handleClick);
        context.body.removeEventListener('keydown', handleKeydown);
        context.body.removeEventListener('submit', handleSubmit);
        context.body.removeEventListener('contextmenu', handleContextmenu);
      },
    };
  }
  // eslint-disable-next-line no-undef
)(Drupal, once);
