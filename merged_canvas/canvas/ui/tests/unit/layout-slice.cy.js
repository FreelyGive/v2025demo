import { makeStore } from '@/app/store';
import {
  deleteNode,
  duplicateNode,
  initialState,
  layoutModelSlice,
  moveNode,
  selectLayoutHistory,
  setLayoutModel,
  shiftNode,
  sortNode,
} from '@/features/layout/layoutModelSlice';
import { setPageData } from '@/features/pageData/pageDataSlice';
import {
  selectUndoType,
  initialState as uiInitialState,
  UndoRedoActionCreators,
} from '@/features/ui/uiSlice';

import layoutFixture from '../fixtures/layout-default.json';

let layout;

beforeEach(() => {
  layout = layoutFixture;
});

describe('Set layout model', () => {
  it('Should set model and layout', () => {
    const state = layoutModelSlice.reducer(
      initialState,
      setLayoutModel(layout),
    );
    expect(state.layout).to.eq(layout.layout);
    expect(state.model).to.eq(layout.model);
  });
});

describe('Delete node', () => {
  it('Should delete node', () => {
    expect(layout.layout[0].components).to.have.length(5);
    expect(layout.layout[0].components.map((item) => item.uuid)).to.deep.equal([
      'a7470350-deb2-4d9f-982c-464d356403d4',
      'static-static-card2df',
      'static-static-card3rr',
      'static-image-static-imageStyle-something7d',
      'ee07d472-a754-4427-b6d4-acfc6f92bbdc',
    ]);
    let state = layoutModelSlice.reducer(
      layout,
      deleteNode('static-static-card2df'),
    );
    expect(state.layout[0].components.length).to.eq(4);
    expect(state.layout[0].components.map((item) => item.uuid)).to.deep.equal([
      'a7470350-deb2-4d9f-982c-464d356403d4',
      'static-static-card3rr',
      'static-image-static-imageStyle-something7d',
      'ee07d472-a754-4427-b6d4-acfc6f92bbdc',
    ]);

    // Delete node with children
    // Check if all UUIDs of nested component(Two Column) exists before deletion
    const childUuids = [
      'ee07d472-a754-4427-b6d4-acfc6f92bbdc',
      '6f3224e2-cb61-46e4-a9e4-35b4d18f0a82',
      '3b709ed2-99d3-4db2-869d-ca426f69fbb9',
      'eaa37ee1-7d50-4041-b04c-c80bdbac3412',
    ];
    expect(
      Object.keys(state.model).filter((item) => childUuids.includes(item))
        .length,
    ).to.eq(4);
    state = layoutModelSlice.reducer(
      layout,
      deleteNode('ee07d472-a754-4427-b6d4-acfc6f92bbdc'),
    );
    expect(state.layout[0].components.length).to.eq(4);
    expect(state.layout[0].components.map((item) => item.uuid)).to.deep.equal([
      'a7470350-deb2-4d9f-982c-464d356403d4',
      'static-static-card2df',
      'static-static-card3rr',
      'static-image-static-imageStyle-something7d',
    ]);
    // To be double sure: check if all UUIDs of nested component(Two Column) should not exist after deletion
    expect(
      Object.keys(state.model).filter((item) => childUuids.includes(item))
        .length,
    ).to.eq(0);
    expect(Object.keys(state.model)).to.deep.equal([
      'static-image-udf7d',
      'static-static-card1ab',
      'static-static-card2df',
      'static-static-card3rr',
      'static-image-static-imageStyle-something7d',
      'a7470350-deb2-4d9f-982c-464d356403d4',
    ]);
  });
});

describe('Move node', () => {
  it('Should move node', () => {
    expect(layout.layout[0].components[0].slots[0].components.length).to.eq(2);
    expect(layout.layout[0].components[4].slots[0].components.length).to.eq(1);
    expect(
      layout.layout[0].components[0].slots[0].components[0].uuid,
    ).to.deep.equal('static-static-card1ab');
    const state = layoutModelSlice.reducer(
      layout,
      moveNode({
        uuid: 'static-static-card1ab',
        to: [0, 4, 0, 1],
      }),
    );
    expect(state.layout[0].components[0].slots[0].components.length).to.eq(1);
    expect(state.layout[0].components[4].slots[0].components.length).to.eq(2);
    expect(
      state.layout[0].components[4].slots[0].components.map(
        (item) => item.uuid,
      ),
    ).to.deep.equal([
      '6f3224e2-cb61-46e4-a9e4-35b4d18f0a82',
      'static-static-card1ab',
    ]);
  });
});

describe('Sort node', () => {
  it('Should sort node', () => {
    expect(layout.layout[0].components[0].slots[0].components[0].uuid).to.eq(
      'static-static-card1ab',
    );
    expect(layout.layout[0].components[0].slots[0].components[1].uuid).to.eq(
      'static-image-udf7d',
    );
    const state = layoutModelSlice.reducer(
      layout,
      sortNode({
        uuid: 'static-static-card1ab',
        to: 1,
      }),
    );
    expect(state.layout[0].components[0].slots[0].components[0].uuid).to.eq(
      'static-image-udf7d',
    );
    expect(state.layout[0].components[0].slots[0].components[1].uuid).to.eq(
      'static-static-card1ab',
    );
    expect(
      state.layout[0].components[0].slots[0].components.map(
        (item) => item.uuid,
      ),
    ).to.deep.equal(['static-image-udf7d', 'static-static-card1ab']);
  });
});

describe('Shift node down', () => {
  it('Should shift node', () => {
    expect(layout.layout[0].components[0].slots[0].components[0].uuid).to.eq(
      'static-static-card1ab',
    );
    expect(layout.layout[0].components[0].slots[0].components[1].uuid).to.eq(
      'static-image-udf7d',
    );
    const state = layoutModelSlice.reducer(
      layout,
      shiftNode({
        uuid: 'static-static-card1ab',
        direction: 'down',
      }),
    );
    expect(state.layout[0].components[0].slots[0].components[0].uuid).to.eq(
      'static-image-udf7d',
    );
    expect(state.layout[0].components[0].slots[0].components[1].uuid).to.eq(
      'static-static-card1ab',
    );
    expect(
      state.layout[0].components[0].slots[0].components.map(
        (item) => item.uuid,
      ),
    ).to.deep.equal(['static-image-udf7d', 'static-static-card1ab']);
  });
});

describe('Shift node up', () => {
  it('Should shift node', () => {
    expect(layout.layout[0].components[3].uuid).to.eq(
      'static-image-static-imageStyle-something7d',
    );
    expect(layout.layout[0].components[2].uuid).to.eq('static-static-card3rr');
    const state = layoutModelSlice.reducer(
      layout,
      shiftNode({
        uuid: 'static-image-static-imageStyle-something7d',
        direction: 'up',
      }),
    );
    expect(state.layout[0].components[3].uuid).to.eq('static-static-card3rr');
    expect(state.layout[0].components[2].uuid).to.eq(
      'static-image-static-imageStyle-something7d',
    );
    expect(state.layout[0].components.map((item) => item.uuid)).to.deep.equal([
      'a7470350-deb2-4d9f-982c-464d356403d4',
      'static-static-card2df',
      'static-image-static-imageStyle-something7d',
      'static-static-card3rr',
      'ee07d472-a754-4427-b6d4-acfc6f92bbdc',
    ]);
  });
});

describe('Undo/redo', () => {
  it('Should support undo when past state exists', () => {
    const store = makeStore({
      layoutModel: { present: layout, past: [initialState], future: [] },
      ui: uiInitialState,
    });
    let state = selectLayoutHistory(store.getState());
    expect(state.present).to.eq(layout);
    expect(state.past.length).to.eq(1);
    expect(state.future.length).to.eq(0);
    store.dispatch(UndoRedoActionCreators.undo('layoutModel'));

    state = selectLayoutHistory(store.getState());
    expect(state.present).to.eq(initialState);
    expect(state.past.length).to.eq(0);
    expect(state.future.length).to.eq(1);
  });

  it('Should support redo when future state exists', () => {
    const store = makeStore({
      layoutModel: { present: layout, past: [initialState], future: [] },
      ui: uiInitialState,
    });
    let state = selectLayoutHistory(store.getState());
    expect(state.present).to.eq(layout);
    store.dispatch(UndoRedoActionCreators.undo('layoutModel'));

    state = selectLayoutHistory(store.getState());
    expect(state.present).to.eq(initialState);
    expect(state.past.length).to.eq(0);
    expect(state.future.length).to.eq(1);
    store.dispatch(UndoRedoActionCreators.redo('layoutModel'));

    state = selectLayoutHistory(store.getState());
    expect(state.present).to.deep.eq({ ...layout, updatePreview: true });
    expect(state.past.length).to.eq(1);
    expect(state.future.length).to.eq(0);
  });

  it('Should not support undo of initial load', () => {
    const store = makeStore({
      layoutModel: { present: initialState, past: [], future: [] },
      ui: uiInitialState,
    });
    let state = selectLayoutHistory(store.getState());
    expect(state.present).to.eq(initialState);
    expect(state.past.length).to.eq(0);
    expect(state.future.length).to.eq(0);
    store.dispatch(setLayoutModel(layout));
    const undoType = selectUndoType(store.getState());
    expect(undoType).to.eq('layoutModel');

    state = selectLayoutHistory(store.getState());
    expect(state.present.layout).to.deep.equal(layout.layout);
    expect(state.present.model).to.deep.equal(layout.model);
    expect(state.past.length).to.eq(0);
    expect(state.future.length).to.eq(0);
  });

  it('Should prune future state if undo type changes', () => {
    const store = makeStore({
      layoutModel: { present: layout, past: [initialState], future: [] },
      pageData: {
        present: { title: [{ value: 'Title' }] },
        past: [{}],
        future: [],
      },
      ui: uiInitialState,
    });
    let state = selectLayoutHistory(store.getState());
    expect(state.present).to.deep.equal(layout);

    state = selectLayoutHistory(store.getState());
    expect(state.past.length).to.eq(1);
    expect(state.future.length).to.eq(0);

    store.dispatch(UndoRedoActionCreators.undo('layoutModel'));
    state = selectLayoutHistory(store.getState());
    expect(state.present).to.deep.equal(initialState);
    expect(state.past.length).to.eq(0);
    expect(state.future.length).to.eq(1);

    store.dispatch(setPageData({}));
    const undoType = selectUndoType(store.getState());
    expect(undoType).to.eq('pageData');

    state = selectLayoutHistory(store.getState());
    expect(state.present).to.deep.equal(initialState);
    expect(state.past.length).to.eq(0);
    expect(state.future.length).to.eq(0);
  });
});

describe('Duplicate node', () => {
  it('Should duplicate a node correctly with a new UUID and duplicate its children nodes', () => {
    // Initialize state with a layout
    const initialStateWithLayout = layoutModelSlice.reducer(
      initialState,
      setLayoutModel({
        layout: [
          {
            nodeType: 'region',
            name: 'content',
            components: [
              {
                uuid: 'original-node',
                nodeType: 'component',
                name: 'Original Node',
                slots: [
                  {
                    id: 'original-node/child1',
                    nodeType: 'slot',
                    name: 'Slot 1',
                    components: [],
                  },
                  {
                    id: 'original-node/child2',
                    nodeType: 'slot',
                    name: 'Slot 2',
                    components: [],
                  },
                ],
              },
            ],
          },
        ],
        model: {},
        updatePreview: true,
      }),
    );

    const nodeToDuplicateUUID = 'original-node';
    const stateAfterDuplication = layoutModelSlice.reducer(
      initialStateWithLayout,
      duplicateNode({ uuid: nodeToDuplicateUUID }),
    );

    const originalNode = initialStateWithLayout.layout[0].components.find(
      (node) => node.uuid === nodeToDuplicateUUID,
    );
    const newNode = stateAfterDuplication.layout[0].components.find(
      (node) => node.uuid !== nodeToDuplicateUUID,
    );

    // Ensure the new node is a duplicate and has a different UUID
    expect(newNode).to.not.be.undefined;
    expect(newNode.uuid).to.not.equal(nodeToDuplicateUUID);
    expect(newNode.type).to.equal(originalNode.type);
    expect(newNode.nodeType).to.equal(originalNode.nodeType);
    expect(newNode.slots.length).to.equal(originalNode.slots.length);

    // Verify each child node's UUID in the new node
    originalNode.slots.forEach((originalChild, index) => {
      const newChild = newNode.slots[index];
      expect(newChild).to.not.be.undefined;
      expect(newChild.id).to.not.equal(originalChild.id);
      expect(newChild.name).to.equal(originalChild.name);
      expect(newChild.nodeType).to.equal(originalChild.nodeType);
      expect(newChild.components).to.deep.equal(originalChild.components);
    });

    // Verify the model for the new node and its children
    expect(stateAfterDuplication.model[newNode.uuid]).to.deep.equal(
      stateAfterDuplication.model[nodeToDuplicateUUID],
    );
  });
});
