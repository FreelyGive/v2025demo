(function (Drupal, once, $) {
  Drupal.behaviors.testCanvasDialog = {
    attach(context) {
      once('test-dialog-style', '[test-drupal-dialog]', context).forEach(
        (el) => {
          $(el).on('click', (e) => {
            e.preventDefault();
            const testDialog = Drupal.dialog(`<div>JS Made This Dialog</div>`, {
              title: 'Dialog made by a call to Drupal.dialog()',
              resizable: false,
              buttons: [
                {
                  text: 'First Button',
                  class: 'button button--primary',
                  click() {
                    testDialog.close();
                  },
                },
                {
                  text: 'Additional Button',
                  class: 'button',
                  click() {
                    testDialog.close();
                  },
                },
              ],
            });

            testDialog.showModal();
          });
        },
      );
    },
  };
})(Drupal, once, jQuery);
