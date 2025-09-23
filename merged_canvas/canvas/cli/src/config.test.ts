import * as fs from 'fs';
import * as path from 'path';
import * as dotenv from 'dotenv';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import * as p from '@clack/prompts';

import {
  ensureConfig,
  getConfig,
  loadEnvFiles,
  promptForConfig,
  setConfig,
} from './config';

vi.mock('fs');
vi.mock('path');
vi.mock('dotenv');

describe('config', () => {
  describe('get/set', () => {
    beforeEach(() => {
      vi.clearAllMocks();
      vi.resetAllMocks();
      setConfig({
        siteUrl: '',
        clientId: '',
        clientSecret: '',
        componentDir: './components',
        verbose: false,
      });
    });

    it('should return default config values', () => {
      const config = getConfig();
      expect(config).toEqual({
        siteUrl: '',
        clientId: '',
        clientSecret: '',
        scope: 'canvas:js_component canvas:asset_library',
        componentDir: './components',
        verbose: false,
      });
    });

    it('should update config values', () => {
      setConfig({
        siteUrl: 'https://example.com',
        clientId: 'test-client',
      });
      expect(getConfig()).toEqual({
        siteUrl: 'https://example.com',
        clientId: 'test-client',
        clientSecret: '',
        scope: 'canvas:js_component canvas:asset_library',
        componentDir: './components',
        verbose: false,
      });
    });
  });

  describe('ensure', () => {
    it('should not prompt if all required keys are present', async () => {
      setConfig({
        siteUrl: 'https://example.com',
        clientId: 'test-client',
        clientSecret: 'test-secret',
        componentDir: './components',
      });

      await ensureConfig(['siteUrl', 'clientId', 'clientSecret']);
      expect(p.text).not.toHaveBeenCalled();
      expect(p.password).not.toHaveBeenCalled();
    });

    it('should prompt for missing required keys', async () => {
      setConfig({
        siteUrl: '',
        clientId: '',
        clientSecret: '',
      });
      await ensureConfig(['siteUrl', 'clientId', 'clientSecret']);
      expect(p.text).toHaveBeenCalledTimes(2);
      expect(p.password).toHaveBeenCalledTimes(1);
    });
  });

  describe('prompt', () => {
    it('should validate site URL', async () => {
      await promptForConfig('siteUrl');

      expect(p.text).toHaveBeenCalledWith({
        message: 'Enter the site URL',
        placeholder: 'https://example.com',
        validate: expect.any(Function),
      });

      // Get the validate function that was passed to p.text()
      const validateFn = vi.mocked(p.text).mock.calls[0][0].validate as (
        value: string,
      ) => string | undefined;

      expect(validateFn('invalid-url')).toBe(
        'URL must start with http:// or https://',
      );
      expect(validateFn('https://example.com')).toBeUndefined();
      expect(validateFn('')).toBe('Site URL is required');
    });

    it('should validate client ID', async () => {
      await promptForConfig('clientId');

      expect(p.text).toHaveBeenCalledWith({
        message: 'Enter your client ID',
        validate: expect.any(Function),
      });

      // Get the validate function that was passed to p.text()
      const validateFn = vi.mocked(p.text).mock.calls[0][0].validate as (
        value: string,
      ) => string | undefined;

      expect(validateFn('')).toBe('Client ID is required');
      expect(validateFn('test-client')).toBeUndefined();
    });

    it('should validate client secret', async () => {
      await promptForConfig('clientSecret');

      expect(p.password).toHaveBeenCalledWith({
        message: 'Enter your client secret',
        validate: expect.any(Function),
      });

      const validateFn = vi.mocked(p.password).mock.calls[0][0].validate as (
        value: string,
      ) => string | undefined;

      expect(validateFn('')).toBe('Client secret is required');
      expect(validateFn('test-secret')).toBeUndefined();
    });

    it('should validate component directory', async () => {
      await promptForConfig('componentDir');

      expect(p.text).toHaveBeenCalledWith({
        message: 'Enter the component directory',
        placeholder: './components',
        validate: expect.any(Function),
      });

      const validateFn = vi.mocked(p.text).mock.calls[0][0].validate as (
        value: string,
      ) => string | undefined;

      expect(validateFn('')).toBe('Component directory is required');
      expect(validateFn('test-dir')).toBeUndefined();
    });

    it('should handle cancelled prompts', async () => {
      vi.mocked(p.isCancel).mockReturnValue(true);
      vi.mocked(p.text).mockResolvedValue('cancelled');

      await expect(ensureConfig(['siteUrl'])).rejects.toThrow(
        'process.exit unexpectedly called with "0"',
      );
    });
  });

  describe('load env', () => {
    beforeEach(() => {
      vi.resetModules();
      vi.unstubAllEnvs();
      vi.stubEnv('HOME', '/home/user');
    });

    it('should load from home directory .canvasrc file only', async () => {
      const mockHomeEnvPath = '/home/user/.canvasrc';
      const mockLocalEnvPath = '/current/dir/.env';
      vi.mocked(path.resolve)
        .mockReturnValueOnce(mockHomeEnvPath)
        .mockReturnValueOnce(mockLocalEnvPath);
      vi.mocked(fs.existsSync)
        .mockReturnValueOnce(true)
        .mockReturnValueOnce(false);

      loadEnvFiles();
      expect(dotenv.config).toHaveBeenCalledExactlyOnceWith({
        path: mockHomeEnvPath,
      });
    });

    it('should load from local .env file only', async () => {
      const mockHomeEnvPath = '/home/user/.canvasrc';
      const mockLocalEnvPath = '/current/dir/.env';
      vi.mocked(path.resolve)
        .mockReturnValueOnce(mockHomeEnvPath)
        .mockReturnValueOnce(mockLocalEnvPath);
      vi.mocked(fs.existsSync)
        .mockReturnValueOnce(false)
        .mockReturnValueOnce(true);

      loadEnvFiles();
      expect(dotenv.config).toHaveBeenCalledWith({ path: mockLocalEnvPath });
    });

    it('should give precedence to local .env over home .canvasrc', async () => {
      const mockHomeEnvPath = '/home/user/.canvasrc';
      const mockLocalEnvPath = '/current/dir/.env';
      vi.mocked(path.resolve)
        .mockReturnValueOnce(mockHomeEnvPath)
        .mockReturnValueOnce(mockLocalEnvPath);
      vi.mocked(fs.existsSync).mockReturnValue(true);

      loadEnvFiles();
      expect(dotenv.config).toHaveBeenCalledTimes(2);
      expect(dotenv.config).toHaveBeenLastCalledWith({
        path: mockLocalEnvPath,
      });
    });

    it('should initialize config with environment variables', async () => {
      vi.stubEnv('CANVAS_SITE_URL', 'https://test.example.com');
      vi.stubEnv('CANVAS_CLIENT_ID', 'test-client');
      vi.stubEnv('CANVAS_CLIENT_SECRET', 'test-secret');
      vi.stubEnv('CANVAS_SCOPE', 'canvas:js_component canvas:asset_library');
      vi.stubEnv('CANVAS_COMPONENT_DIR', './test-components');
      vi.stubEnv('CANVAS_VERBOSE', 'true');

      // Re-import config to trigger initialization
      const { getConfig } = await import('./config');

      expect(getConfig()).toEqual({
        siteUrl: 'https://test.example.com',
        clientId: 'test-client',
        clientSecret: 'test-secret',
        scope: 'canvas:js_component canvas:asset_library',
        componentDir: './test-components',
        verbose: true,
      });
    });

    it('should use default config values when no environment files exist', async () => {
      vi.mocked(fs.existsSync).mockReturnValue(false);

      // Re-import config to trigger initialization
      const { getConfig } = await import('./config');

      expect(getConfig()).toEqual({
        siteUrl: '',
        clientId: '',
        clientSecret: '',
        scope: 'canvas:js_component canvas:asset_library',
        componentDir: './components',
        verbose: false,
      });
    });
  });
});
