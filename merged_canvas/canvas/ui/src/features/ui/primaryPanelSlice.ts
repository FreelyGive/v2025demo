import { createAppSlice } from '@/app/createAppSlice';

import type { PayloadAction } from '@reduxjs/toolkit';

const PANEL_STATE_KEY = 'canvas_panel_state';

// Try to load initial state from localStorage.
const loadSavedState = (): Partial<PrimaryPanelState> => {
  try {
    const saved = localStorage.getItem(PANEL_STATE_KEY);
    if (saved) {
      return JSON.parse(saved);
    }
  } catch (e) {
    console.warn('Failed to load panel state from localStorage:', e);
  }
  return {};
};

export interface PrimaryPanelState {
  activePanel: string;
  isHidden: boolean;
  aiPanelOpen: boolean;
}

export enum LayoutItemType {
  PATTERN = 'pattern',
  COMPONENT = 'component',
  DYNAMIC = 'dynamicComponent',
  CODE = 'code',
  UNDEFINED = 'undefined',
}

const savedState = loadSavedState();

const initialState: PrimaryPanelState = {
  activePanel: savedState.activePanel || 'layers',
  isHidden: false,
  aiPanelOpen: false,
};

// Temporary workaround to persist primary panel state when switching between entities until
// we can use routing without full page reloads.
const saveToLocalStorage = (state: PrimaryPanelState) => {
  try {
    const dataToSave = {
      activePanel: state.activePanel,
    };
    localStorage.setItem(PANEL_STATE_KEY, JSON.stringify(dataToSave));
  } catch (e) {
    console.warn('Failed to save panel state to localStorage:', e);
  }
};

export const primaryPanelSlice = createAppSlice({
  name: 'primaryPanel',
  // `createSlice` will infer the state type from the `initialState` argument
  initialState,
  // The `reducers` field lets us define reducers and generate associated actions
  reducers: (create) => ({
    setActivePanel: create.reducer((state, action: PayloadAction<string>) => {
      state.activePanel = action.payload;
      saveToLocalStorage(state);
    }),
    unsetActivePanel: create.reducer((state) => {
      state.activePanel = '';
      saveToLocalStorage(state);
    }),
    setAiPanelOpen: create.reducer((state) => {
      state.aiPanelOpen = true;
    }),
    setAiPanelClosed: create.reducer((state) => {
      state.aiPanelOpen = false;
    }),
  }),
  selectors: {
    selectActivePanel: (primaryPanel): string => {
      return primaryPanel.activePanel;
    },
    selectAiPanelOpen: (primaryPanel): boolean => {
      return primaryPanel.aiPanelOpen;
    },
  },
});

// Action creators are generated for each case reducer function.
export const {
  setActivePanel,
  unsetActivePanel,
  setAiPanelOpen,
  setAiPanelClosed,
} = primaryPanelSlice.actions;

// Selectors returned by `slice.selectors` take the root state as their first argument.
export const { selectActivePanel, selectAiPanelOpen } =
  primaryPanelSlice.selectors;
