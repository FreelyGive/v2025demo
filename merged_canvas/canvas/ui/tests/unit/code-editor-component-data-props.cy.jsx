import { Provider } from 'react-redux';
import { Theme } from '@radix-ui/themes';

import { makeStore } from '@/app/store';
import {
  addProp,
  selectCodeComponentProperty,
  toggleRequired,
  updateProp,
} from '@/features/code-editor/codeEditorSlice';
import ComponentData from '@/features/code-editor/component-data/ComponentData';
import { parseExampleSrc as parseImageExampleSrc } from '@/features/code-editor/component-data/forms/FormPropTypeImage';
import { getPropMachineName } from '@/features/code-editor/utils';

import '@/styles/radix-themes';
import '@/styles/index.css';

describe('Component data / props in code editor', () => {
  let store;

  beforeEach(() => {
    cy.viewport(500, 800);
    store = makeStore({});
    cy.mount(
      <Provider store={store}>
        <Theme
          accentColor="blue"
          hasBackground={false}
          panelBackground="solid"
          appearance="light"
        >
          <ComponentData />
        </Theme>
      </Provider>,
    );
  });

  it('creates, reorders, and removes props', () => {
    // Add a new prop.
    cy.findByText('Add').click();

    cy.findByLabelText('Prop name').should('exist');
    cy.findByLabelText('Type').should('exist');
    cy.findByLabelText('Required').should('exist');

    cy.log('Checking first prop in store with default values');
    cy.wrap(store).then((store) => {
      const props = selectCodeComponentProperty('props')(store.getState());
      expect(props).to.have.length(
        1,
        'Should have exactly one prop after clicking new prop button',
      );
      expect(props[0]).to.deep.include(
        {
          name: '',
          type: 'string',
          example: '',
          derivedType: 'text',
          format: undefined,
          $ref: undefined,
        },
        'Should have the correct default prop values',
      );
    });

    cy.findByLabelText('Prop name').type('Title');
    cy.findByLabelText('Example value').type('Your title goes here');

    cy.log('Checking updated prop');
    cy.wrap(store).then((store) => {
      const props = selectCodeComponentProperty('props')(store.getState());
      expect(props).to.have.length(1, 'Should have exactly one prop');
      expect(props[0]).to.deep.include(
        {
          name: 'Title',
          example: 'Your title goes here',
          derivedType: 'text',
          format: undefined,
          $ref: undefined,
        },
        'Should have the updated name and example value',
      );
    });

    cy.log('Adding more props');

    // Add a test list prop with three values.
    cy.findByText('Add').click();
    cy.findAllByLabelText('Prop name').last().type('Variant');
    cy.findAllByLabelText('Type').last().click();
    cy.findByText('List: text').click();
    cy.findByText('Add value').click();
    cy.findAllByTestId(/canvas-prop-enum-value-[0-9a-f-]+-\d/)
      .last()
      .type('Alpha');
    cy.findByText('Add value').click();
    cy.findAllByTestId(/canvas-prop-enum-value-[0-9a-f-]+-\d/)
      .last()
      .type('Bravo');
    cy.findByText('Add value').click();
    cy.findAllByTestId(/canvas-prop-enum-value-[0-9a-f-]+-\d/)
      .last()
      .type('Charlie');
    cy.findByLabelText('Default value').click();
    cy.findByText('Bravo').click();

    // Add a boolean prop.
    cy.findByText('Add').click();
    cy.findAllByLabelText('Prop name').last().type('Featured');
    cy.findAllByLabelText('Type').last().click();
    cy.findByText('Boolean').click();
    cy.findAllByLabelText('Example value').last().assertToggleState(false);
    cy.findAllByLabelText('Example value').last().toggleToggle();
    cy.findAllByLabelText('Example value').last().assertToggleState(true);

    // Check that the props are in the store.
    cy.wrap(store).then((store) => {
      const props = selectCodeComponentProperty('props')(store.getState());
      expect(props).to.have.length(3, 'Should have exactly three props');
      expect(props[0]).to.deep.include({
        name: 'Title',
        type: 'string',
        example: 'Your title goes here',
        format: undefined,
        $ref: undefined,
        derivedType: 'text',
      });
      expect(props[1]).to.deep.include({
        name: 'Variant',
        type: 'string',
        enum: ['Alpha', 'Bravo', 'Charlie'],
        example: 'Bravo',
        format: undefined,
        $ref: undefined,
        derivedType: 'listText',
      });
      expect(props[2]).to.deep.include({
        name: 'Featured',
        type: 'boolean',
        example: true,
        format: undefined,
        $ref: undefined,
        derivedType: 'boolean',
      });
    });

    // Reorder the props. Move the first prop to the third position.
    cy.findAllByLabelText('Move prop')
      .first()
      .realDnd('[data-testid="prop-2"]');
    // Check that the props in the store are in the new order.
    cy.wrap(store).then((store) => {
      const props = selectCodeComponentProperty('props')(store.getState());
      expect(props[0].name).to.equal('Variant');
      expect(props[1].name).to.equal('Featured');
      expect(props[2].name).to.equal('Title');
    });

    // Reorder the props again. Move the first prop to the second position.
    cy.findAllByLabelText('Move prop')
      .first()
      .realDnd('[data-testid="prop-1"]', {
        position: 'top',
      });
    // Check that the props in the store are in the new order.
    cy.wrap(store).then((store) => {
      const props = selectCodeComponentProperty('props')(store.getState());
      expect(props[0].name).to.equal('Featured');
      expect(props[1].name).to.equal('Variant');
      expect(props[2].name).to.equal('Title');
    });

    // Remove the first prop.
    cy.findAllByLabelText('Remove prop').first().click();
    // Check that the props in the store are in the new order.
    cy.wrap(store).then((store) => {
      const props = selectCodeComponentProperty('props')(store.getState());
      expect(props).to.have.length(2, 'Should have exactly two props');
      expect(props[0].name).to.equal('Variant');
      expect(props[1].name).to.equal('Title');
    });

    // Remove the last prop.
    cy.findAllByLabelText('Remove prop').last().click();
    // Check that the props in the store are in the new order.
    cy.wrap(store).then((store) => {
      const props = selectCodeComponentProperty('props')(store.getState());
      expect(props).to.have.length(1, 'Should have exactly one prop');
      expect(props[0].name).to.equal('Variant');
    });

    // Remove the one remaining prop.
    cy.findByLabelText('Remove prop').click();
    cy.wrap(store).then((store) => {
      const props = selectCodeComponentProperty('props')(store.getState());
      expect(props).to.have.length(0, 'Should have no props');
    });
  });

  it('adds and removes prop from required props', () => {
    // Add a new prop and toggle it as required.
    cy.findByText('Add').click();
    cy.findByLabelText('Required').toggleToggle();

    // Check that the prop is now required.
    cy.log('Checking updated prop');
    cy.wrap(store).then((store) => {
      const propName = selectCodeComponentProperty('props')(store.getState())[0]
        .name;
      const required = selectCodeComponentProperty('required')(
        store.getState(),
      );
      expect(required[0]).to.equal(
        getPropMachineName(propName),
        'Should have the prop as required',
      );
    });

    // Toggle the prop as not required.
    cy.findByLabelText('Required').toggleToggle();

    // Check that the prop is no longer required.
    cy.wrap(store).then((store) => {
      const required = selectCodeComponentProperty('required')(
        store.getState(),
      );
      expect(required).to.have.length(0, 'Should have no required props');
    });

    // Toggle the prop as required again, then delete it.
    cy.findByLabelText('Required').toggleToggle();
    cy.findByLabelText('Remove prop').click();

    // Check that the prop is no longer required.
    cy.wrap(store).then((store) => {
      const required = selectCodeComponentProperty('required')(
        store.getState(),
      );
      expect(required).to.have.length(0, 'Should have no required props');
    });
  });

  it('displays an existing prop', () => {
    // Add a new prop directly to the store, update it, and toggle it as required.
    cy.wrap(store).then((store) => {
      store.dispatch(addProp());
      const newProp = selectCodeComponentProperty('props')(store.getState())[0];
      cy.log(
        `Added new prop directly to the store: ${JSON.stringify(newProp)}`,
      );
      store.dispatch(
        updateProp({
          id: newProp.id,
          updates: { name: 'Title', example: 'Your title goes here' },
        }),
      );
      const updatedProp = selectCodeComponentProperty('props')(
        store.getState(),
      )[0];
      cy.log(
        `Updated prop directly in the store: ${JSON.stringify(updatedProp)}`,
      );
      store.dispatch(toggleRequired({ propId: updatedProp.id }));
      cy.log(
        `Toggled required prop in the store: ${JSON.stringify(updatedProp)}`,
      );
    });

    // Check that the prop is displayed in the component.
    cy.findByLabelText('Prop name').should('have.value', 'Title');
    cy.findByLabelText('Type').should('have.text', 'Text');
    cy.findByLabelText('Required').assertToggleState(true);
    cy.findByLabelText('Example value').should(
      'have.value',
      'Your title goes here',
    );
  });

  it('creates a new text prop', () => {
    cy.findByText('Add').click();

    cy.findByLabelText('Prop name').type('Title');
    cy.findByLabelText('Example value').should(
      'have.attr',
      'placeholder',
      'Enter a text value',
    );
    cy.findByLabelText('Example value').type('Your title goes here');
    cy.findByLabelText('Example value').should(
      'have.value',
      'Your title goes here',
    );

    cy.wrap(store).then((store) => {
      const prop = selectCodeComponentProperty('props')(store.getState())[0];
      expect(prop).to.deep.include(
        {
          name: 'Title',
          type: 'string',
          example: 'Your title goes here',
          format: undefined,
          $ref: undefined,
        },
        'Should have the correct prop metadata',
      );
    });
  });

  it('creates a new integer prop', () => {
    cy.findByText('Add').click();

    cy.findByLabelText('Prop name').type('Count');
    cy.findByLabelText('Type').click();
    cy.findByText('Integer').click();
    cy.findByLabelText('Example value');
    cy.findByLabelText('Example value').should(
      'have.attr',
      'placeholder',
      'Enter an integer',
    );
    cy.findByLabelText('Example value').type(
      'Typing an invalid string value with hopefully no effect',
    );
    cy.findByLabelText('Example value').should('have.value', '');
    cy.findByLabelText('Example value').type('922');
    cy.findByLabelText('Example value').should('have.value', '922');

    cy.wrap(store).then((store) => {
      const prop = selectCodeComponentProperty('props')(store.getState())[0];
      expect(prop).to.deep.include(
        {
          name: 'Count',
          type: 'integer',
          example: '922',
          format: undefined,
          $ref: undefined,
        },
        'Should have the correct prop metadata',
      );
    });
  });

  it('creates a new number prop', () => {
    cy.findByText('Add').click();

    cy.findByLabelText('Prop name').type('Percentage');
    cy.findByLabelText('Type').click();
    cy.findByText('Number').click();
    cy.findByLabelText('Example value').should(
      'have.attr',
      'placeholder',
      'Enter a number',
    );
    cy.findByLabelText('Example value').type(
      'Typing an invalid string value with hopefully no effect',
    );
    cy.findByLabelText('Example value').should('have.value', '');
    cy.findByLabelText('Example value').type('9.22');
    cy.findByLabelText('Example value').should('have.value', '9.22');

    cy.wrap(store).then((store) => {
      const prop = selectCodeComponentProperty('props')(store.getState())[0];
      expect(prop).to.deep.include(
        {
          name: 'Percentage',
          type: 'number',
          example: '9.22',
          format: undefined,
          $ref: undefined,
        },
        'Should have the correct prop metadata',
      );
    });
  });

  it('creates a new boolean prop', () => {
    cy.findByText('Add').click();

    cy.findByLabelText('Prop name').type('Is featured');
    cy.findByLabelText('Type').click();
    cy.findByText('Boolean').click();
    cy.findByLabelText('Example value').assertToggleState(false);

    cy.wrap(store).then((store) => {
      const prop = selectCodeComponentProperty('props')(store.getState())[0];
      expect(prop).to.deep.include(
        {
          name: 'Is featured',
          type: 'boolean',
          example: false,
          format: undefined,
          $ref: undefined,
        },
        'Should have the correct prop metadata',
      );
    });

    cy.findByLabelText('Example value').toggleToggle();
    cy.findByLabelText('Example value').assertToggleState(true);

    cy.wrap(store).then((store) => {
      const prop = selectCodeComponentProperty('props')(store.getState())[0];
      expect(prop).to.deep.include(
        {
          name: 'Is featured',
          type: 'boolean',
          example: true,
        },
        'Should have the correct prop metadata',
      );
    });
  });

  it('creates a new text list prop', () => {
    cy.findByText('Add').click();

    cy.wrap(store).then((store) => {
      const propId = selectCodeComponentProperty('props')(store.getState())[0]
        .id;
      cy.wrap(propId).as('propId');
    });

    cy.findByLabelText('Prop name').type('Tags');
    cy.findByLabelText('Type').click();
    cy.findByText('List: text').click();
    cy.findByLabelText('Default value').should('not.exist');

    cy.wrap(store).then((store) => {
      expect(
        selectCodeComponentProperty('props')(store.getState())[0],
      ).to.deep.include(
        {
          name: 'Tags',
          type: 'string',
          enum: [],
          format: undefined,
          $ref: undefined,
        },
        'Should have the correct prop metadata',
      );
    });

    cy.get('@propId').then((propId) => {
      // Add a new value, make sure "Default value" is not shown yet while the
      // new value is empty.
      cy.findByText('Add value').click();
      cy.findByLabelText('Default value').should('not.exist');

      // Type a value, make sure "Default value" is shown.
      cy.findByTestId(`canvas-prop-enum-value-${propId}-0`).type('Alpha');
      cy.findByLabelText('Default value').should('exist');
      cy.wrap(store).then((store) => {
        expect(
          selectCodeComponentProperty('props')(store.getState())[0].enum,
        ).to.deep.equal(['Alpha'], 'Should have the appropriate enum values');
      });

      // Clear the value, make sure "Default value" is not shown.
      cy.findByTestId(`canvas-prop-enum-value-${propId}-0`).clear();
      cy.findByLabelText('Default value').should('not.exist');
      cy.wrap(store).then((store) => {
        expect(
          selectCodeComponentProperty('props')(store.getState())[0].enum,
        ).to.deep.equal([''], 'Should have the following enum values: ""');
      });

      // Type a value, then add two more values.
      cy.findByTestId(`canvas-prop-enum-value-${propId}-0`).type('Alpha');
      cy.findByText('Add value').click();
      cy.findByTestId(`canvas-prop-enum-value-${propId}-1`).type('Bravo');
      cy.findByText('Add value').click();
      cy.findByTestId(`canvas-prop-enum-value-${propId}-2`).type('Charlie');
      cy.wrap(store).then((store) => {
        expect(
          selectCodeComponentProperty('props')(store.getState())[0].enum,
        ).to.deep.equal(
          ['Alpha', 'Bravo', 'Charlie'],
          'Should have the appropriate enum values',
        );
      });

      // Set the prop as required. "Default value" now should have the first
      // value selected.
      cy.findByLabelText('Required').toggleToggle();
      cy.findByLabelText('Default value').should('have.text', 'Alpha');
      cy.wrap(store).then((store) => {
        expect(
          selectCodeComponentProperty('props')(store.getState())[0],
        ).to.deep.include(
          {
            enum: ['Alpha', 'Bravo', 'Charlie'],
            example: 'Alpha',
          },
          'Should have the appropriate enum and example values',
        );
      });

      // Clear the first value that is also currently the selected default value.
      cy.findByTestId(`canvas-prop-enum-value-${propId}-0`).clear();
      // Verify that the default value is now the second value, and that the
      // dropdown has the remaining values.
      cy.findByLabelText('Default value').should('have.text', 'Bravo');
      cy.wrap(store).then((store) => {
        expect(
          selectCodeComponentProperty('props')(store.getState())[0],
        ).to.deep.include(
          {
            enum: ['', 'Bravo', 'Charlie'],
            example: 'Bravo',
          },
          'Should have the appropriate enum and example values',
        );
      });
      cy.findByLabelText('Default value').click();
      cy.findByRole('listbox').within(() => {
        cy.findByRole('option', { name: 'Bravo' }).should('exist');
        cy.findByRole('option', { name: 'Charlie' }).should('exist');
      });
      // Select the third value as default while the dropdown is open.
      cy.findByText('Charlie').click();
      cy.wrap(store).then((store) => {
        expect(
          selectCodeComponentProperty('props')(store.getState())[0],
        ).to.deep.include(
          {
            enum: ['', 'Bravo', 'Charlie'],
            example: 'Charlie',
          },
          'Should have the appropriate enum and example values',
        );
      });

      // Modify the third value.
      cy.findByTestId(`canvas-prop-enum-value-${propId}-2`).type('Zulu');
      // The default value should change to the first valid value (currently the
      // second) after the previous default value was modified.
      cy.findByLabelText('Default value').should('have.text', 'Bravo');
      cy.wrap(store).then((store) => {
        expect(
          selectCodeComponentProperty('props')(store.getState())[0],
        ).to.deep.include(
          {
            enum: ['', 'Bravo', 'CharlieZulu'],
            example: 'Bravo',
          },
          'Should have the appropriate enum and example values',
        );
      });

      // Modify the second value — currently default.
      cy.findByTestId(`canvas-prop-enum-value-${propId}-1`).type('Yankee');
      // The modified version should become the new default value, because it
      // happens to be the first valid value.
      cy.findByLabelText('Default value').should('have.text', 'BravoYankee');
      cy.wrap(store).then((store) => {
        expect(
          selectCodeComponentProperty('props')(store.getState())[0],
        ).to.deep.include(
          {
            enum: ['', 'BravoYankee', 'CharlieZulu'],
            example: 'BravoYankee',
          },
          'Should have the appropriate enum and example values',
        );
      });

      // Delete the first value. The previously second value should become the
      // first. Similarly, the previously third value should now be the second.
      cy.findByTestId(`canvas-prop-enum-value-delete-${propId}-0`).click();
      cy.findByTestId(`canvas-prop-enum-value-${propId}-0`).should(
        'have.value',
        'BravoYankee',
      );
      cy.findByTestId(`canvas-prop-enum-value-${propId}-1`).should(
        'have.value',
        'CharlieZulu',
      );
      cy.wrap(store).then((store) => {
        expect(
          selectCodeComponentProperty('props')(store.getState())[0],
        ).to.deep.include(
          {
            enum: ['BravoYankee', 'CharlieZulu'],
            example: 'BravoYankee',
          },
          'Should have the appropriate enum and example values',
        );
      });

      // Delete the first value. It was previously used as the default value,
      // but is now deleted, so make sure the default value is updated to the
      // new first valid value.
      cy.findByTestId(`canvas-prop-enum-value-delete-${propId}-0`).click();
      cy.findByLabelText('Default value').should('have.text', 'CharlieZulu');
      cy.wrap(store).then((store) => {
        expect(
          selectCodeComponentProperty('props')(store.getState())[0],
        ).to.deep.include(
          {
            enum: ['CharlieZulu'],
            example: 'CharlieZulu',
          },
          'Should have the appropriate enum and example values',
        );
      });

      // Modify the first value. Make sure the default value follows it.
      cy.findByTestId(`canvas-prop-enum-value-${propId}-0`).type('XRay');
      cy.findByLabelText('Default value').should(
        'have.text',
        'CharlieZuluXRay',
      );
      cy.wrap(store).then((store) => {
        expect(
          selectCodeComponentProperty('props')(store.getState())[0],
        ).to.deep.include(
          {
            enum: ['CharlieZuluXRay'],
            example: 'CharlieZuluXRay',
          },
          'Should have the appropriate enum and example values',
        );
      });

      // Set the prop as not required.
      cy.findByLabelText('Required').toggleToggle();
      // Modify the first value. The default value should now be removed, as the
      // prop is not required, and the previously set default value was modified.
      cy.findByTestId(`canvas-prop-enum-value-${propId}-0`).type('Whiskey');
      cy.findByLabelText('Default value').should('have.text', '- None -');
      cy.wrap(store).then((store) => {
        expect(
          selectCodeComponentProperty('props')(store.getState())[0],
        ).to.deep.include(
          {
            enum: ['CharlieZuluXRayWhiskey'],
            example: '',
          },
          'Should have the appropriate enum and example values',
        );
      });

      // Set the prop as required again. The default value should now be the first
      // value.
      cy.findByLabelText('Required').toggleToggle();
      cy.findByLabelText('Default value').should(
        'have.text',
        'CharlieZuluXRayWhiskey',
      );
      cy.wrap(store).then((store) => {
        expect(
          selectCodeComponentProperty('props')(store.getState())[0],
        ).to.deep.include(
          {
            enum: ['CharlieZuluXRayWhiskey'],
            example: 'CharlieZuluXRayWhiskey',
          },
          'Should have the appropriate enum and example values',
        );
      });

      // Delete the one last remaining value. "Default value" should not be
      // visible, as there are no values left.
      cy.findByTestId(`canvas-prop-enum-value-delete-${propId}-0`).click();
      cy.findByLabelText('Default value').should('not.exist');
      cy.wrap(store).then((store) => {
        expect(
          selectCodeComponentProperty('props')(store.getState())[0],
        ).to.deep.include(
          {
            enum: [],
            example: '',
          },
          'Should have the appropriate enum and example values',
        );
      });
    });
  });

  it('creates a new integer list prop', () => {
    // The 'creates a new text list prop' test case already covers the
    // functionality of adding and removing values. This test is here as a sanity
    // check that the integer type works as expected. The only difference is that
    // we can't add a string value.

    cy.findByText('Add').click();

    cy.wrap(store).then((store) => {
      const propId = selectCodeComponentProperty('props')(store.getState())[0]
        .id;
      cy.wrap(propId).as('propId');
    });

    cy.findByLabelText('Prop name').type('Level');
    cy.findByLabelText('Type').click();
    cy.findByText('List: integer').click();
    cy.findByLabelText('Default value').should('not.exist');

    cy.get('@propId').then((propId) => {
      // Add a new value, make sure "Default value" is not shown yet while the
      // new value is empty.
      cy.findByText('Add value').click();
      cy.findByLabelText('Default value').should('not.exist');

      // Ensure we can't type a string value.
      cy.findByTestId(`canvas-prop-enum-value-${propId}-0`).type(
        'Typing an invalid string value with hopefully no effect',
      );
      cy.findByTestId(`canvas-prop-enum-value-${propId}-0`).should(
        'have.value',
        '',
      );

      // Type a value, make sure "Default value" is shown.
      cy.findByTestId(`canvas-prop-enum-value-${propId}-0`).type('1');
      cy.findByLabelText('Default value').should('exist');
      cy.wrap(store).then((store) => {
        expect(
          selectCodeComponentProperty('props')(store.getState())[0],
        ).to.deep.include(
          {
            enum: ['1'],
            example: '',
          },
          'Should have the appropriate enum and example values',
        );
      });

      // Set the prop as required. "Default value" now should have the value
      // selected.
      cy.findByLabelText('Required').toggleToggle();
      cy.findByLabelText('Default value').should('have.text', '1');
      cy.wrap(store).then((store) => {
        expect(
          selectCodeComponentProperty('props')(store.getState())[0],
        ).to.deep.include(
          {
            enum: ['1'],
            example: '1',
          },
          'Should have the appropriate enum and example values',
        );
      });

      // Add a second value.
      cy.findByText('Add value').click();
      cy.findByTestId(`canvas-prop-enum-value-${propId}-1`).type('2');
      cy.wrap(store).then((store) => {
        expect(
          selectCodeComponentProperty('props')(store.getState())[0],
        ).to.deep.include(
          {
            enum: ['1', '2'],
            example: '1',
          },
          'Should have the appropriate enum and example values',
        );
      });

      // Set it as the default value.
      cy.findByLabelText('Default value').click();
      cy.findByText('2').click();
      cy.wrap(store).then((store) => {
        expect(
          selectCodeComponentProperty('props')(store.getState())[0],
        ).to.deep.include(
          {
            enum: ['1', '2'],
            example: '2',
          },
          'Should have the appropriate enum and example values',
        );
      });

      // Delete the second value.
      cy.findByTestId(`canvas-prop-enum-value-delete-${propId}-1`).click();
      // The default value should now be the first value.
      cy.findByLabelText('Default value').should('have.text', '1');
      cy.wrap(store).then((store) => {
        expect(
          selectCodeComponentProperty('props')(store.getState())[0],
        ).to.deep.include(
          {
            enum: ['1'],
            example: '1',
          },
          'Should have the appropriate enum and example values',
        );
      });
    });
  });

  it('removes enum values when the type is changed', () => {
    cy.findByText('Add').click();

    // Add an enum value for a List: text prop.
    cy.findByLabelText('Type').click();
    cy.findByText('List: text').click();
    cy.findByText('Add value').click();
    cy.findByTestId(/canvas-prop-enum-value-[0-9a-f-]+-0/).type('Alpha');
    cy.wrap(store).then((store) => {
      expect(
        selectCodeComponentProperty('props')(store.getState())[0].enum,
      ).to.deep.equal(['Alpha']);
    });

    // Change the type to List: integer. The enum value should be removed.
    cy.findByLabelText('Type').click();
    cy.findByText('List: integer').click();
    cy.findByTestId(/canvas-prop-enum-value-[0-9a-f-]+-0/).should('not.exist');
    cy.wrap(store).then((store) => {
      expect(
        selectCodeComponentProperty('props')(store.getState())[0].enum,
      ).to.deep.equal([]);
    });
    // Add an enum value.
    cy.findByText('Add value').click();
    cy.findByTestId(/canvas-prop-enum-value-[0-9a-f-]+-0/).type('922');
    cy.wrap(store).then((store) => {
      expect(
        selectCodeComponentProperty('props')(store.getState())[0].enum,
      ).to.deep.equal(['922']);
    });

    // Change the type to List: text. The enum value should be removed.
    cy.findByLabelText('Type').click();
    cy.findByText('List: text').click();
    cy.findByTestId(/canvas-prop-enum-value-[0-9a-f-]+-0/).should('not.exist');
    cy.wrap(store).then((store) => {
      expect(
        selectCodeComponentProperty('props')(store.getState())[0].enum,
      ).to.deep.equal([]);
    });
  });

  it('removes examples when the type is changed', () => {
    cy.findByText('Add').click();

    // Add an example value for a text prop.
    cy.findByText('Example value').type('Alpha');
    cy.wrap(store).then((store) => {
      expect(
        selectCodeComponentProperty('props')(store.getState())[0].example,
      ).to.equal('Alpha');
    });

    // Change the type to Integer. The example value should be removed.
    cy.findByLabelText('Type').click();
    cy.findByText('Integer').click();
    cy.findByLabelText('Example value').should('have.value', '');
    cy.wrap(store).then((store) => {
      expect(
        selectCodeComponentProperty('props')(store.getState())[0].example,
      ).to.equal('');
    });
    // Add an example value.
    cy.findByLabelText('Example value').type('922');
    cy.wrap(store).then((store) => {
      expect(
        selectCodeComponentProperty('props')(store.getState())[0].example,
      ).to.equal('922');
    });

    // Change the type to Number. The example value should be removed.
    cy.findByLabelText('Type').click();
    cy.findByText('Number').click();
    cy.findByLabelText('Example value').should('have.value', '');
    cy.wrap(store).then((store) => {
      expect(
        selectCodeComponentProperty('props')(store.getState())[0].example,
      ).to.equal('');
    });
    // Add an example value.
    cy.findByLabelText('Example value').type('9.22');
    cy.wrap(store).then((store) => {
      expect(
        selectCodeComponentProperty('props')(store.getState())[0].example,
      ).to.equal('9.22');
    });

    // Change the type to Text. The example value should be removed.
    cy.findByLabelText('Type').click();
    cy.findByText('Text').click();
    cy.findByLabelText('Example value').should('have.value', '');
    cy.wrap(store).then((store) => {
      expect(
        selectCodeComponentProperty('props')(store.getState())[0].example,
      ).to.equal('');
    });

    // Change the type to Formatted text. The example value should be removed.
    cy.findByLabelText('Type').click();
    cy.findByText('Formatted text').click();
    cy.findByLabelText('Example value').should('have.value', '');
    cy.wrap(store).then((store) => {
      expect(
        selectCodeComponentProperty('props')(store.getState())[0].example,
      ).to.equal('');
    });

    // Change type to Image. The example value should be removed.
    cy.findByLabelText('Type').click();
    cy.findByText('Image').click();
    cy.findByLabelText('Example aspect ratio').should(
      'have.text',
      '4:3 (Standard)',
    );
    cy.wrap(store).then((store) => {
      expect(
        selectCodeComponentProperty('props')(store.getState())[0].example,
      ).to.deep.equal({
        src: 'https://placehold.co/800x600@2x.png?alternateWidths=https%3A%2F%2Fplacehold.co%2F%7Bwidth%7Dx%7Bheight%7D%402x.png',
        width: 800,
        height: 600,
        alt: 'Example image placeholder',
      });
    });

    // Change the type to Link. The example value should be removed.
    cy.findByLabelText('Type').click();
    cy.findByText('Link').click();
    cy.findByLabelText('Example value').should('have.value', '');
    cy.wrap(store).then((store) => {
      expect(
        selectCodeComponentProperty('props')(store.getState())[0].example,
      ).to.equal('');
    });
  });

  it('creates a new formatted text prop', () => {
    cy.findByText('Add').click();

    cy.findByLabelText('Prop name').type('Description');
    cy.findByLabelText('Type').click();
    cy.findByText('Formatted text').click();
    cy.findByLabelText('Example value').should(
      'have.attr',
      'placeholder',
      'Enter a text value',
    );
    cy.findByLabelText('Example value').type('Your description goes here');
    cy.findByLabelText('Example value').should(
      'have.value',
      'Your description goes here',
    );

    cy.wrap(store).then((store) => {
      const prop = selectCodeComponentProperty('props')(store.getState())[0];
      expect(prop).to.deep.include(
        {
          name: 'Description',
          type: 'string',
          example: 'Your description goes here',
          format: undefined,
          contentMediaType: 'text/html',
          'x-formatting-context': 'block',
        },
        'Should have the correct prop metadata',
      );
    });
  });

  it('creates a new image prop', () => {
    cy.findByText('Add').click();

    cy.findByLabelText('Prop name').type('Image');
    cy.findByLabelText('Type').click();
    cy.findByText('Image').click();
    // Verify the default example values.
    cy.findByLabelText('Example aspect ratio').should(
      'have.text',
      '4:3 (Standard)',
    );
    cy.findByLabelText('Pixel density').should(
      'have.text',
      '2x (High density)',
    );

    cy.wrap(store).then((store) => {
      expect(
        selectCodeComponentProperty('props')(store.getState())[0],
      ).to.deep.include(
        {
          type: 'object',
          example: {
            src: 'https://placehold.co/800x600@2x.png?alternateWidths=https%3A%2F%2Fplacehold.co%2F%7Bwidth%7Dx%7Bheight%7D%402x.png',
            width: 800,
            height: 600,
            alt: 'Example image placeholder',
          },
          format: undefined,
          $ref: 'json-schema-definitions://canvas.module/image',
        },
        'Should have the appropriate type, example value, and $ref',
      );
    });

    // Select the "None" option for the example aspect ratio.
    cy.findByLabelText('Example aspect ratio').click();
    cy.findByText('- None -').click();
    // The pixel density should now be hidden.
    cy.findByLabelText('Pixel density').should('not.exist');
    cy.wrap(store).then((store) => {
      expect(
        selectCodeComponentProperty('props')(store.getState())[0],
      ).to.deep.include(
        {
          type: 'object',
          example: '',
          format: undefined,
          $ref: 'json-schema-definitions://canvas.module/image',
        },
        'Should have the appropriate type, example value, and $ref',
      );
    });

    // Set the prop as required.
    cy.findByLabelText('Required').toggleToggle();
    // The example aspect ratio and pixel density should now be the default values.
    cy.findByLabelText('Example aspect ratio').should(
      'have.text',
      '4:3 (Standard)',
    );
    cy.findByLabelText('Pixel density').should(
      'have.text',
      '2x (High density)',
    );
    cy.wrap(store).then((store) => {
      expect(
        selectCodeComponentProperty('props')(store.getState())[0],
      ).to.deep.include(
        {
          type: 'object',
          example: {
            src: 'https://placehold.co/800x600@2x.png?alternateWidths=https%3A%2F%2Fplacehold.co%2F%7Bwidth%7Dx%7Bheight%7D%402x.png',
            width: 800,
            height: 600,
            alt: 'Example image placeholder',
          },
          format: undefined,
          $ref: 'json-schema-definitions://canvas.module/image',
        },
        'Should have the appropriate type, example value, and $ref',
      );
    });

    // Update the aspect ratio.
    cy.findByLabelText('Example aspect ratio').click();
    cy.findByText('16:9 (Widescreen)').click();
    cy.findByLabelText('Example aspect ratio').should(
      'have.text',
      '16:9 (Widescreen)',
    );
    // Update the pixel density.
    cy.findByLabelText('Pixel density').click();
    cy.findByText('3x (Ultra-high density)').click();
    cy.findByLabelText('Pixel density').should(
      'have.text',
      '3x (Ultra-high density)',
    );
    cy.wrap(store).then((store) => {
      expect(
        selectCodeComponentProperty('props')(store.getState())[0],
      ).to.deep.include(
        {
          type: 'object',
          example: {
            src: 'https://placehold.co/1280x720@3x.png?alternateWidths=https%3A%2F%2Fplacehold.co%2F%7Bwidth%7Dx%7Bheight%7D%403x.png',
            width: 1280,
            height: 720,
            alt: 'Example image placeholder',
          },
          format: undefined,
          $ref: 'json-schema-definitions://canvas.module/image',
        },
        'Should have the appropriate type, example value, and $ref',
      );
    });

    // Set the prop as not required, then back to required. The example aspect
    // ratio and pixel density should be the previous values.
    cy.findByLabelText('Required').toggleToggle();
    cy.findByLabelText('Required').toggleToggle();
    cy.findByLabelText('Example aspect ratio').should(
      'have.text',
      '16:9 (Widescreen)',
    );
    cy.findByLabelText('Pixel density').should(
      'have.text',
      '3x (Ultra-high density)',
    );
    cy.wrap(store).then((store) => {
      expect(
        selectCodeComponentProperty('props')(store.getState())[0],
      ).to.deep.include(
        {
          type: 'object',
          example: {
            src: 'https://placehold.co/1280x720@3x.png?alternateWidths=https%3A%2F%2Fplacehold.co%2F%7Bwidth%7Dx%7Bheight%7D%403x.png',
            width: 1280,
            height: 720,
            alt: 'Example image placeholder',
          },
          format: undefined,
          $ref: 'json-schema-definitions://canvas.module/image',
        },
        'Should have the appropriate type, example value, and $ref',
      );
    });
  });

  it('creates a new video prop', () => {
    cy.findByText('Add').click();

    cy.findByLabelText('Prop name').type('Video');
    cy.findByLabelText('Type').click();
    cy.findByText('Video').click();
    // Verify the default example value.
    cy.findByLabelText('Example aspect ratio').should(
      'have.text',
      '16:9 (Widescreen)',
    );

    cy.wrap(store).then((store) => {
      expect(
        selectCodeComponentProperty('props')(store.getState())[0],
      ).to.deep.include(
        {
          type: 'object',
          example: {
            src: `/ui/assets/videos/mountain_wide.mp4`,
            poster: 'https://placehold.co/1920x1080.png?text=Widescreen',
          },
          format: undefined,
          $ref: 'json-schema-definitions://canvas.module/video',
        },
        'Should have the appropriate type, example value, and $ref',
      );
    });

    // Select the "None" option for the example aspect ratio.
    cy.findByLabelText('Example aspect ratio').click();
    cy.findByText('- None -').click();
    cy.wrap(store).then((store) => {
      expect(
        selectCodeComponentProperty('props')(store.getState())[0],
      ).to.deep.include(
        {
          type: 'object',
          example: '',
          format: undefined,
          $ref: 'json-schema-definitions://canvas.module/video',
        },
        'Should have the appropriate type, example value, and $ref',
      );
    });

    // Set the prop as required.
    cy.findByLabelText('Required').toggleToggle();
    // The example aspect ratio should now be the default values.
    cy.findByLabelText('Example aspect ratio').should(
      'have.text',
      '16:9 (Widescreen)',
    );
    cy.wrap(store).then((store) => {
      expect(
        selectCodeComponentProperty('props')(store.getState())[0],
      ).to.deep.include(
        {
          type: 'object',
          example: {
            src: `/ui/assets/videos/mountain_wide.mp4`,
            poster: 'https://placehold.co/1920x1080.png?text=Widescreen',
          },
          format: undefined,
          $ref: 'json-schema-definitions://canvas.module/video',
        },
        'Should have the appropriate type, example value, and $ref',
      );
    });

    // Update the aspect ratio.
    cy.findByLabelText('Example aspect ratio').click();
    cy.findByText('9:16 (Vertical)').click();
    cy.findByLabelText('Example aspect ratio').should(
      'have.text',
      '9:16 (Vertical)',
    );
    cy.wrap(store).then((store) => {
      expect(
        selectCodeComponentProperty('props')(store.getState())[0],
      ).to.deep.include(
        {
          type: 'object',
          example: {
            src: `/ui/assets/videos/bird_vertical.mp4`,
            poster: 'https://placehold.co/1080x1920.png?text=Vertical',
          },
          format: undefined,
          $ref: 'json-schema-definitions://canvas.module/video',
        },
        'Should have the appropriate type, example value, and $ref',
      );
    });

    // Set the prop as not required, then back to required. The example aspect
    // ratio should be the previous values.
    cy.findByLabelText('Required').toggleToggle();
    cy.findByLabelText('Required').toggleToggle();
    cy.findByLabelText('Example aspect ratio').should(
      'have.text',
      '9:16 (Vertical)',
    );
    cy.wrap(store).then((store) => {
      expect(
        selectCodeComponentProperty('props')(store.getState())[0],
      ).to.deep.include(
        {
          type: 'object',
          example: {
            src: `/ui/assets/videos/bird_vertical.mp4`,
            poster: 'https://placehold.co/1080x1920.png?text=Vertical',
          },
          format: undefined,
          $ref: 'json-schema-definitions://canvas.module/video',
        },
        'Should have the appropriate type, example value, and $ref',
      );
    });
  });

  it('creates a new link prop', () => {
    cy.findByText('Add').click();

    // Add a new link prop, leaving the Link type as "Relative path".
    cy.findByLabelText('Prop name').type('Link');
    cy.findByLabelText('Type').click();
    cy.findByText('Link').click();
    cy.findByLabelText('Link type').should('have.text', 'Relative path');
    cy.findByLabelText('Example value').should(
      'have.attr',
      'placeholder',
      'Enter a path',
    );
    cy.findByLabelText('Example value').type('gerbeaud');
    // Verify the prop metadata.
    cy.wrap(store).then((store) => {
      const prop = selectCodeComponentProperty('props')(store.getState())[0];
      expect(prop).to.deep.include(
        {
          name: 'Link',
          type: 'string',
          example: 'gerbeaud',
          format: 'uri-reference',
          $ref: undefined,
        },
        'Should have the correct prop metadata',
      );
    });

    // Change the Link type to "Full URL".
    cy.findByLabelText('Link type').click();
    cy.findByText('Full URL').click();
    cy.findByLabelText('Example value').should(
      'have.attr',
      'placeholder',
      'Enter a URL',
    );
    cy.findByLabelText('Example value').clear();
    cy.findByLabelText('Example value').type('https://example.com');
    // Verify the prop metadata.
    cy.wrap(store).then((store) => {
      const prop = selectCodeComponentProperty('props')(store.getState())[0];
      expect(prop).to.deep.include(
        {
          name: 'Link',
          type: 'string',
          example: 'https://example.com',
          format: 'uri',
          $ref: undefined,
        },
        'Should have the correct prop metadata',
      );
    });

    // Change the Link type back to "Relative path".
    cy.findByLabelText('Link type').click();
    cy.findByText('Relative path').click();
    cy.findByLabelText('Example value').should(
      'have.attr',
      'placeholder',
      'Enter a path',
    );
    cy.findByLabelText('Example value').clear();
    cy.findByLabelText('Example value').type('hazelnut');
    // Verify the prop metadata.
    cy.wrap(store).then((store) => {
      const prop = selectCodeComponentProperty('props')(store.getState())[0];
      expect(prop).to.deep.include(
        {
          name: 'Link',
          type: 'string',
          example: 'hazelnut',
          format: 'uri-reference',
          $ref: undefined,
        },
        'Should have the correct prop metadata',
      );
    });
  });

  it('validates example value of a link prop', () => {
    cy.findByText('Add').click();

    cy.findByLabelText('Prop name').type('Link');
    cy.findByLabelText('Type').click();
    cy.findByText('Link').click();
    cy.findByLabelText('Example value').type('gerbeaud');
    cy.findByLabelText('Example value').blur();
    cy.findByLabelText('Example value').should(
      'not.have.attr',
      'data-invalid-prop-value',
    );

    cy.findByLabelText('Example value').type(' ^ 0330');
    cy.findByLabelText('Example value').blur();
    cy.findByLabelText('Example value').should(
      'have.attr',
      'data-invalid-prop-value',
    );

    // Typing into the field should reset the invalid state.
    cy.findByLabelText('Example value').type(' ^');
    cy.findByLabelText('Example value').should(
      'not.have.attr',
      'data-invalid-prop-value',
    );

    cy.findByLabelText('Example value').blur();
    cy.findByLabelText('Example value').should(
      'have.attr',
      'data-invalid-prop-value',
    );

    // Switch to the full URL type.
    cy.findByLabelText('Link type').click();
    cy.findByText('Full URL').click();
    // The invalid state should be cleared by switching the link type.
    cy.findByLabelText('Example value').should(
      'not.have.attr',
      'data-invalid-prop-value',
    );

    cy.findByLabelText('Example value').clear();
    cy.findByLabelText('Example value').type('https://hazelnut.com');
    cy.findByLabelText('Example value').blur();
    cy.findByLabelText('Example value').should(
      'not.have.attr',
      'data-invalid-prop-value',
    );

    cy.findByLabelText('Example value').clear();
    cy.findByLabelText('Example value').type('0203');
    cy.findByLabelText('Example value').blur();
    cy.findByLabelText('Example value').should(
      'have.attr',
      'data-invalid-prop-value',
    );

    // An empty value should be valid.
    cy.findByLabelText('Example value').clear();
    cy.findByLabelText('Example value').blur();
    cy.findByLabelText('Example value').should(
      'not.have.attr',
      'data-invalid-prop-value',
    );
  });

  it('parses the image prop example URL', () => {
    expect(
      parseImageExampleSrc('https://placehold.co/801x601.png'),
    ).to.deep.equal({
      aspectRatio: '4:3', // Fallback to default aspect ratio, size is not known.
      pixelDensity: '1x', // Matched from URL.
    });

    expect(
      parseImageExampleSrc('https://placehold.co/801x601@2x.png'),
    ).to.deep.equal({
      aspectRatio: '4:3', // Fallback to default aspect ratio, size is not known.
      pixelDensity: '2x', // Exact match.
    });

    expect(
      parseImageExampleSrc('https://placehold.co/900x600@4x.png'),
    ).to.deep.equal({
      aspectRatio: '3:2',
      pixelDensity: '2x', // Fallback to default pixel density, density is not known.
    });

    expect(
      parseImageExampleSrc('https://placehold.co/900x600@2x.png'),
    ).to.deep.equal({
      aspectRatio: '3:2',
      pixelDensity: '2x',
    });

    expect(
      parseImageExampleSrc('https://placehold.co/1400x600@3x.png'),
    ).to.deep.equal({
      aspectRatio: '21:9',
      pixelDensity: '3x',
    });
  });
});
