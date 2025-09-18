import { afterAll, afterEach, beforeAll, describe, expect, it } from 'vitest';

import { server } from './__mocks__/server';
import { ApiService } from './api';

describe('api service', () => {
  const mockConfig = {
    siteUrl: 'https://canvas-mock',
    clientId: 'cli',
    clientSecret: 'secret',
    scope: 'canvas:js_component canvas:asset_library',
  };

  beforeAll(() => {
    server.listen();
  });

  afterEach(() => {
    server.resetHandlers();
  });

  afterAll(() => {
    server.close();
  });

  describe('create', () => {
    it('should initialize with access token', async () => {
      const client = await ApiService.create(mockConfig);
      expect(client).toBeDefined();
      expect(client.getAccessToken()).toBe('test-access-token');
    });

    it('should handle invalid credentials', async () => {
      await expect(
        ApiService.create({
          ...mockConfig,
          clientId: 'invalid',
          clientSecret: 'invalid',
        }),
      ).rejects.toThrow(
        'Authentication failed. Please check your client ID and secret.',
      );
    });

    it('should handle errors', async () => {
      await expect(
        ApiService.create({
          ...mockConfig,
          scope: 'canvas:this-scope-is-invalid',
        }),
      ).rejects.toThrow(
        'API Error (400): invalid_scope | The requested scope is invalid, unknown, or malformed | Check the `canvas:invalid` scope',
      );
    });

    it('should handle no permission', async () => {
      const client = await ApiService.create({
        ...mockConfig,
        scope: 'canvas:this-scope-is-valid-but-no-permission',
      });
      await expect(client.listComponents()).rejects.toThrow(
        'You do not have permission to perform this action. Check your configured scope.',
      );
    });

    it('should handle network errors', async () => {
      server.close();
      await expect(ApiService.create(mockConfig)).rejects.toThrow(
        'Network error: No response from server. Check your site URL and internet connection.',
      );
      await expect(
        ApiService.create({
          ...mockConfig,
          siteUrl: 'http://ddev.site--not-working',
        }),
      ).rejects.toThrow(
        'Network error: No response from DDEV site. Is DDEV running? Try using HTTP instead of HTTPS.',
      );
    });
  });
});
