import undoable from 'redux-undo';
import { v4 as uuidv4 } from 'uuid';
import { combineSlices, configureStore } from '@reduxjs/toolkit';
import { setupListeners } from '@reduxjs/toolkit/query';

import { publishReviewSlice } from '@/components/review/PublishReview.slice';
import codeEditorSlice from '@/features/code-editor/codeEditorSlice';
import { configurationSlice } from '@/features/configuration/configurationSlice';
import { queryErrorSlice } from '@/features/error-handling/queryErrorSlice';
import { extensionsSlice } from '@/features/extensions/extensionsSlice';
import { formStateSlice } from '@/features/form/formStateSlice';
import {
  layoutModelReducer,
  setInitialLayoutModel,
  setUpdatePreview,
} from '@/features/layout/layoutModelSlice';
import {
  pageDataReducer,
  setInitialPageData,
} from '@/features/pageData/pageDataSlice';
import { previewSlice } from '@/features/pagePreview/previewSlice';
import { personalizationSlice } from '@/features/personalization/personalizationSlice';
import { codeComponentDialogSlice } from '@/features/ui/codeComponentDialogSlice';
import { dialogSlice } from '@/features/ui/dialogSlice';
import { primaryPanelSlice } from '@/features/ui/primaryPanelSlice';
import {
  performUndoOrRedo,
  pushUndo,
  setLatestUndoRedoActionId,
  uiSlice,
} from '@/features/ui/uiSlice';
import { assetLibraryApi } from '@/services/assetLibrary';
import { componentAndLayoutApi } from '@/services/componentAndLayout';
import { componentInstanceFormApi } from '@/services/componentInstanceForm';
import { contentApi } from '@/services/content';
import { pageDataFormApi } from '@/services/pageDataForm';
import { patternApi } from '@/services/patterns';
import { pendingChangesApi } from '@/services/pendingChangesApi';
import { personalizationApi } from '@/services/personalization';
import { previewApi } from '@/services/preview';
import { rtkQueryErrorHandler } from '@/utils/rtkQuery-error';

import type { Action, Middleware, ThunkAction } from '@reduxjs/toolkit';
import type { UnknownAction } from 'redux';
import type { LayoutModelSliceState } from '@/features/layout/layoutModelSlice';
import type { PageDataState } from '@/features/pageData/pageDataSlice';
import type { UndoRedoType } from '@/features/ui/uiSlice';

// Reducer enhancer to decorate undoable aware reducers and unset future state
// if an action is performed on another undoable slice.
// @see https://redux.js.org/usage/implementing-undo-history#meet-reducer-enhancers
// @see https://en.wikipedia.org/wiki/History_Eraser
const historyEraser = <T>(
  reducer: any,
  thisType: UndoRedoType,
  forceStateOnUndoRedo: Partial<T> = {},
) => {
  return (state: any, action: UnknownAction, ...slices: any[]) => {
    // Pass through to the inner (undoable) reducer.
    const newState = reducer(state, action, ...slices);
    const type = action.type;
    if (
      type === 'ui/pushUndo' &&
      action.payload !== undefined &&
      action.payload !== thisType &&
      newState.future.length > 0
    ) {
      // Discard the future (redo) states for this slice as we've moved into a
      // future state for another slice.
      // E.g. If this reducer is applied to the 'pageData' slice, but we've
      // pushed 'layoutModel' to the undo stack, then any future (redo) state
      // for this slice is no longer valid.
      // Without this historyEraser, slices would retain their future state when
      // they are not needed or reachable.
      return { ...newState, future: [] };
    }
    if (
      !type.startsWith(`${thisType}/`) &&
      !type.startsWith(`@@redux-undo/${thisType}`)
    ) {
      return newState;
    }
    // For actions in this slice we want to force a certain undo/redo state.
    return {
      ...newState,
      past: newState.past.map((i: T) => ({ ...i, ...forceStateOnUndoRedo })),
      future: newState.future.map((i: T) => ({
        ...i,
        ...forceStateOnUndoRedo,
      })),
    };
  };
};

// `combineSlices` automatically combines the reducers using
// their `reducerPath`s, therefore we no longer need to call `combineReducers`.
const rootReducer = combineSlices(
  {
    layoutModel: historyEraser<LayoutModelSliceState>(
      undoable(layoutModelReducer, {
        undoType: '@@redux-undo/layoutModel_UNDO',
        redoType: '@@redux-undo/layoutModel_REDO',
        filter: (action, currentState, previousHistory) => {
          const { present } = previousHistory;
          return (
            Object.keys(present.model).length > 0 &&
            action.type !== setInitialLayoutModel.type &&
            action.type !== setUpdatePreview.type
          );
        },
      }),
      'layoutModel',
      // We want any redo/undo actions to trigger a preview update from the
      // server.
      { updatePreview: true },
    ),
    pageData: historyEraser<PageDataState>(
      undoable(pageDataReducer, {
        undoType: '@@redux-undo/pageData_UNDO',
        redoType: '@@redux-undo/pageData_REDO',
        filter: (action, currentState, previousHistory) => {
          const { present } = previousHistory;
          return (
            Object.keys(present).length > 0 &&
            action.type !== setInitialPageData.type
          );
        },
      }),
      'pageData',
    ),
  },
  patternApi,
  assetLibraryApi,
  personalizationApi,
  componentAndLayoutApi,
  previewApi,
  componentInstanceFormApi,
  pageDataFormApi,
  configurationSlice,
  primaryPanelSlice,
  dialogSlice,
  codeComponentDialogSlice,
  uiSlice,
  formStateSlice,
  extensionsSlice,
  pendingChangesApi,
  publishReviewSlice,
  contentApi,
  codeEditorSlice,
  previewSlice,
  queryErrorSlice,
  personalizationSlice,
);
// Infer the `RootState` type from the root reducer
export type RootState = ReturnType<typeof rootReducer>;

// Middleware to add unique ID to undo/redo actions and store it.
const undoRedoActionIdMiddleware: Middleware<{}, RootState> =
  (store) => (next) => (action) => {
    const type = (action as Action).type;
    // If the action being performed is an UNDO or REDO action we need to move
    // items between the undo and redo stacks.
    const matches = type.match(/@@redux-undo\/[^_]+_(UNDO|REDO)/);
    if (matches && matches.length === 2) {
      const id = uuidv4();
      const [, undoOrRedo] = matches;
      store.dispatch(performUndoOrRedo(undoOrRedo === 'UNDO'));
      store.dispatch(setLatestUndoRedoActionId(id));
      return next({
        ...(action as Action),
        meta: {
          id,
        },
      });
    }
    if (
      type === setUpdatePreview.type ||
      type === setInitialLayoutModel.type ||
      type === setInitialPageData.type
    ) {
      // Ignore initial actions that set the state of the model or page data
      // from the return of API responses. The user should not be able to undo
      // or redo these actions.
      return next(action);
    }
    const [slice] = type.split('/');
    if (slice === 'layoutModel' || slice === 'pageData') {
      store.dispatch(pushUndo(slice as UndoRedoType));
    }
    return next(action);
  };

// The store setup is wrapped in `makeStore` to allow reuse
// when setting up tests that need the same store config
export const makeStore = (preloadedState?: Partial<RootState>) => {
  const store = configureStore({
    reducer: rootReducer,
    // Adding the api middleware enables caching, invalidation, polling,
    // and other useful features of `rtk-query`.
    middleware: (getDefaultMiddleware) => {
      return getDefaultMiddleware().concat(
        patternApi.middleware,
        assetLibraryApi.middleware,
        personalizationApi.middleware,
        componentAndLayoutApi.middleware,
        previewApi.middleware,
        componentInstanceFormApi.middleware,
        pageDataFormApi.middleware,
        undoRedoActionIdMiddleware,
        pendingChangesApi.middleware,
        contentApi.middleware,
        rtkQueryErrorHandler, // Add the error handling middleware
      );
    },
    preloadedState,
  });
  // configure listeners using the provided defaults
  // optional, but required for `refetchOnFocus`/`refetchOnReconnect` behaviors
  setupListeners(store.dispatch);
  return store;
};

// Infer the type of `store`
export type AppStore = ReturnType<typeof makeStore>;
// Infer the `AppDispatch` type from the store itself
export type AppDispatch = AppStore['dispatch'];
export type AppThunk<ThunkReturnType = void> = ThunkAction<
  ThunkReturnType,
  RootState,
  unknown,
  Action
>;
