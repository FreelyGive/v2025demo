import fs from 'fs/promises';
import os from 'os';
import path from 'path';
import chalk from 'chalk';
import * as p from '@clack/prompts';

export const CANVAS_CACHE_DIR = path.join(os.homedir(), '.canvas');

// Download the JS source of all code components into a local directory: ~/.canvas
export async function downloadJsSourceFromCanvas(
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  componentsToDownload: Record<string, any>,
) {
  for (const key in componentsToDownload) {
    const component = componentsToDownload[key];
    try {
      // Create component directory structure
      const componentDir = path.join(CANVAS_CACHE_DIR, component.machineName);
      await fs.rm(componentDir, { recursive: true, force: true });
      await fs.mkdir(componentDir, { recursive: true });

      // Create JS file
      if (component.sourceCodeJs) {
        await fs.writeFile(
          path.join(componentDir, `index.jsx`),
          component.sourceCodeJs,
          'utf-8',
        );
      }
    } catch (error) {
      if (error instanceof Error) {
        p.note(chalk.red(`Error: ${error.message}`));
      } else {
        p.note(chalk.red(`Unknown error: ${String(error)}`));
      }
    }
  }
}

// Copy local JS sources from the CLI components directory to  ~/.canvas
export async function copyLocalJsSource(
  componentsToCopy: string[],
): Promise<void> {
  try {
    // Ensure the target directory exists
    await fs.mkdir(CANVAS_CACHE_DIR, { recursive: true });
    // Copy each component to the target directory
    for (const componentPath of componentsToCopy) {
      const baseName = path.basename(componentPath);
      const sourcePath = componentPath;
      const targetPath = path.join(CANVAS_CACHE_DIR, baseName);
      // Check if it's a directory
      const stats = await fs.stat(sourcePath);
      if (stats.isDirectory()) {
        // Create the component directory in the target
        await fs.mkdir(targetPath, { recursive: true });
        const sourceFile = path.join(sourcePath, 'index.jsx');
        const targetFile = path.join(targetPath, 'index.jsx');
        await fs.copyFile(sourceFile, targetFile);
      }
    }
  } catch (error) {
    if (error instanceof Error) {
      p.note(chalk.red(`Error: ${error.message}`));
    } else {
      p.note(chalk.red(`Unknown error: ${String(error)}`));
    }
  }
}

export async function cleanUpCacheDirectory(): Promise<void> {
  try {
    const cacheEntries = await fs.readdir(CANVAS_CACHE_DIR, {
      withFileTypes: true,
    });
    for (const entry of cacheEntries) {
      const entryPath = path.join(CANVAS_CACHE_DIR, entry.name);
      if (entry.isDirectory()) {
        await fs.rm(entryPath, { recursive: true, force: true });
      } else {
        await fs.unlink(entryPath);
      }
    }
  } catch (error) {
    p.note(
      chalk.red(
        `Failed to clean cache directory contents: ${error instanceof Error ? error.message : String(error)}`,
      ),
    );
  }
}
