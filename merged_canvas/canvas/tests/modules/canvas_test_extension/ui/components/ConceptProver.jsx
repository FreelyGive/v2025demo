import { useSelector, useDispatch } from 'react-redux';
import Button from './Button';
import { useState } from 'react';

const ConceptProver = () => {
  const dispatch = useDispatch();
  const [selectedLayoutItem, setSelectedLayoutItem] = useState();
  const [selectedFromListComponentType, setSelectedFromListComponentType] = useState();

  // Get the entire layout model from the Redux store.
  const theLayout = useSelector((state) => state?.layoutModel?.present?.layout);

  // Get the available components list from the redux store.
  const availableComponents = useSelector((state) => {
    return state?.componentAndLayoutApi?.queries['getComponents(undefined)']
      ?.data;
  });

  // Get the uuid of the selected component from the Redux store.
  const selectedComponent = useSelector((state) => state.ui.selection.items[0]);
  const itemsInLayout = [];

  const flatComponentsList = (components) => {
    components.forEach((component) => {
      itemsInLayout.push(component);
      component.slots.forEach((slot) => flatComponentsList(slot.components));
    });
  };
  theLayout.forEach((region) => {
    flatComponentsList(region.components || []);
  });

  const node = drupalSettings.canvas.layoutUtils.findComponentByUuid(theLayout, selectedComponent);
  const [selectedComponentType] = node?.type ? node.type.split('@') : [];

  // Create a dropdown with every available component as options.
  const componentsSelect = () => {
    return (
      <div>
        <label>
          Components available in library:
          <br />
          <select
            data-testid="ex-select-component"
            style={{ maxWidth: '250px' }}
            onChange={(e) => setSelectedFromListComponentType(e.target.value)}
          >
            <option value="" key={99999999}>
              {typeof availableComponents === 'object'
                ? '--Select A Component--'
                : '-- Component List Not Ready --'}
            </option>
            {typeof availableComponents === 'object' &&
              Object.entries(availableComponents).map(([key, item], index) => (
                <option key={index} value={item.id}>
                  {item.name}
                </option>
              ))}
          </select>
          {/* When a component type is selected, provide the option to insert it in the layout. */}
          {selectedFromListComponentType && (
            <Button
              data-testid="ex-insert"
              onClick={() => {
                const component = availableComponents[selectedFromListComponentType];
                const withValues = component?.propSources?.heading ? { heading: 'Hijacked Value' } : null;
                dispatch(
                  drupalSettings.canvas.layoutUtils.addNewComponentToLayout({
                    component,
                    withValues,
                  },
                    drupalSettings.canvas.componentSelectionUtils.setSelectedComponent
                  ),
                );
              }}
            >
              insert
            </Button>
          )}
        </label>
      </div>
    );
  };

  const layoutItemsSelect = () => {
    return (
      <div>
        <label>
          Items in layout:
          <br />
          <select
            data-testid="ex-select-in-layout"
            style={{ maxWidth: '250px' }}
            onChange={(e) => setSelectedLayoutItem(e.target.value)}
          >
            <option value="" key={99999999}>
              {itemsInLayout.length
                ? '--Choose an item in the layout--'
                : '-- No items in layout yet --'}
            </option>
            {itemsInLayout.map((item, index) => (
              <option key={index} value={item.uuid}>
                {item.type}({item.uuid})
              </option>
            ))}
          </select>
        </label>
        {/* If the above <select> has chosen an item, provide a way to focus it. */}
        {selectedLayoutItem && (
          <Button
            data-testid="ex-focus"
            onClick={() => {
              // Dispatch based on action name.
              // Update redux store so the layout item chosen is selected in the UI.
              drupalSettings.canvas.componentSelectionUtils.setSelectedComponent(
                selectedLayoutItem,
              );
            }}
          >
            focus
          </Button>
        )}

        {/* If the above <select> has chosen an item, provide a way to delete it. */}
        {selectedLayoutItem && (
          <Button
            data-testid="ex-delete"
            onClick={() => {
              // Dispatch based on action name.
              // Update redux store so the layout item chosen is selected in the UI.
              dispatch({
                type: 'layoutModel/deleteNode',
                payload: selectedLayoutItem,
              });
              // This sets the selected component to null so the contextual menu
              // closes instead of attempting to render to form for a deleted
              // component.
              dispatch({ type: 'ui/unsetSelectedComponent' });
              setSelectedLayoutItem(null);
            }}
          >
            delete
          </Button>
        )}
      </div>
    );
  };

  return (
    <div
      style={{
        backgroundColor: '#c0ffee',
        border: '1px solid #ccc',
        bottom: '2rem',
        padding: '.75rem',
      }}
    >
      <div>
        {layoutItemsSelect()}
        {componentsSelect()}
        <div style={{ marginTop: '1rem' }}>
          <b>Event: Detect selected element</b>:<br />
          <small data-testid="ex-selected-element">{selectedComponent}</small>
        </div>
        {/* When a hero is selected, make a button available to programmatically
          update its value. */}
        {selectedComponentType === 'sdc.canvas_test_sdc.my-hero' && (
          <Button
            data-testid="ex-update"
            onClick={() => {
              dispatch(
                drupalSettings.canvas.layoutUtils.updateExistingComponentValues(
                  {
                    componentToUpdateId: selectedComponent,
                    values: {heading: 'an extension updated this'},
                  })
              );
            }}
          >
            Update the heading value of the selected hero component
          </Button>

        )}
      </div>
    </div>
  );
};

export default ConceptProver;
