import { v4 as uuidv4 } from 'uuid';
import { createSelector, createSlice } from '@reduxjs/toolkit';

import {
  getPropMachineName,
  serializeProps,
  serializeSlots,
} from '@/features/code-editor/utils';

import type { PayloadAction } from '@reduxjs/toolkit';
import type { RootState } from '@/app/store';
import type {
  AssetLibrary,
  CodeComponent,
  CodeComponentProp,
  CodeComponentSerialized,
  CodeComponentSlot,
} from '@/types/CodeComponent';

interface CodeEditorState {
  status: CodeEditorStatusOptions;
  codeComponent: CodeComponent;
  globalAssetLibrary: AssetLibrary;
  previewCompiledJsForSlots: string;
}

interface CodeEditorStatusOptions {
  needsAutoSave: boolean;
  needsAutoSaveOnFirstCompilation: boolean;
  compilationError: boolean;
  isCompiling: boolean;
  isSaving: boolean;
  hasUnsavedChanges: boolean;
}

export const initialState: CodeEditorState = {
  status: {
    needsAutoSave: false,
    needsAutoSaveOnFirstCompilation: false,
    compilationError: false,
    isCompiling: false,
    isSaving: false,
    hasUnsavedChanges: false,
  },
  codeComponent: {
    machineName: '',
    name: '',
    status: false,
    props: [],
    required: [],
    slots: [],
    sourceCodeJs: '',
    sourceCodeCss: '',
    compiledJs: '',
    compiledCss: '',
    importedJsComponents: [],
    dataFetches: {},
    dataDependencies: {},
  },
  globalAssetLibrary: {
    id: 'global',
    label: 'Global',
    css: {
      original: '',
      compiled: '',
    },
    js: {
      original: '',
      compiled: '',
    },
  },
  previewCompiledJsForSlots: '',
};

export const codeEditorSlice = createSlice({
  name: 'codeEditor',
  initialState,

  reducers: (create) => ({
    initializeCodeEditor: create.reducer(
      (
        state,
        action: PayloadAction<{
          codeComponent: CodeComponent;
          globalAssetLibrary: AssetLibrary;
          status?: Partial<CodeEditorStatusOptions>;
        }>,
      ) => ({
        // Note that we're starting from the initial state, has `needsAutoSave`
        // set to false.
        // The `setCodeComponent` and `setGlobalAssetLibrary` actions will set
        // `needsAutoSave` to true by default.
        ...initialState,
        codeComponent: {
          ...initialState.codeComponent,
          ...action.payload.codeComponent,
          // Do not use previously compiled code. It will be re-compiled.
          compiledCss: '',
          compiledJs: '',
        },
        globalAssetLibrary: {
          ...initialState.globalAssetLibrary,
          ...action.payload.globalAssetLibrary,
          css: {
            ...initialState.globalAssetLibrary.css,
            ...action.payload.globalAssetLibrary.css,
            // Do not use previously compiled CSS. It will be re-compiled.
            compiled: '',
          },
        },
        status: {
          ...initialState.status,
          ...action.payload.status,
        },
      }),
    ),

    resetCodeEditor: create.reducer(() => initialState),

    setStatus: create.reducer(
      (state, action: PayloadAction<Partial<CodeEditorStatusOptions>>) => ({
        ...state,
        status: { ...state.status, ...action.payload },
      }),
    ),

    /**
     * Sets a property of the code component and sets the `needsAutoSave` status
     * to true by default.
     *
     * @param [0] - The property to set.
     * @param [1] - The value to set.
     * @param [2] - (optional) A partial status object to override the existing status.
     *   By default, auto-save will be set to true.
     */
    setCodeComponentProperty: create.reducer(
      (
        state,
        action: PayloadAction<
          | [keyof CodeComponent, CodeComponent[keyof CodeComponent]]
          | [
              keyof CodeComponent,
              CodeComponent[keyof CodeComponent],
              Partial<CodeEditorStatusOptions>,
            ]
        >,
      ) => ({
        ...state,
        codeComponent: {
          ...state.codeComponent,
          [action.payload[0]]: action.payload[1],
        },
        status: {
          ...state.status, // Use the existing status.
          needsAutoSave: true, // Override auto-save to true.
          ...(action.payload[2] && action.payload[2]), // Override with the new status if provided.
        },
      }),
    ),

    addProp: (state) => {
      state.codeComponent.props.push({
        id: uuidv4(),
        name: '',
        type: 'string',
        example: '',
        format: undefined,
        $ref: undefined,
        derivedType: 'text',
      });
    },
    updateProp: (
      state,
      action: PayloadAction<{
        id: string;
        updates: Partial<CodeComponentProp>;
      }>,
    ) => {
      const { id, updates } = action.payload;
      const propIndex = state.codeComponent.props.findIndex((p) => p.id === id);
      if (propIndex !== -1) {
        const currentProp = state.codeComponent.props[propIndex];
        state.codeComponent.props[propIndex] = {
          ...currentProp,
          ...updates,
        } as CodeComponentProp;
        // Set auto-save to true when updating a prop.
        state.status.needsAutoSave = true;
      }
    },

    removeProp: (
      state,
      action: PayloadAction<{
        propId: string;
      }>,
    ) => {
      const { propId } = action.payload;
      const propToRemove = state.codeComponent.props.find(
        (prop) => prop.id === propId,
      );
      state.codeComponent.props = state.codeComponent.props.filter(
        (prop) => prop.id !== propId,
      );
      if (propToRemove) {
        state.codeComponent.required = state.codeComponent.required.filter(
          (name) => name !== getPropMachineName(propToRemove.name),
        );
        // Set auto-save to true when removing a prop.
        state.status.needsAutoSave = true;
      }
    },

    reorderProps: (
      state,
      action: PayloadAction<{
        oldIndex: number;
        newIndex: number;
      }>,
    ) => {
      const { oldIndex, newIndex } = action.payload;
      const props = state.codeComponent.props;
      const [removed] = props.splice(oldIndex, 1);
      props.splice(newIndex, 0, removed);
      // Set auto-save to true when reordering a prop.
      state.status.needsAutoSave = true;
    },

    toggleRequired: (
      state,
      action: PayloadAction<{
        propId: string;
      }>,
    ) => {
      const { propId } = action.payload;
      const prop = state.codeComponent.props.find((p) => p.id === propId);
      if (!prop) return;

      const propName = getPropMachineName(prop.name);
      if (state.codeComponent.required.includes(propName)) {
        state.codeComponent.required = state.codeComponent.required.filter(
          (name) => name !== propName,
        );
      } else {
        state.codeComponent.required.push(propName);
      }
      // Set auto-save to true when toggling required.
      state.status.needsAutoSave = true;
    },

    addSlot: (state) => {
      state.codeComponent.slots.push({
        id: uuidv4(),
        name: '',
        example: '',
      });
    },

    updateSlot: (
      state,
      action: PayloadAction<{
        id: string;
        updates: Partial<CodeComponentSlot>;
      }>,
    ) => {
      const { id, updates } = action.payload;
      const slotIndex = state.codeComponent.slots.findIndex((s) => s.id === id);
      if (slotIndex !== -1) {
        const currentSlot = state.codeComponent.slots[slotIndex];
        state.codeComponent.slots[slotIndex] = {
          ...currentSlot,
          ...updates,
        } as CodeComponentSlot;
        // Set auto-save to true when updating a slot.
        state.status.needsAutoSave = true;
      }
    },

    removeSlot: (
      state,
      action: PayloadAction<{
        slotId: string;
      }>,
    ) => {
      const { slotId } = action.payload;
      state.codeComponent.slots = state.codeComponent.slots.filter(
        (slot) => slot.id !== slotId,
      );
      // Set auto-save to true when removing a slot.
      state.status.needsAutoSave = true;
    },

    reorderSlots: (
      state,
      action: PayloadAction<{
        oldIndex: number;
        newIndex: number;
      }>,
    ) => {
      const { oldIndex, newIndex } = action.payload;
      const slots = state.codeComponent.slots;
      const [removed] = slots.splice(oldIndex, 1);
      slots.splice(newIndex, 0, removed);
      // Set auto-save to true when reordering a slot.
      state.status.needsAutoSave = true;
    },

    /**
     * Sets a property of the global asset library and sets the `needsAutoSave`
     * status to true by default.
     *
     * @param [0] - The type of asset to set: 'css' or 'js'.
     * @param [1] - The property to set: 'original' or 'compiled'.
     * @param [2] - The value to set.
     * @param [3] - (optional) A partial status object to override the existing status.
     *   By default, auto-save will be set to true.
     */
    setGlobalAssetLibraryProperty: create.reducer(
      (
        state,
        action: PayloadAction<
          | [
              keyof Pick<AssetLibrary, 'css' | 'js'>,
              keyof AssetLibrary['css'] | keyof AssetLibrary['js'],
              string,
            ]
          | [
              keyof Pick<AssetLibrary, 'css' | 'js'>,
              keyof AssetLibrary['css'] | keyof AssetLibrary['js'],
              string,
              Partial<CodeEditorStatusOptions>,
            ]
        >,
      ) => ({
        ...state,
        globalAssetLibrary: {
          ...state.globalAssetLibrary,
          [action.payload[0]]: {
            ...state.globalAssetLibrary[action.payload[0]],
            [action.payload[1]]: action.payload[2],
          },
        },
        status: {
          ...state.status, // Use the existing status.
          needsAutoSave: true, // Override auto-save to true.
          ...(action.payload[3] && action.payload[3]), // Override with the new status if provided.
        },
      }),
    ),

    setPreviewCompiledJsForSlots: create.reducer(
      (state, action: PayloadAction<string>) => ({
        ...state,
        previewCompiledJsForSlots: action.payload,
      }),
    ),

    addDataFetch: (
      state,
      action: PayloadAction<{ id: string; data: any; error: boolean }>,
    ) => {
      state.codeComponent.dataFetches[action.payload.id] = action.payload;
    },

    clearDataFetches: (state) => {
      state.codeComponent.dataFetches = {};
    },
  }),
});

export const selectStatus = (state: RootState) => state.codeEditor.status;

// Select the entire code component (deserialized), or a specific property.
export const selectCodeComponent = <
  K extends keyof CodeComponent | undefined = undefined,
>(
  state: RootState,
  property?: K,
): K extends keyof CodeComponent ? CodeComponent[K] : CodeComponent => {
  if (property) {
    return state.codeEditor.codeComponent[
      property
    ] as K extends keyof CodeComponent ? CodeComponent[K] : CodeComponent;
  }
  return state.codeEditor.codeComponent as K extends keyof CodeComponent
    ? CodeComponent[K]
    : CodeComponent;
};

// Curried selector to select a specific property of the code component.
export const selectCodeComponentProperty =
  <K extends keyof CodeComponent>(property: K) =>
  (state: RootState) =>
    selectCodeComponent(state, property);

export const selectCodeComponentSerialized = createSelector(
  [(state: RootState) => selectCodeComponent(state)],
  (codeComponent): CodeComponentSerialized => ({
    machineName: codeComponent.machineName,
    name: codeComponent.name,
    status: codeComponent.status,
    props: serializeProps(codeComponent.props),
    required: codeComponent.required,
    slots: serializeSlots(codeComponent.slots),
    sourceCodeJs: codeComponent.sourceCodeJs,
    sourceCodeCss: codeComponent.sourceCodeCss,
    compiledJs: codeComponent.compiledJs,
    compiledCss: codeComponent.compiledCss,
    importedJsComponents: codeComponent.importedJsComponents,
    dataFetches: codeComponent.dataFetches,
    dataDependencies: codeComponent.dataDependencies,
  }),
);

// Select the entire global asset library, or a specific property.
export const selectGlobalAssetLibrary = <T = AssetLibrary>(
  state: RootState,
  properties?: ['css' | 'js', 'original' | 'compiled'] | 'css' | 'js',
): T => {
  if (!properties) {
    return state.codeEditor.globalAssetLibrary as T;
  }

  if (typeof properties === 'string') {
    return state.codeEditor.globalAssetLibrary[properties] as T;
  }
  if (state.codeEditor.globalAssetLibrary[properties[0]] === null) {
    return '' as T;
  }

  return (state.codeEditor.globalAssetLibrary[properties[0]][properties[1]] ??
    '') as T;
};

// Curried selector to select a specific CSS or JS property of the global asset
// library.
export const selectGlobalAssetLibraryProperty =
  (properties: ['css' | 'js', 'original' | 'compiled']) => (state: RootState) =>
    selectGlobalAssetLibrary<
      AssetLibrary[(typeof properties)[0]][(typeof properties)[1]]
    >(state, properties);

export const selectPreviewCompiledJsForSlots = (state: RootState) =>
  state.codeEditor.previewCompiledJsForSlots;

export const {
  initializeCodeEditor,
  resetCodeEditor,
  setStatus,
  setCodeComponentProperty,
  setGlobalAssetLibraryProperty,
  addProp,
  updateProp,
  removeProp,
  reorderProps,
  toggleRequired,
  addSlot,
  updateSlot,
  removeSlot,
  reorderSlots,
  setPreviewCompiledJsForSlots,
  addDataFetch,
  clearDataFetches,
} = codeEditorSlice.actions;

export default codeEditorSlice;
