import fs from 'fs/promises';
import path from 'path';
import chalk from 'chalk';
import yaml from 'js-yaml';
import * as p from '@clack/prompts';

import { ensureConfig, getConfig, setConfig } from '../config';
import { createApiService } from '../services/api';
import { reportResults } from '../utils/report-results';
import { directoryExists } from '../utils/utils';

import type { Command } from 'commander';
import type { Component } from '../types/Component';
import type { Metadata } from '../types/Metadata';
import type { Result } from '../types/Result';

interface DownloadOptions {
  clientId?: string;
  clientSecret?: string;
  siteUrl?: string;
  scope?: string;
  dir?: string;
  component?: string;
  all?: boolean; // Download all components
  verbose?: boolean;
}

// @todo: Support non-interactive download if user passes all necessary args in.
export function downloadCommand(program: Command): void {
  program
    .command('download')
    .description('download components to your local filesystem')
    .option('--client-id <id>', 'Client ID')
    .option('--client-secret <secret>', 'Client Secret')
    .option('--site-url <url>', 'Site URL')
    .option('--scope <scope>', 'Scope')
    .option('-d, --dir <directory>', 'Component directory')
    .option('-c, --component <name>', 'Specific component to download')
    .option('--all', 'Download all components')
    .option('--verbose', 'Enable verbose output')
    .action(async (options: DownloadOptions) => {
      p.intro('Drupal Canvas Component Download');

      try {
        // Update config with CLI options
        if (options.clientId) setConfig({ clientId: options.clientId });
        if (options.clientSecret)
          setConfig({ clientSecret: options.clientSecret });
        if (options.siteUrl) setConfig({ siteUrl: options.siteUrl });
        if (options.dir) setConfig({ componentDir: options.dir });
        if (options.all) setConfig({ all: options.all });
        if (options.scope) setConfig({ scope: options.scope });
        if (options.verbose) setConfig({ verbose: true });
        // Ensure all required config is present
        await ensureConfig([
          'siteUrl',
          'clientId',
          'clientSecret',
          'scope',
          'componentDir',
        ]);

        const config = getConfig();
        const apiService = await createApiService();

        // Get components
        const s = p.spinner();
        s.start('Fetching components');

        const components = await apiService.listComponents();
        const {
          css: { original: globalCss },
        } = await apiService.getGlobalAssetLibrary();

        if (Object.keys(components).length === 0) {
          s.stop('No components found');
          p.outro('Download cancelled - no components were found');
          return;
        }

        s.stop(`Found ${Object.keys(components).length} components`);

        // If a specific component was requested, filter for it
        let componentsToDownload: Record<string, Component> = {};

        // If --all option is used, download all components.
        if (options.all) {
          // Download all components
          componentsToDownload = components;
        } else if (options.component) {
          const component = Object.values(components).find(
            (c) =>
              c.machineName === options.component ||
              c.name === options.component,
          );
          if (!component) {
            p.note(chalk.red(`Component "${options.component}" not found`));
            p.outro('Download cancelled');
            return;
          }
          componentsToDownload = { component };
        } else {
          // Choose components to download
          const selectedComponents = await p.multiselect({
            message: 'Select components to download',
            options: [
              {
                value: '_allComponents',
                label: 'All components',
              },
              ...Object.keys(components).map((key) => ({
                value: components[key].machineName,
                label: `${components[key].name} (${components[key].machineName})`,
              })),
            ],
            required: true,
          });

          if (p.isCancel(selectedComponents)) {
            p.cancel('Operation cancelled');
            return;
          }

          // Check if "all" option is selected
          if (selectedComponents.includes('_allComponents')) {
            componentsToDownload = components;
          } else {
            componentsToDownload = Object.fromEntries(
              Object.entries(components).filter(([, component]) =>
                (selectedComponents as string[]).includes(
                  component.machineName,
                ),
              ),
            );
          }
        }

        // Handle singular/plural cases for console messages.
        const componentPluralized = `component${Object.keys(componentsToDownload).length > 1 ? 's' : ''}`;

        // Confirm download
        const confirmDownload = await p.confirm({
          message: `Download ${Object.keys(componentsToDownload).length} ${componentPluralized} to ${config.componentDir}?`,
          initialValue: true,
        });

        if (p.isCancel(confirmDownload) || !confirmDownload) {
          p.cancel('Operation cancelled');
          return;
        }

        // Download components
        const results: Result[] = [];

        s.start(`Downloading ${componentPluralized}`);

        for (const key in componentsToDownload) {
          const component = componentsToDownload[key];
          try {
            // Create component directory structure
            const componentDir = path.join(
              config.componentDir,
              component.machineName,
            );
            // Check if the directory exists and is non-empty to confirm deletion.
            const dirExists = await directoryExists(componentDir);
            if (dirExists) {
              const files = await fs.readdir(componentDir);
              if (files.length > 0) {
                const confirmDelete = await p.confirm({
                  message: `The "${componentDir}" directory is not empty. Are you sure you want to delete and overwrite this directory?`,
                  initialValue: true,
                });
                if (p.isCancel(confirmDelete) || !confirmDelete) {
                  p.cancel('Operation cancelled');
                  process.exit(0);
                }
              }
            }

            await fs.rm(componentDir, { recursive: true, force: true });
            await fs.mkdir(componentDir, { recursive: true });

            // Create component.yml metadata file
            const metadata: Metadata = {
              name: component.name,
              machineName: component.machineName,
              status: component.status,
              required: component.required || [],
              props: {
                properties: component.props || {},
              },
              slots: component.slots || {},
              importedJsComponents: component.importedJsComponents || [],
              dataDependencies: component.dataDependencies || [],
            };

            await fs.writeFile(
              path.join(componentDir, `component.yml`),
              yaml.dump(metadata),
              'utf-8',
            );

            // Create JS file
            if (component.sourceCodeJs) {
              await fs.writeFile(
                path.join(componentDir, `index.jsx`),
                component.sourceCodeJs,
                'utf-8',
              );
            }

            // Create CSS file
            if (component.sourceCodeCss) {
              await fs.writeFile(
                path.join(componentDir, `index.css`),
                component.sourceCodeCss,
                'utf-8',
              );
            }

            results.push({
              itemName: component.machineName,
              success: true,
            });
          } catch (error) {
            results.push({
              itemName: component.machineName,
              success: false,
              details: [
                {
                  content:
                    error instanceof Error ? error.message : String(error),
                },
              ],
            });
          }
        }
        s.stop(
          chalk.green(
            `Processed ${Object.keys(componentsToDownload).length} ${componentPluralized}`,
          ),
        );

        reportResults(results, 'Downloaded components', 'Component');

        // Create global.css file if it exists.
        if (globalCss) {
          let globalCssResult: Result;
          try {
            const globalCssPath = path.join(config.componentDir, 'global.css');
            await fs.writeFile(globalCssPath, globalCss, 'utf-8');
            globalCssResult = {
              itemName: 'global.css',
              success: true,
            };
          } catch (error) {
            const errorMessage =
              error instanceof Error ? error.message : String(error);
            globalCssResult = {
              itemName: 'global.css',
              success: false,
              details: [
                {
                  content: errorMessage,
                },
              ],
            };
          }
          reportResults([globalCssResult], 'Downloaded assets', 'Asset');
        }

        p.outro(`⬇️ Download command completed`);
      } catch (error) {
        if (error instanceof Error) {
          p.note(chalk.red(`Error: ${error.message}`));
        } else {
          p.note(chalk.red(`Unknown error: ${String(error)}`));
        }
        process.exit(1);
      }
    });
}
