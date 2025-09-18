import axios from 'axios';

import { getConfig } from '../config.js';

import type { AxiosInstance } from 'axios';
import type { AssetLibrary, Component } from '../types/Component';

export interface ApiOptions {
  siteUrl: string;
  clientId: string;
  clientSecret: string;
  scope: string;
}

export class ApiService {
  private client: AxiosInstance;
  private readonly siteUrl: string;
  private readonly clientId: string;
  private readonly clientSecret: string;
  private readonly scope: string;
  private accessToken: string | null = null;

  private constructor(options: ApiOptions) {
    this.clientId = options.clientId;
    this.clientSecret = options.clientSecret;
    this.siteUrl = options.siteUrl;
    this.scope = options.scope;

    // Create the client without authorization headers by default
    this.client = axios.create({
      baseURL: options.siteUrl,
      headers: {
        'Content-Type': 'application/json',
        // Add the CLI marker header to identify CLI requests
        'X-Canvas-CLI': '1',
      },
      // Allow longer timeout for uploads
      timeout: 30000,
      transformResponse: [
        (data) => {
          const forbidden = ['Fatal error'];

          // data comes as string, check it directly
          if (data.includes && forbidden.some((str) => data.includes(str))) {
            throw new Error(data);
          }

          // Parse JSON if it's a string (default axios behavior)
          try {
            return JSON.parse(data);
          } catch {
            return data;
          }
        },
      ],
    });
  }

  public static async create(options: ApiOptions): Promise<ApiService> {
    const instance = new ApiService(options);
    try {
      const response = await instance.client.post(
        '/oauth/token',
        new URLSearchParams({
          grant_type: 'client_credentials',
          client_id: instance.clientId,
          client_secret: instance.clientSecret,
          scope: instance.scope,
        }).toString(),
        {
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
        },
      );

      instance.accessToken = response.data.access_token;

      // Update the default headers to include the access token
      instance.client.defaults.headers.common['Authorization'] =
        `Bearer ${instance.accessToken}`;
    } catch (error) {
      instance.handleApiError(error);
      throw new Error(
        'Failed to initialize API service: Could not obtain access token',
      );
    }
    return instance;
  }

  getAccessToken(): string | null {
    return this.accessToken;
  }

  /**
   * List all components.
   */
  async listComponents(): Promise<Record<string, Component>> {
    try {
      const response = await this.client.get(
        '/canvas/api/v0/config/js_component',
      );
      return response.data;
    } catch (error) {
      this.handleApiError(error);
      throw new Error('Failed to list components');
    }
  }

  /**
   * Create a new component in Canvas.
   */
  async createComponent(
    component: Component,
    raw: boolean = false,
  ): Promise<Component> {
    try {
      const response = await this.client.post(
        '/canvas/api/v0/config/js_component',
        component,
      );
      return response.data;
    } catch (error) {
      // If raw is true (not the default), rethrow so the caller can handle it.
      if (raw) {
        throw error;
      }
      this.handleApiError(error as Error);
      throw new Error(`Failed to create component: '${component.machineName}'`);
    }
  }

  /**
   * Get a specific component
   */
  async getComponent(machineName: string): Promise<Component> {
    try {
      const response = await this.client.get(
        `/canvas/api/v0/config/js_component/${machineName}`,
      );
      return response.data;
    } catch (error) {
      this.handleApiError(error);
      throw new Error(`Component '${machineName}' not found`);
    }
  }

  /**
   * Update an existing component
   */
  async updateComponent(
    machineName: string,
    component: Partial<Component>,
  ): Promise<Component> {
    try {
      const response = await this.client.patch(
        `/canvas/api/v0/config/js_component/${machineName}`,
        component,
      );
      return response.data;
    } catch (error) {
      this.handleApiError(error);
      throw new Error(`Failed to update component '${machineName}'`);
    }
  }

  /**
   * Get global asset library.
   */
  async getGlobalAssetLibrary(): Promise<AssetLibrary> {
    try {
      const response = await this.client.get(
        '/canvas/api/v0/config/asset_library/global',
      );
      return response.data;
    } catch (error) {
      this.handleApiError(error);
      throw new Error('Failed to get global asset library');
    }
  }

  /**
   * Update global asset library.
   */
  async updateGlobalAssetLibrary(
    assetLibrary: Partial<AssetLibrary>,
  ): Promise<AssetLibrary> {
    try {
      const response = await this.client.patch(
        '/canvas/api/v0/config/asset_library/global',
        assetLibrary,
      );
      return response.data;
    } catch (error) {
      this.handleApiError(error);
      throw new Error('Failed to update global asset library');
    }
  }

  private handleApiError(error: Error): void {
    const config = getConfig();
    const verbose = config.verbose;

    if (axios.isAxiosError(error)) {
      if (error.response) {
        const status = error.response.status;
        const data = error.response.data;

        // Do not output verbose logs for 404 responses. They are expected when
        // uploading newly created components.
        if (verbose && status !== 404) {
          console.error('API Error Details:');
          console.error(`- Status: ${status}`);
          console.error(`- URL: ${error.config?.url || 'unknown'}`);
          console.error(
            `- Method: ${error.config?.method?.toUpperCase() || 'unknown'}`,
          );
          console.error('- Response data:', JSON.stringify(data, null, 2));

          // Hide auth token in logs
          const safeHeaders = { ...error.config?.headers };
          if (safeHeaders && safeHeaders.Authorization) {
            safeHeaders.Authorization = 'Bearer ********';
          }
          console.error(
            '- Request headers:',
            JSON.stringify(safeHeaders, null, 2),
          );
        }

        if (status === 401) {
          throw new Error(
            'Authentication failed. Please check your client ID and secret.',
          );
        } else if (status === 403) {
          throw new Error(
            'You do not have permission to perform this action. Check your configured scope.',
          );
        } else if (
          data &&
          (data.error || data.error_description || data.hint)
        ) {
          throw new Error(
            `API Error (${status}): ${[
              data.error,
              data.error_description,
              data.hint,
            ]
              .filter(Boolean)
              .join(' | ')}`,
          );
        } else {
          throw new Error(`API Error (${status}): ${error.message}`);
        }
      } else if (error.request) {
        // Request was made but no response received
        if (verbose) {
          console.error('Network Error Details:');
          console.error(`- No response received from server`);
          console.error(`- URL: ${error.config?.url || 'unknown'}`);
          console.error(
            `- Method: ${error.config?.method?.toUpperCase() || 'unknown'}`,
          );

          // Hide auth token in logs
          const safeHeaders = { ...error.config?.headers };
          if (safeHeaders && safeHeaders.Authorization) {
            safeHeaders.Authorization = 'Bearer ********';
          }
          console.error(
            '- Request headers:',
            JSON.stringify(safeHeaders, null, 2),
          );

          // Check if this is a local development site
          if (this.siteUrl.includes('ddev.site')) {
            console.error('\nDDEV Local Development Troubleshooting Tips:');
            console.error('1. Make sure DDEV is running: try "ddev status"');
            console.error(
              '2. Try using HTTP instead of HTTPS: use "http://drupal-dev.ddev.site" as URL',
            );
            console.error('3. Check if the site is accessible in your browser');
            console.error(
              '4. For HTTPS issues: Try "ddev auth ssl" to set up local SSL certificates',
            );
          }
        }

        if (this.siteUrl.includes('ddev.site')) {
          throw new Error(
            `Network error: No response from DDEV site. Is DDEV running? Try using HTTP instead of HTTPS.`,
          );
        } else {
          throw new Error(
            `Network error: No response from server. Check your site URL and internet connection.`,
          );
        }
      } else {
        if (verbose) {
          console.error('Request Setup Error:');
          console.error(`- Error: ${error.message}`);
          console.error('- Stack:', error.stack);
        }
        throw new Error(`Request setup error: ${error.message}`);
      }
    } else if (error instanceof Error) {
      if (verbose) {
        console.error('General Error:');
        console.error(`- Message: ${error.message}`);
        console.error('- Stack:', error.stack);
      }
      throw new Error(`Network error: ${error.message}`);
    } else {
      if (verbose) {
        console.error('Unknown Error:', error);
      }
      throw new Error('Unknown API error occurred');
    }
  }
}

export function createApiService(): Promise<ApiService> {
  const config = getConfig();

  if (!config.siteUrl) {
    throw new Error(
      'Site URL is required. Set it in the CANVAS_SITE_URL environment variable or pass it with --site-url.',
    );
  }

  if (!config.clientId) {
    throw new Error(
      'Client ID is required. Set it in the CANVAS_CLIENT_ID environment variable or pass it with --client-id.',
    );
  }

  if (!config.clientSecret) {
    throw new Error(
      'Client secret is required. Set it in the CANVAS_CLIENT_SECRET environment variable or pass it with --client-secret.',
    );
  }

  if (!config.scope) {
    throw new Error(
      'Scope is required. Set it in the CANVAS_SCOPE environment variable or pass it with --scope.',
    );
  }

  return ApiService.create({
    siteUrl: config.siteUrl,
    clientId: config.clientId,
    clientSecret: config.clientSecret,
    scope: config.scope,
  });
}
