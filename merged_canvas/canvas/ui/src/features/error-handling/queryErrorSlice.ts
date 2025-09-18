import { createSlice } from '@reduxjs/toolkit';

import type { PayloadAction } from '@reduxjs/toolkit';

export interface queryError {
  status: string;
  errors?: any;
  message: string;
}

export interface queryErrorSliceState {
  latestError: queryError | undefined;
}

export const initialState: queryErrorSliceState = {
  latestError: undefined,
};

export const queryErrorSlice = createSlice({
  name: 'queryError',
  initialState,
  reducers: (create) => ({
    setLatestError: create.reducer(
      (state, action: PayloadAction<queryError>) => {
        state.latestError = action.payload;
      },
    ),
  }),
  selectors: {
    selectLatestError: (state): queryError | undefined => {
      return state.latestError;
    },
  },
});
export const { setLatestError } = queryErrorSlice.actions;
export const { selectLatestError } = queryErrorSlice.selectors;
