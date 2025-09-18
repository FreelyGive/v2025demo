import { createAppSlice } from '@/app/createAppSlice';

import type { PayloadAction } from '@reduxjs/toolkit';
import type { Pattern } from '@/types/Pattern';

export interface DialogSliceState {
  saveAsPattern: boolean;
  extension: boolean;
  deletePatternConfirm: {
    open: boolean;
    data: Pattern | {};
  };
}

const initialState: DialogSliceState = {
  saveAsPattern: false,
  extension: false,
  deletePatternConfirm: {
    open: false,
    data: {},
  },
};

type UpdateDialogPayload = keyof Omit<DialogSliceState, 'deletePatternConfirm'>;

type UpdateDialogWithDataPayload = {
  operation: keyof Omit<DialogSliceState, UpdateDialogPayload>;
  data: any;
};

export const dialogSlice = createAppSlice({
  name: 'dialog',
  initialState,
  reducers: (create) => ({
    setDialogOpen: create.reducer(
      (state, action: PayloadAction<UpdateDialogPayload>) => {
        state[action.payload] = true;
      },
    ),
    setDialogClosed: create.reducer(
      (state, action: PayloadAction<UpdateDialogPayload>) => {
        state[action.payload] = false;
      },
    ),
    setDialogWithDataOpen: create.reducer(
      (state, action: PayloadAction<UpdateDialogWithDataPayload>) => {
        state[action.payload.operation] = {
          open: true,
          data: action.payload.data,
        };
      },
    ),
    setDialogWithDataClosed: create.reducer(
      (
        state,
        action: PayloadAction<
          keyof Omit<DialogSliceState, UpdateDialogPayload>
        >,
      ) => {
        state[action.payload] = {
          open: false,
          data: {},
        };
      },
    ),
  }),
  selectors: {
    selectDialogOpen: (dialog): DialogSliceState => {
      return dialog;
    },
  },
});

export const {
  setDialogOpen,
  setDialogClosed,
  setDialogWithDataOpen,
  setDialogWithDataClosed,
} = dialogSlice.actions;
export const { selectDialogOpen } = dialogSlice.selectors;
