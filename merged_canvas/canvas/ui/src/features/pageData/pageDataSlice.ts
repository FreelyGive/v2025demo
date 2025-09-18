import { createSlice } from '@reduxjs/toolkit';

import type { PayloadAction } from '@reduxjs/toolkit';
import type { StateWithHistory } from 'redux-undo';

interface Values {
  [key: string]: any;
}

export interface PageDataState extends Values {}

const initialState: PageDataState = {};

export interface StateWithHistoryWrapper {
  pageData: StateWithHistory<PageDataState>;
}

export const pageDataSlice = createSlice({
  name: 'pageData',
  initialState,
  reducers: (create) => ({
    setPageData: create.reducer((state, action: PayloadAction<Values>) => {
      return {
        ...state,
        ...action.payload,
      };
    }),
    // Identical to setPageData but just with a different type for ensuring this
    // doesn't trigger an undo/redo action.
    setInitialPageData: create.reducer(
      (state, action: PayloadAction<Values>) => {
        return {
          ...state,
          ...action.payload,
        };
      },
    ),
    externalUpdateComplete: create.reducer(
      (state, action: PayloadAction<string>) => {
        const { externalUpdates } = state;
        if (externalUpdates && action.payload) {
          const updatedExternalUpdates = externalUpdates.filter(
            (field: string) => {
              return action.payload !== field;
            },
          );
          return {
            ...state,
            externalUpdates: updatedExternalUpdates,
          };
        }
        return state;
      },
    ),
    updatePageDataExternally: create.reducer(
      (state, action: PayloadAction<Values>) => {
        const externalUpdates = state?.externalUpdates || [];
        return {
          ...state,
          ...action.payload,
          externalUpdates: [...externalUpdates, ...Object.keys(action.payload)],
        };
      },
    ),
  }),
});

export const {
  setPageData,
  setInitialPageData,
  updatePageDataExternally,
  externalUpdateComplete,
} = pageDataSlice.actions;

export const pageDataReducer = pageDataSlice.reducer;

export const selectPageData = (state: StateWithHistoryWrapper) =>
  state.pageData.present;

export const selectPageDataHistory = (state: StateWithHistoryWrapper) =>
  state.pageData;
