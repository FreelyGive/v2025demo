import { createAppSlice } from '@/app/createAppSlice';

import type { PayloadAction } from '@reduxjs/toolkit';
import type { ActiveExtension, ExtensionDefinition } from '@/types/Extensions';

export interface ExtensionsSliceState {
  activeExtension: ActiveExtension;
}

const initialState: ExtensionsSliceState = {
  activeExtension: null,
};

type UpdateExtensionsPayload = ExtensionDefinition;

export const extensionsSlice = createAppSlice({
  name: 'extensions',
  initialState,
  reducers: (create) => ({
    setActiveExtension: create.reducer(
      (state, action: PayloadAction<UpdateExtensionsPayload>) => {
        state.activeExtension = action.payload;
      },
    ),
    unsetActiveExtension: create.reducer((state) => {
      state.activeExtension = null;
    }),
  }),
  selectors: {
    selectActiveExtension: (state): ActiveExtension => {
      return state.activeExtension;
    },
  },
});

export const extensionsReducer = extensionsSlice.reducer;

export const { setActiveExtension, unsetActiveExtension } =
  extensionsSlice.actions;
export const { selectActiveExtension } = extensionsSlice.selectors;
