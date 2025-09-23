import { existsSync, readFileSync } from 'fs';
import { exec as execNode } from 'node:child_process';
import * as path from 'node:path';
import { resolve } from 'path';
import { promisify } from 'util';

const exec = promisify(execNode);

export const getRootDir = (): string => {
  let dir = process.cwd();
  let found = false;
  for (let i = 0; i < 15; i++) {
    if (
      existsSync(`${dir}/index.php`) &&
      existsSync(`${dir}/core/lib/Drupal.php`)
    ) {
      found = true;
      break;
    }
    dir = resolve(dir, '..');
  }
  if (!found) {
    throw new Error('Unable to find Drupal root directory.');
  }
  return dir;
};

export const getComposerDir = async (): Promise<string | null> => {
  const rootDir = getRootDir();
  let composerRoot = rootDir;
  if (!existsSync(`${composerRoot}/composer.json`)) {
    composerRoot = `${rootDir}/..`;
  }
  return composerRoot;
};

export const getVendorDir = async (): Promise<string | null> => {
  const composerRoot = await getComposerDir();
  if (existsSync(`${composerRoot}/composer.json`)) {
    try {
      const { stdout }: { stdout: string } = await exec(
        'composer config vendor-dir --no-interaction',
        { cwd: composerRoot },
      );
      return path.resolve(`${composerRoot}/${stdout.toString().trim()}`);
    } catch (error) {
      throw new Error(`Could not locate vendor directory: ${error}`);
    }
  }
  throw new Error('Could not locate vendor directory.');
};

export const getModuleDir = async (): Promise<string | null> => {
  let modulePath = 'modules/contrib';
  const composerRoot = await getComposerDir();

  try {
    const composerJson = readFileSync(`${composerRoot}/composer.json`, 'utf8');
    const composerData = JSON.parse(composerJson);
    const installerPaths = composerData.extra['installer-paths'];
    Object.keys(installerPaths).forEach((key) => {
      if (installerPaths[key].includes('type:drupal-module')) {
        modulePath = key.replace('/{$name}', '');
      }
    });
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
  } catch (error) {
    console.log('Unable to locate module directory, using default value.');
  }

  return `${composerRoot}/${modulePath}`;
};

export const hasDrush = async (): Promise<boolean> => {
  const vendorDir = await getVendorDir();
  return existsSync(`${vendorDir}/bin/drush`);
};
