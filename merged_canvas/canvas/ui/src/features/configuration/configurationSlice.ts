import { createSlice } from '@reduxjs/toolkit';

import type { PayloadAction } from '@reduxjs/toolkit';
import type { RootState } from '@/app/store';
import type { StagedConfig } from '@/types/Config';

export interface AppConfiguration {
  baseUrl: string;
  entityType: string;
  entity: string;
  isNew: boolean;
  isPublished: boolean;
  devMode: boolean;
  homepagePath?: string;
  homepageStagedConfigExists?: boolean;
}

export const initialState: AppConfiguration = {
  baseUrl: '/',
  entityType: 'none',
  entity: 'none',
  isNew: true,
  isPublished: false,
  devMode: false,
  homepageStagedConfigExists: false,
};

export const configurationSlice = createSlice({
  name: 'configuration',
  initialState,
  reducers: (create) => ({
    setConfiguration: create.reducer(
      (state, action: PayloadAction<AppConfiguration>) => ({
        ...state,
        ...action.payload,
      }),
    ),
    setHomepagePath: create.reducer((state, action: PayloadAction<string>) => {
      state.homepagePath = action.payload;
    }),
    setHomepageStagedConfigExists: create.reducer(
      (state, action: PayloadAction<boolean>) => {
        state.homepageStagedConfigExists = action.payload;
      },
    ),
  }),
});

export const {
  setConfiguration,
  setHomepagePath,
  setHomepageStagedConfigExists,
} = configurationSlice.actions;

export default configurationSlice.reducer;
export const selectBaseUrl = (state: RootState) => state.configuration.baseUrl;

export const selectEntityType = (state: RootState) =>
  state.configuration.entityType;
export const selectEntityId = (state: RootState) => state.configuration.entity;

export const selectDevMode = (state: RootState) => state.configuration.devMode;
export const selectHomepagePath = (state: RootState) =>
  state.configuration.homepagePath;

export const selectHomepageStagedConfigExists = (state: RootState) =>
  state.configuration.homepageStagedConfigExists;

// Helper to extract homepage path from staged config structure.
export const extractHomepagePathFromStagedConfig = (
  config: StagedConfig,
): string => {
  return config.data.actions[0].input['page.front'];
};
