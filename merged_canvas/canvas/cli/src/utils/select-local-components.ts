import path from 'path';
import * as p from '@clack/prompts';

import { getConfig } from '../config.js';
import { findComponentDirectories } from './find-component-directories.js';

/**
 * Utility function to select local components for building/processing.
 * Can be reused across different commands like upload, build, etc.
 */
export async function selectLocalComponents(
  allFlag: boolean,
  message: string = 'Select components',
  baseDir?: string,
): Promise<string[] | null> {
  // Get the base directory from config if not provided
  const config = getConfig();
  const searchDir = baseDir || config.componentDir;

  // Find component directories
  const componentDirs = await findComponentDirectories(searchDir);

  if (componentDirs.length === 0) {
    p.outro(`📂 No local components were found in ${searchDir}`);
    return null;
  }

  // Select all components if the --all flag is set.
  if (allFlag) {
    p.log.info(`Selected all components`);
    return componentDirs;
  }

  const selectedDirs = await p.multiselect({
    message,
    options: [
      {
        value: '_allComponents',
        label: 'All components',
      },
      ...componentDirs.map((dir) => ({
        value: dir,
        label: path.basename(dir),
      })),
    ],
    required: true,
  });

  if (p.isCancel(selectedDirs)) {
    p.cancel('Operation cancelled');
    return null;
  }

  const count = selectedDirs.includes('_allComponents')
    ? componentDirs.length
    : selectedDirs.length;

  const confirmBuild = await p.confirm({
    message: `Process ${count} components`,
    initialValue: true,
  });

  if (p.isCancel(confirmBuild) || !confirmBuild) {
    p.cancel('Operation cancelled');
    return null;
  }

  // If 'all' is selected, return all component directories.
  if (selectedDirs.includes('_allComponents')) {
    return componentDirs;
  }
  return selectedDirs;
}
