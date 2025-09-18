import { makeStore } from '@/app/store';
import {
  pageDataSlice,
  selectPageDataHistory,
  setPageData,
} from '@/features/pageData/pageDataSlice';
import {
  initialState,
  pushUndo,
  selectUndoType,
  UndoRedoActionCreators,
} from '@/features/ui/uiSlice';

let pageData = {
  title: [{ value: 'Some title' }],
};

describe('Set page state', () => {
  it('Should set page state', () => {
    const state = pageDataSlice.reducer({}, setPageData(pageData));
    expect(state).to.deep.equal(pageData);
  });
});

describe('Undo/redo', () => {
  it('Should support undo when past state exists', () => {
    const store = makeStore({
      pageData: { present: pageData, past: [{}], future: [] },
      ui: initialState,
    });
    let state = selectPageDataHistory(store.getState());
    expect(state.present).to.deep.equal(pageData);
    cy.wrap(state.past).should('have.length', 1);
    cy.wrap(state.future).should('have.length', 0);
    store.dispatch(UndoRedoActionCreators.undo('pageData'));

    state = selectPageDataHistory(store.getState());
    expect(state.present).to.deep.equal({});
    cy.wrap(state.past).should('have.length', 0);
    cy.wrap(state.future).should('have.length', 1);
  });

  it('Should support redo when future state exists', () => {
    const store = makeStore({
      pageData: { present: pageData, past: [{}], future: [] },
      ui: initialState,
    });
    let state = selectPageDataHistory(store.getState());
    expect(state.present).to.deep.equal(pageData);
    store.dispatch(UndoRedoActionCreators.undo('pageData'));

    state = selectPageDataHistory(store.getState());
    expect(state.present).to.deep.equal({});
    cy.wrap(state.past).should('have.length', 0);
    cy.wrap(state.future).should('have.length', 1);
    store.dispatch(UndoRedoActionCreators.redo('pageData'));

    state = selectPageDataHistory(store.getState());
    expect(state.present).to.deep.equal(pageData);
    cy.wrap(state.past).should('have.length', 1);
    cy.wrap(state.future).should('have.length', 0);
  });

  it('Should not support undo of initial load', () => {
    const store = makeStore({
      pageData: { present: {}, past: [], future: [] },
      ui: initialState,
    });
    let state = selectPageDataHistory(store.getState());
    expect(state.present).to.deep.equal({});
    cy.wrap(state.past).should('have.length', 0);
    cy.wrap(state.future).should('have.length', 0);
    store.dispatch(setPageData(pageData));

    state = selectPageDataHistory(store.getState());
    expect(state.present).to.deep.equal(pageData);
    cy.wrap(state.past).should('have.length', 0);
    cy.wrap(state.future).should('have.length', 0);
  });

  it('Should prune future state if undo type changes', () => {
    const store = makeStore({
      pageData: { present: pageData, past: [], future: [] },
      ui: initialState,
    });
    const newState = {
      ...pageData,
      published: [{ value: true }],
    };
    let state = selectPageDataHistory(store.getState());
    expect(state.present).to.deep.equal(pageData);
    store.dispatch(setPageData(newState));

    state = selectPageDataHistory(store.getState());
    cy.wrap(state.past).should('have.length', 1);
    cy.wrap(state.future).should('have.length', 0);

    store.dispatch(UndoRedoActionCreators.undo('pageData'));
    state = selectPageDataHistory(store.getState());
    expect(state.present).to.deep.equal(pageData);
    cy.wrap(state.past).should('have.length', 0);
    cy.wrap(state.future).should('have.length', 1);

    store.dispatch(pushUndo('layoutModel'));
    const undoRedoType = selectUndoType(store.getState());
    expect(undoRedoType).to.eq('layoutModel');

    state = selectPageDataHistory(store.getState());
    expect(state.present).to.deep.equal(pageData);
    cy.wrap(state.past).should('have.length', 0);
    cy.wrap(state.future).should('have.length', 0);
  });
});
