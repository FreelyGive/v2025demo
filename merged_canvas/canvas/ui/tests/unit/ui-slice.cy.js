import {
  initialState,
  setHoveredComponent,
  uiSlice,
  unsetHoveredComponent,
} from '@/features/ui/uiSlice';

describe('Set hovered component', () => {
  it('Should set hovered component to the passed ID', () => {
    const state = uiSlice.reducer(initialState, setHoveredComponent('12345'));
    expect(state.hoveredComponent).to.eq('12345');
  });
});

describe('Unset hovered component', () => {
  it('Should set model and layout', () => {
    const state = uiSlice.reducer(initialState, unsetHoveredComponent());
    expect(state.hoveredComponent).to.eq(undefined);
  });
});
