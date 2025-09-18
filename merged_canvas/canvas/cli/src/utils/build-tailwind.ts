import { promises as fs } from 'fs';
import path from 'path';
import { compileCss, extractClassNameCandidates } from 'tailwindcss-in-browser';
import { upsertClassNameCandidatesInComment } from '@drupal-canvas/ui/features/code-editor/utils/classNameCandidates';

import { getConfig } from '../config';
import { transformCss } from '../lib/transform-css';
import { createApiService } from '../services/api';
import {
  CANVAS_CACHE_DIR,
  cleanUpCacheDirectory,
  copyLocalJsSource,
  downloadJsSourceFromCanvas,
} from './process-cache-dir';

import type { Component } from '../types/Component';
import type { Result } from '../types/Result';

export async function getAllClassNameCandidatesFromCacheDir(
  componentsToDownload: Record<string, Component>,
  localComponentsToCopy: string[],
) {
  if (Object.keys(componentsToDownload).length > 0) {
    // Download the JS source of all online code components to ~/.canvas.
    await downloadJsSourceFromCanvas(componentsToDownload);
  }
  // Copy local JS source files to ~/.canvas.
  await copyLocalJsSource(localComponentsToCopy);

  const cacheEntries = await fs.readdir(CANVAS_CACHE_DIR, {
    withFileTypes: true,
  });
  const cacheDirs = cacheEntries
    .filter((entry) => entry.isDirectory())
    .map((dir) => path.join(CANVAS_CACHE_DIR, dir.name));

  let allClassNameCandidates: string[] = [];

  // Get the class name candidates from all components in the cache directory.
  for (const cacheDir of cacheDirs) {
    const componentClassNameCandidates =
      await getClassNameCandidatesForComponent(cacheDir);
    allClassNameCandidates = [
      ...allClassNameCandidates,
      ...componentClassNameCandidates,
    ];
  }
  // Delete the contents of the cache directory at the end.
  await cleanUpCacheDirectory();

  return allClassNameCandidates;
}

// Builds CSS using the given class name candidates.
export async function buildTailwindCss(
  classNameCandidates: string[],
  globalSourceCodeCss: string,
  distDir: string,
) {
  const compiledTwCss = await compileCss(
    classNameCandidates,
    globalSourceCodeCss,
  );
  const transformedTwCss = await transformCss(compiledTwCss);
  await fs.writeFile(path.join(distDir, 'index.css'), transformedTwCss);
}

// Extracts class name candidates from a component's JS source code.
export async function getClassNameCandidatesForComponent(
  dir: string,
): Promise<string[]> {
  const componentName = path.basename(dir);
  const config = getConfig();
  const componentsDir = config.componentDir;
  const distDir = path.join(componentsDir, 'dist');

  // Read the JS source code of the component from the cache directory.
  const jsSource = await fs.readFile(path.join(dir, 'index.jsx'), 'utf-8');

  // Read the current global source code JS from the components' directory.
  // components/dist/index.js read the global source code JS.
  const currentGlobalSourceCodeJs = await fs.readFile(
    path.join(distDir, 'index.js'),
    'utf-8',
  );
  // Extract class name candidates from the source code.
  const classNameCandidates = extractClassNameCandidates(jsSource);

  // Add it to our globally tracked index of class name candidates, which
  // are extracted from all code components. They're stored as a JS comment
  // in the global asset library.
  // @see ui/src/features/code-editor/utils/classNameCandidates.ts
  const { nextSource: globalJSClassNameIndex, nextClassNameCandidates } =
    upsertClassNameCandidatesInComment(
      currentGlobalSourceCodeJs,
      componentName,
      classNameCandidates,
    );

  // Write this to components/dist/index.js.
  await fs.writeFile(path.join(distDir, 'index.js'), globalJSClassNameIndex);

  return nextClassNameCandidates;
}

/**
 * Complete Tailwind CSS building workflow that can be shared between commands
 */
export async function buildTailwindForComponents(
  selectedComponents: string[],
): Promise<Result> {
  try {
    const config = getConfig();
    const apiService = await createApiService();

    // Fetch all components from the API to prepare for the Tailwind CSS build.
    const onlineComponents = await apiService.listComponents();
    const globalAssetLibrary = await apiService.getGlobalAssetLibrary();
    const globalSourceCodeJs = globalAssetLibrary.js.original;
    const globalSourceCodeCss = globalAssetLibrary.css.original;

    // Write the existing global JS source code to the components' dist directory.
    const distDir = path.join(config.componentDir, 'dist');
    await fs.mkdir(distDir, { recursive: true });
    const targetFile = path.join(distDir, 'index.js');
    await fs.writeFile(targetFile, globalSourceCodeJs, 'utf-8');

    // Gather all class name candidates from both local and online components.
    const allClassNameCandidates = await getAllClassNameCandidatesFromCacheDir(
      onlineComponents,
      selectedComponents,
    );

    await buildTailwindCss(
      allClassNameCandidates,
      globalSourceCodeCss,
      distDir,
    );

    return {
      itemName: 'Tailwind CSS',
      success: true,
    };
  } catch (error) {
    return {
      itemName: 'Tailwind CSS',
      success: false,
      details: [
        {
          heading: 'Error',
          content: error instanceof Error ? error.message : String(error),
        },
      ],
    };
  }
}
