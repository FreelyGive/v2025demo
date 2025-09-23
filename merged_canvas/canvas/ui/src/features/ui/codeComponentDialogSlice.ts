import { createSelector } from '@reduxjs/toolkit';

import { createAppSlice } from '@/app/createAppSlice';

import type { PayloadAction } from '@reduxjs/toolkit';
import type { CodeComponentSerialized } from '@/types/CodeComponent';

interface CodeComponentDialogState {
  selectedCodeComponent: CodeComponentSerialized | null;
  isAddDialogOpen: boolean;
  isRenameDialogOpen: boolean;
  isDeleteDialogOpen: boolean;
  isAddToComponentsDialogOpen: boolean;
  isRemoveFromComponentsDialogOpen: boolean;
  isInLayoutDialogOpen: boolean;
}

const initialState: CodeComponentDialogState = {
  selectedCodeComponent: null,
  isAddDialogOpen: false,
  isRenameDialogOpen: false,
  isDeleteDialogOpen: false,
  isAddToComponentsDialogOpen: false,
  isRemoveFromComponentsDialogOpen: false,
  isInLayoutDialogOpen: false,
};

export const codeComponentDialogSlice = createAppSlice({
  name: 'codeComponentDialog',
  initialState,
  reducers: (create) => {
    // Helper function to reset all dialog states.
    const resetDialogOpenStates = (state: CodeComponentDialogState) => {
      state.isAddDialogOpen = false;
      state.isRenameDialogOpen = false;
      state.isDeleteDialogOpen = false;
      state.isAddToComponentsDialogOpen = false;
      state.isRemoveFromComponentsDialogOpen = false;
      state.isInLayoutDialogOpen = false;
    };

    return {
      openAddDialog: create.reducer((state) => {
        resetDialogOpenStates(state);
        state.isAddDialogOpen = true;
        state.selectedCodeComponent = null;
      }),
      openRenameDialog: create.reducer(
        (state, action: PayloadAction<CodeComponentSerialized>) => {
          resetDialogOpenStates(state);
          state.isRenameDialogOpen = true;
          state.selectedCodeComponent = action.payload;
        },
      ),
      openDeleteDialog: create.reducer(
        (state, action: PayloadAction<CodeComponentSerialized>) => {
          resetDialogOpenStates(state);
          state.isDeleteDialogOpen = true;
          state.selectedCodeComponent = action.payload;
        },
      ),
      // Only for internal components.
      openAddToComponentsDialog: create.reducer(
        (state, action: PayloadAction<CodeComponentSerialized>) => {
          resetDialogOpenStates(state);
          state.isAddToComponentsDialogOpen = true;
          state.selectedCodeComponent = action.payload;
        },
      ),
      // Only for exposed components.
      openRemoveFromComponentsDialog: create.reducer(
        (state, action: PayloadAction<CodeComponentSerialized>) => {
          resetDialogOpenStates(state);
          state.isRemoveFromComponentsDialogOpen = true;
          state.selectedCodeComponent = action.payload;
        },
      ),
      // Only for exposed components.
      openInLayoutDialog: create.reducer((state) => {
        resetDialogOpenStates(state);
        state.isInLayoutDialogOpen = true;
      }),
      closeAllDialogs: create.reducer((state) => {
        resetDialogOpenStates(state);
        state.selectedCodeComponent = null;
      }),
    };
  },
  selectors: {
    selectDialogStates: createSelector(
      (state) => state.selectedCodeComponent,
      (state) => state.isAddDialogOpen,
      (state) => state.isRenameDialogOpen,
      (state) => state.isDeleteDialogOpen,
      (state) => state.isAddToComponentsDialogOpen,
      (state) => state.isRemoveFromComponentsDialogOpen,
      (state) => state.isInLayoutDialogOpen,
      (
        selectedCodeComponent,
        isAddDialogOpen,
        isRenameDialogOpen,
        isDeleteDialogOpen,
        isAddToComponentsDialogOpen,
        isRemoveFromComponentsDialogOpen,
        isInLayoutDialogOpen,
      ): CodeComponentDialogState => ({
        selectedCodeComponent,
        isAddDialogOpen,
        isRenameDialogOpen,
        isDeleteDialogOpen,
        isAddToComponentsDialogOpen,
        isRemoveFromComponentsDialogOpen,
        isInLayoutDialogOpen,
      }),
    ),
    selectSelectedCodeComponent: (state): CodeComponentSerialized | null => {
      return state.selectedCodeComponent;
    },
  },
});

export const {
  openAddDialog,
  openRenameDialog,
  openDeleteDialog,
  openAddToComponentsDialog,
  openRemoveFromComponentsDialog,
  openInLayoutDialog,
  closeAllDialogs,
} = codeComponentDialogSlice.actions;

export const { selectDialogStates, selectSelectedCodeComponent } =
  codeComponentDialogSlice.selectors;

export default codeComponentDialogSlice.reducer;
