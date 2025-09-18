import { createSlice } from '@reduxjs/toolkit';

import type { PayloadAction } from '@reduxjs/toolkit';
import type { RootState } from '@/app/store';

interface SegmentsState {
  loading: boolean;
  error: string | null;
}

const initialState: SegmentsState = {
  loading: false,
  error: null,
};

const personalizationSlice = createSlice({
  name: 'segments',
  initialState,
  reducers: {
    setLoading: (state, action: PayloadAction<boolean>) => {
      state.loading = action.payload;
    },
    setError: (state, action: PayloadAction<string | null>) => {
      state.error = action.payload;
      state.loading = false;
    },
    clearError: (state) => {
      state.error = null;
    },
  },
});

export const { setLoading, setError, clearError } =
  personalizationSlice.actions;

// Selectors
export const selectSegmentsLoading = (state: RootState) =>
  state.segments.loading;
export const selectSegmentsError = (state: RootState) => state.segments.error;

export { personalizationSlice };
export default personalizationSlice.reducer;
