import { Provider } from 'react-redux';
import { Theme } from '@radix-ui/themes';

import { makeStore } from '@/app/store';
import {
  addSlot,
  selectCodeComponentProperty,
  updateSlot,
} from '@/features/code-editor/codeEditorSlice';
import ComponentData from '@/features/code-editor/component-data/ComponentData';

import '@/styles/radix-themes';
import '@/styles/index.css';

describe('Component data / slots in code editor', () => {
  let store;

  beforeEach(() => {
    cy.viewport(500, 900);
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
    cy.findByRole('tab', { name: 'Slots' }).click();
  });

  it('creates, reorders, and removes slots', () => {
    // Add a new slot.
    cy.findByText('Add').click();

    cy.findByLabelText('Slot name').should('exist');
    cy.findByText('Example HTML/JSX value').should('exist');

    cy.log('Checking first slot in store with default values');
    cy.wrap(store).then((store) => {
      const slots = selectCodeComponentProperty('slots')(store.getState());
      expect(slots).to.have.length(
        1,
        'Should have exactly one slot after clicking new slot button',
      );
      expect(slots[0]).to.deep.include(
        {
          name: '',
          example: '',
        },
        'Should have the correct default slot values',
      );
    });

    cy.findByLabelText('Slot name').type('Alpha');
    cy.findByTestId(/slot-example-[0-9a-f-]+/).click();
    cy.realType('A slot');

    cy.log('Checking updated slot');
    cy.wrap(store).then((store) => {
      const slots = selectCodeComponentProperty('slots')(store.getState());
      expect(slots).to.have.length(1, 'Should have exactly one slot');
      expect(slots[0]).to.deep.include(
        {
          name: 'Alpha',
          example: 'A slot',
        },
        'Should have the updated name and example value',
      );
    });

    cy.log('Adding more slots');

    // Add a second slot
    cy.findByText('Add').click();
    cy.findAllByLabelText('Slot name').last().type('Bravo');
    cy.findAllByTestId(/slot-example-[0-9a-f-]+/)
      .last()
      .click();
    cy.realType('B slot');

    // Add a third slot
    cy.findByText('Add').click();
    cy.findAllByLabelText('Slot name').last().type('Charlie');
    cy.findAllByTestId(/slot-example-[0-9a-f-]+/)
      .last()
      .click();
    cy.realType('C slot');

    // Check that the slots are in the store.
    cy.wrap(store).then((store) => {
      const slots = selectCodeComponentProperty('slots')(store.getState());
      expect(slots).to.have.length(3, 'Should have exactly three slots');
      expect(slots[0]).to.deep.include({
        name: 'Alpha',
        example: 'A slot',
      });
      expect(slots[1]).to.deep.include({
        name: 'Bravo',
        example: 'B slot',
      });
      expect(slots[2]).to.deep.include({
        name: 'Charlie',
        example: 'C slot',
      });
    });

    // Reorder the slots. Move the first slot to the third position.
    cy.findAllByLabelText('Move slot')
      .first()
      .realDnd('[data-testid="slot-2"]', {
        position: 'bottom',
      });
    // Check that the slots in the store are in the new order.
    cy.wrap(store).then((store) => {
      const slots = selectCodeComponentProperty('slots')(store.getState());
      expect(slots[0].name).to.equal('Bravo');
      expect(slots[1].name).to.equal('Charlie');
      expect(slots[2].name).to.equal('Alpha');
    });

    // Reorder the slots again. Move the first slot to the second position.
    cy.findAllByLabelText('Move slot')
      .first()
      .realDnd('[data-testid="slot-1"]', {
        position: 'top',
      });
    // Check that the slots in the store are in the new order.
    cy.wrap(store).then((store) => {
      const slots = selectCodeComponentProperty('slots')(store.getState());
      expect(slots[0].name).to.equal('Charlie');
      expect(slots[1].name).to.equal('Bravo');
      expect(slots[2].name).to.equal('Alpha');
    });

    // Remove the first slot.
    cy.findAllByLabelText('Remove slot').first().click();
    // Check that the slots in the store are in the new order.
    cy.wrap(store).then((store) => {
      const slots = selectCodeComponentProperty('slots')(store.getState());
      expect(slots).to.have.length(2, 'Should have exactly two slots');
      expect(slots[0].name).to.equal('Bravo');
      expect(slots[1].name).to.equal('Alpha');
    });

    // Remove the last slot.
    cy.findAllByLabelText('Remove slot').last().click();
    // Check that the slots in the store are in the new order.
    cy.wrap(store).then((store) => {
      const slots = selectCodeComponentProperty('slots')(store.getState());
      expect(slots).to.have.length(1, 'Should have exactly one slot');
      expect(slots[0].name).to.equal('Bravo');
    });

    // Remove the one remaining slot.
    cy.findByLabelText('Remove slot').click();
    cy.wrap(store).then((store) => {
      const slots = selectCodeComponentProperty('slots')(store.getState());
      expect(slots).to.have.length(0, 'Should have no slots');
    });
  });

  it('displays an existing slot', () => {
    // Add a new slot directly to the store, update it, and toggle it as required.
    cy.wrap(store).then((store) => {
      store.dispatch(addSlot());
      const newSlot = selectCodeComponentProperty('slots')(store.getState())[0];
      cy.log(
        `Added new slot directly to the store: ${JSON.stringify(newSlot)}`,
      );
      store.dispatch(
        updateSlot({
          id: newSlot.id,
          updates: { name: 'Alpha', example: 'A slot' },
        }),
      );
      const updatedSlot = selectCodeComponentProperty('slots')(
        store.getState(),
      )[0];
      cy.log(
        `Updated slot directly in the store: ${JSON.stringify(updatedSlot)}`,
      );
    });

    // Check that the slot is displayed in the component.
    cy.findByLabelText('Slot name').should('have.value', 'Alpha');
    cy.findByText('A slot').should('exist');
  });
});
