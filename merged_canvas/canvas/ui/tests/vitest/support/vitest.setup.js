import { vi } from 'vitest';

const mockDrupalSettings = {
  path: {
    baseUrl: '/',
  },
  canvas: {},
};

vi.stubGlobal('URL', {
  createObjectURL: vi.fn().mockImplementation((blob) => {
    return `mock-object-url/${blob.name}`;
  }),
});

vi.mock('@/utils/drupal-globals', () => ({
  getDrupal: () => ({
    url: (path) => `http://mock-drupal-url/${path}`,
  }),
  getDrupalSettings: () => mockDrupalSettings,
  getCanvasSettings: () => mockDrupalSettings.canvas,
  getBasePath: () => mockDrupalSettings.path.baseUrl,
  setCanvasDrupalSetting: (property, value) => {
    if (mockDrupalSettings?.canvas?.[property]) {
      mockDrupalSettings.canvas[property] = {
        ...mockDrupalSettings.canvas[property],
        ...value,
      };
    }
  },
  getCanvasModuleBaseUrl: () => '/modules/contrib/canvas',
}));

vi.mock('@swc/wasm-web', () => ({
  default: vi.fn().mockReturnValue(Promise.resolve()),
  transformSync: vi.fn(() => ({
    code: '',
  })),
}));

vi.mock('tailwindcss-in-browser', () => ({
  default: vi.fn().mockReturnValue(Promise.resolve('')),
  extractClassNameCandidates: vi.fn().mockReturnValue([]),
  compileCss: vi.fn().mockImplementation(() => Promise.resolve('')),
  compilePartialCss: vi.fn().mockImplementation(() => Promise.resolve('')),
  transformCss: vi.fn().mockReturnValue(Promise.resolve('')),
}));
