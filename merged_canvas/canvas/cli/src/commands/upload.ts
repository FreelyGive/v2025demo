import fs from 'fs/promises';
import path from 'path';
import chalk from 'chalk';
import * as p from '@clack/prompts';

import { ensureConfig, getConfig, setConfig } from '../config.js';
import { createApiService } from '../services/api.js';
import { buildComponent } from '../utils/build';
import { buildTailwindForComponents } from '../utils/build-tailwind.js';
import {
  createComponentPayload,
  processComponentFiles,
} from '../utils/process-component-files.js';
import { reportResults } from '../utils/report-results';
import { selectLocalComponents } from '../utils/select-local-components.js';
import { fileExists } from '../utils/utils';

import type { Command } from 'commander';
import type { ApiService } from '../services/api.js';
import type { Result } from '../types/Result.js';

interface UploadOptions {
  clientId?: string;
  clientSecret?: string;
  siteUrl?: string;
  scope?: string;
  dir?: string;
  verbose?: boolean;
  all?: boolean;
  tailwind?: boolean;
}

/**
 * Registers the upload command. Scripts that run on CI should use the --all flag.
 */
export function uploadCommand(program: Command): void {
  program
    .command('upload')
    .description('build and upload local components and global CSS assets')
    .option('--client-id <id>', 'Client ID')
    .option('--client-secret <secret>', 'Client Secret')
    .option('--site-url <url>', 'Site URL')
    .option('--scope <scope>', 'Scope')
    .option('-d, --dir <directory>', 'Component directory')
    .option('--all', 'Upload all components')
    .option('--verbose', 'Verbose output')
    .option('--no-tailwind', 'Skip Tailwind CSS building')
    .action(async (options: UploadOptions) => {
      const allFlag = options.all || false;
      const skipTailwind = !options.tailwind;

      try {
        p.intro('Drupal Canvas Component Upload');

        // Update config with CLI options
        if (options.clientId) setConfig({ clientId: options.clientId });
        if (options.clientSecret)
          setConfig({ clientSecret: options.clientSecret });
        if (options.siteUrl) setConfig({ siteUrl: options.siteUrl });
        if (options.dir) setConfig({ componentDir: options.dir });
        if (options.scope) setConfig({ scope: options.scope });
        if (options.all) setConfig({ all: options.all });
        if (options.verbose) setConfig({ verbose: options.verbose });
        // Ensure all required config is present
        await ensureConfig([
          'siteUrl',
          'clientId',
          'clientSecret',
          'scope',
          'componentDir',
        ]);
        const config = getConfig();

        // Select components to upload
        const componentsToUpload = await selectLocalComponents(
          allFlag,
          'Select components to upload',
        );
        if (!componentsToUpload || componentsToUpload.length === 0) {
          return;
        }

        // Create API service
        const apiService = await createApiService();

        // Build and upload components
        const componentResults = await getBuildAndUploadResults(
          componentsToUpload as string[],
          apiService,
        );

        // Display component upload results
        reportResults(componentResults, 'Uploaded components', 'Component');

        if (skipTailwind) {
          p.log.info('Skipping Tailwind CSS build');
        } else {
          // Build Tailwind CSS and upload global CSS
          const s2 = p.spinner();
          s2.start('Building Tailwind CSS');
          const tailwindResult = await buildTailwindForComponents(
            componentsToUpload as string[],
          );
          const componentLabelPluralized =
            componentsToUpload.length === 1 ? 'component' : 'components';
          s2.stop(
            chalk.green(
              `Processed Tailwind CSS classes from ${componentsToUpload.length} selected local ${componentLabelPluralized} and all online components`,
            ),
          );

          // Capture Tailwind error if any
          if (!tailwindResult.success && tailwindResult.details) {
            // Report failed Tailwind CSS build.
            reportResults([tailwindResult], 'Built assets', 'Asset');
            p.note(
              chalk.red(`Tailwind build failed, global assets upload aborted.`),
            );
          } else {
            // If the Tailwind build was successful, proceed with uploading the global CSS.
            const globalCssResult = await uploadGlobalAssetLibrary(
              apiService,
              config.componentDir,
            );
            reportResults([globalCssResult], 'Uploaded assets', 'Asset');
          }
        }
        p.outro('⬆️ Upload command completed');
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

// Get the build and upload results.
async function getBuildAndUploadResults(
  componentsToUpload: string[],
  apiService: ApiService,
): Promise<Result[]> {
  const results: Result[] = [];

  // Build components
  const buildResults = await buildSelectedComponents(componentsToUpload);

  // Filter successful builds
  const successfulBuilds = buildResults.filter((build) => build.success);
  const failedBuilds = buildResults.filter((build) => !build.success);

  if (successfulBuilds.length === 0) {
    const message = 'All component builds failed.';
    p.note(chalk.red(message));
  }
  const spinner: {
    start: (msg?: string) => void;
    stop: (msg?: string, code?: number) => void;
    message: (msg?: string) => void;
  } = p.spinner();
  spinner.start('Uploading components');

  // Only upload the successfully built components.
  for (const buildResult of successfulBuilds) {
    const dir = buildResult.itemName
      ? (componentsToUpload.find(
          (d) => path.basename(d) === buildResult.itemName,
        ) as string)
      : undefined;

    if (!dir) continue;

    try {
      // Process component files
      const componentName = path.basename(dir);

      // Process all component files
      const { sourceCodeJs, compiledJs, sourceCodeCss, compiledCss, metadata } =
        await processComponentFiles(dir);
      if (!metadata) {
        throw new Error('Invalid metadata file');
      }

      const machineName =
        buildResult.itemName ||
        metadata.machineName ||
        componentName.toLowerCase().replace(/[^a-z0-9_-]/g, '_');

      // @todo: Add code from /ui to automatically detect first party imports
      //   without relying on yml metadata.
      const importedJsComponents = metadata.importedJsComponents || [];

      const componentPayloadArg = {
        metadata,
        machineName,
        componentName,
        sourceCodeJs,
        compiledJs,
        sourceCodeCss,
        compiledCss,
        importedJsComponents,
      };
      const componentPayload = createComponentPayload(componentPayloadArg);

      // Check if component exists already
      let componentExists = false;

      try {
        await apiService.getComponent(machineName);
        componentExists = true;
      } catch {
        // Component does not exist, will create new.
      }

      try {
        // Create or update the component
        if (componentExists) {
          await apiService.updateComponent(machineName, componentPayload, true);
        } else {
          await apiService.createComponent(componentPayload, true);
        }
      } catch (error) {
        // If the error is a 422 and specifies dataDependencies as the problem,
        // it might be due to running a version of Canvas that does not yet support
        // this property. Remove dataDependencies from the payload and make
        // another attempts.
        if (
          error.status === 422 &&
          error?.response?.data?.errors?.[0]?.source?.pointer ===
            'dataDependencies'
        ) {
          // eslint-disable-next-line @typescript-eslint/no-unused-vars
          const { dataDependencies, ...remainingPayload } = componentPayload;
          if (componentExists) {
            await apiService.updateComponent(machineName, remainingPayload);
          } else {
            await apiService.createComponent(remainingPayload);
          }
        } else {
          // If we are here the create/update failed and is not a 422 related to
          // dataDependencies.
          // Make another attempt to create/update without the 2nd argument so
          // the error is in the format expected by the catch statement that
          // summarizes the success (or lack thereof) of this operation.
          if (componentExists) {
            await apiService.updateComponent(machineName, componentPayload);
          } else {
            await apiService.createComponent(componentPayload);
          }
        }
      }

      results.push({
        itemName: componentName,
        success: true,
        details: [
          {
            content: componentExists ? 'Updated' : 'Created',
          },
        ],
      });
    } catch (error) {
      const errorMessage =
        error instanceof Error ? error.message : String(error);
      results.push({
        itemName: buildResult.itemName,
        success: false,
        details: [
          {
            content: errorMessage,
          },
        ],
      });
    }
  }
  // Add the failed builds to the upload results to get the correct count.
  results.push(...failedBuilds);
  const componentLabelPluralized =
    results.length === 1 ? 'component' : 'components';
  spinner.stop(
    chalk.green(`Processed ${results.length} ${componentLabelPluralized}`),
  );
  return results;
}

/**
 * Build all selected components
 */
async function buildSelectedComponents(
  componentDirs: string[],
): Promise<Result[]> {
  const buildResults: Result[] = [];
  for (const dir of componentDirs) {
    buildResults.push(await buildComponent(dir));
  }
  return buildResults;
}

/**
 * Uploads global CSS if it exists
 */
async function uploadGlobalAssetLibrary(
  apiService: ApiService,
  componentDir: string,
): Promise<Result> {
  try {
    const distDir = path.join(componentDir, 'dist');
    const globalCompiledCssPath = path.join(distDir, 'index.css');
    const globalCompiledCssExists = await fileExists(globalCompiledCssPath);
    if (globalCompiledCssExists) {
      const globalCompiledCss = await fs.readFile(
        path.join(distDir, 'index.css'),
        'utf-8',
      );
      const classNameCandidateIndexFile = await fs.readFile(
        path.join(distDir, 'index.js'),
        'utf-8',
      );
      // @todo: It doesn't make sense to have to fetch the current.css.original
      // from the API, but we need to do this because otherwise, the existing
      // css.original gets overwritten if we don't pass anything.
      const current = await apiService.getGlobalAssetLibrary();
      const originalCss = current.css.original;

      // Upload the global CSS
      await apiService.updateGlobalAssetLibrary({
        css: {
          original: originalCss,
          compiled: globalCompiledCss,
        },
        js: {
          original: classNameCandidateIndexFile,
          compiled: '',
        },
      });
      return {
        success: true,
        itemName: 'Global CSS',
      };
    } else {
      return {
        success: false,
        itemName: 'Global CSS',
        details: [
          {
            content: `Global CSS file not found at ${globalCompiledCssPath}.`,
          },
        ],
      };
    }
  } catch (error) {
    const errorMessage = error instanceof Error ? error.message : String(error);
    return {
      success: false,
      itemName: 'Global CSS',
      details: [
        {
          content: errorMessage,
        },
      ],
    };
  }
}
