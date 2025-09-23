import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render } from '@testing-library/react';
import AppWrapper from '@tests/vitest/components/AppWrapper';

import { makeStore } from '@/app/store';
import Layout from '@/features/layout/Layout';
import { useGetPageLayoutQuery } from '@/services/componentAndLayout';

import type { AppStore } from '@/app/store';

vi.mock('@/services/componentAndLayout', async () => {
  const originalModule = await vi.importActual('@/services/componentAndLayout');
  return {
    ...originalModule,
    useGetPageLayoutQuery: vi.fn(),
  };
});

describe('Layout', () => {
  let store: AppStore;

  beforeEach(() => {
    store = makeStore({});

    (useGetPageLayoutQuery as ReturnType<typeof vi.fn>).mockReturnValue({
      data: {
        layout: [],
        model: {},
      },
    });
  });

  it('layout does not get re-initialized', async () => {
    const dispatchSpy = vi.spyOn(store, 'dispatch');

    render(
      <AppWrapper store={store} location="/editor" path="/editor">
        <Layout />
      </AppWrapper>,
    );
    expect(dispatchSpy).toHaveBeenCalledWith(
      expect.objectContaining({
        type: expect.stringContaining('layoutModel/setInitialLayoutModel'),
      }),
    );

    // Re-render the component.
    dispatchSpy.mockClear();
    render(
      <AppWrapper store={store} location="/editor" path="/editor">
        <Layout />
      </AppWrapper>,
    );
    // The dispatch to set the initial layout model should not have been called.
    expect(dispatchSpy).not.toHaveBeenCalledWith(
      expect.objectContaining({
        type: expect.stringContaining('layoutModel/setInitialLayoutModel'),
      }),
    );
  });
});
