(function (Drupal, once, $) {
  Drupal.behaviors.testValueUpdates = {
    attach() {
      // Attach event listeners to buttons that will update the values of
      // various React rendered form elements to demonstrate the value changes
      // are propagated to Redux and/or work with the State API.
      once('test-value-updates', '#trigger-text-update').forEach((el) => {
        el.addEventListener('click', (e) => {
          e.preventDefault();
          // Update a text prop.
          document.querySelector(
            '[data-form-id="component_instance_form"][type="text"]',
          ).value = 'SURPRISE!';
          // Update the controlling text input, use jQuery val() so we have an
          // instance of that method working in our tests.
          // eslint-disable-next-line no-jquery/no-val
          $('#edit-controlling-text').val('make visible invisible');
        });
      });
      once('test-value-updates', '#trigger-select-update').forEach((el) => {
        el.addEventListener('click', (e) => {
          e.preventDefault();
          // Update an enum prop value.
          document.querySelector(
            'select[data-form-id="component_instance_form"]',
          ).value = 'baz';
        });
      });
      once('test-value-updates', '#trigger-number-update').forEach((el) => {
        el.addEventListener('click', (e) => {
          e.preventDefault();
          // Update a number prop value.
          document.querySelector(
            '[data-form-id="component_instance_form"][type="number"]',
          ).value = 2000;
        });
      });
      once('test-value-updates', '#trigger-toggle-update').forEach((el) => {
        el.addEventListener('click', (e) => {
          e.preventDefault();
          // Toggle a boolean prop value.
          const checkbox = document.querySelector(
            '[name^="canvas_component_props"][type="checkbox"]',
          );
          checkbox.checked = !checkbox.checked;
        });
      });
      once('test-value-updates', '#trigger-checkbox-update').forEach((el) => {
        el.addEventListener('click', (e) => {
          e.preventDefault();
          // Toggle a checkbox value.
          const checkbox = document.querySelector('#edit-show-extra-field');
          checkbox.checked = !checkbox.checked;
        });
      });
    },
  };
})(Drupal, once, jQuery);
