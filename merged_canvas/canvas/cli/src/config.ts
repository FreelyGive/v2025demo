import fs from 'fs';
import path from 'path';
import dotenv from 'dotenv';
import * as p from '@clack/prompts';

// Load environment variables.
export function loadEnvFiles() {
  // Load from the user's home directory (for global settings).
  const homeDir = process.env.HOME || process.env.USERPROFILE || '';
  if (homeDir) {
    const homeEnvPath = path.resolve(homeDir, '.canvasrc');
    if (fs.existsSync(homeEnvPath)) {
      dotenv.config({ path: homeEnvPath });
    }
  }
  // Then load from the current directory so the local .env file takes precedence.
  const localEnvPath = path.resolve(process.cwd(), '.env');
  if (fs.existsSync(localEnvPath)) {
    dotenv.config({ path: localEnvPath });
  }
}

// Load environment variables before creating config.
loadEnvFiles();

export interface Config {
  siteUrl: string;
  clientId: string;
  clientSecret: string;
  scope: string;
  componentDir: string;
  verbose: boolean;
  all?: boolean;
}

let config: Config = {
  siteUrl: process.env.CANVAS_SITE_URL || '',
  clientId: process.env.CANVAS_CLIENT_ID || '',
  clientSecret: process.env.CANVAS_CLIENT_SECRET || '',
  scope: process.env.CANVAS_SCOPE || 'canvas:js_component canvas:asset_library',
  componentDir: process.env.CANVAS_COMPONENT_DIR || './components',
  verbose: process.env.CANVAS_VERBOSE === 'true',
};

export function getConfig(): Config {
  return config;
}

export function setConfig(newConfig: Partial<Config>): void {
  config = { ...config, ...newConfig };
}

export type ConfigKey = keyof Config;

export async function ensureConfig(requiredKeys: ConfigKey[]): Promise<void> {
  const config = getConfig();
  const missingKeys = requiredKeys.filter((key) => !config[key]);

  for (const key of missingKeys) {
    await promptForConfig(key);
  }
}

export async function promptForConfig(key: ConfigKey): Promise<void> {
  switch (key) {
    case 'siteUrl': {
      const value = await p.text({
        message: 'Enter the site URL',
        placeholder: 'https://example.com',
        validate: (value) => {
          if (!value) return 'Site URL is required';
          if (!value.startsWith('http'))
            return 'URL must start with http:// or https://';
          return;
        },
      });

      if (p.isCancel(value)) {
        p.cancel('Operation cancelled');
        process.exit(0);
      }

      setConfig({ siteUrl: value });
      break;
    }

    case 'clientId': {
      const value = await p.text({
        message: 'Enter your client ID',
        validate: (value) => {
          if (!value) return 'Client ID is required';
          return;
        },
      });

      if (p.isCancel(value)) {
        p.cancel('Operation cancelled');
        process.exit(0);
      }

      setConfig({ clientId: value });
      break;
    }

    case 'clientSecret': {
      const value = await p.password({
        message: 'Enter your client secret',
        validate: (value) => {
          if (!value) return 'Client secret is required';
          return;
        },
      });

      if (p.isCancel(value)) {
        p.cancel('Operation cancelled');
        process.exit(0);
      }

      setConfig({ clientSecret: value });
      break;
    }

    case 'componentDir': {
      const value = await p.text({
        message: 'Enter the component directory',
        placeholder: './components',
        validate: (value) => {
          if (!value) return 'Component directory is required';
          return;
        },
      });

      if (p.isCancel(value)) {
        p.cancel('Operation cancelled');
        process.exit(0);
      }

      setConfig({ componentDir: value });
      break;
    }
  }
}
