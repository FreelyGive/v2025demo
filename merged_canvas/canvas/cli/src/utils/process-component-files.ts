import { promises as fs } from 'fs';
import path from 'path';
import * as yaml from 'js-yaml';

import type { Component } from '../types/Component';
import type { Metadata } from '../types/Metadata';

/**
 * Process and read component files
 * @param componentDir Component directory path
 * @returns Processed component files and paths
 */
export async function processComponentFiles(componentDir: string): Promise<{
  sourceCodeJs: string;
  compiledJs: string;
  sourceCodeCss: string;
  compiledCss: string;
  metadata: Metadata | undefined;
}> {
  const metadataPath = await findMetadataPath(componentDir);
  const metadata = await readComponentMetadata(metadataPath);
  const distDir = path.join(componentDir, 'dist');
  const sourceCodeJs = await fs.readFile(
    path.join(componentDir, 'index.jsx'),
    'utf-8',
  );
  const compiledJs = await fs.readFile(path.join(distDir, 'index.js'), 'utf-8');

  let sourceCodeCss = '';
  let compiledCss = '';

  try {
    sourceCodeCss = await fs.readFile(
      path.join(componentDir, 'index.css'),
      'utf-8',
    );
    // If source CSS exists, compiled CSS should also exist
    compiledCss = await fs.readFile(path.join(distDir, 'index.css'), 'utf-8');
  } catch {
    // CSS files don't exist, use empty strings
  }

  return {
    sourceCodeJs,
    compiledJs,
    sourceCodeCss,
    compiledCss,
    metadata,
  };
}

/**
 * Find the component metadata file
 * @param componentDir Component directory path
 * @returns Path to the found metadata file
 */
export async function findMetadataPath(componentDir: string): Promise<string> {
  const metadataPath = path.join(componentDir, 'component.yml');

  try {
    await fs.access(metadataPath);
    return metadataPath;
  } catch (e) {
    console.error(`Error finding component metadata at ${metadataPath}:`, e);
  }
  return '';
}

/**
 * Reads and validates component metadata from a YAML file
 * @param filePath Path to the YAML file
 * @returns Properly structured component metadata
 */
export async function readComponentMetadata(
  filePath: string,
): Promise<Metadata | undefined> {
  try {
    const content = await fs.readFile(filePath, 'utf-8');
    // Make sure we return an object even if the file is empty
    const rawMetadata = yaml.load(content) || {};

    if (typeof rawMetadata !== 'object') {
      console.error(
        `Invalid metadata format in ${filePath}. Expected an object, got ${typeof rawMetadata}`,
      );
      return undefined;
    }

    // Basic validation and normalization
    const metadata = rawMetadata as Metadata;

    // Ensure other required fields
    if (!metadata.name) {
      metadata.name = path.basename(path.dirname(filePath));
    }
    if (!metadata.machineName) {
      metadata.machineName = path.basename(path.dirname(filePath));
    }

    if (!metadata.slots || typeof metadata.slots !== 'object') {
      metadata.slots = {};
    }

    return metadata;
  } catch (error) {
    console.error(`Error reading component metadata from ${filePath}:`, error);
    return undefined;
  }
}

/**
 * Creates a standardized component payload for API requests
 * @param params Component payload parameters
 * @returns Component payload for API
 */
export function createComponentPayload(params: {
  metadata: Metadata;
  machineName: string;
  componentName: string;
  sourceCodeJs: string;
  compiledJs: string;
  sourceCodeCss: string;
  compiledCss: string;
  importedJsComponents: string[];
}): Component {
  const {
    metadata,
    machineName,
    componentName,
    sourceCodeJs,
    compiledJs,
    sourceCodeCss,
    compiledCss,
  } = params;

  // Ensure props is correctly structured
  const propsData = metadata.props.properties;

  // Ensure slots has correct format
  let slotsData = metadata.slots || {};
  if (typeof slotsData === 'string' || Array.isArray(slotsData)) {
    slotsData = {};
  }

  return {
    machineName,
    name: metadata.name || componentName,
    status: true,
    required: Array.isArray(metadata.required) ? metadata.required : [],
    props: propsData,
    slots: slotsData,
    sourceCodeJs: sourceCodeJs,
    compiledJs: compiledJs,
    sourceCodeCss: sourceCodeCss,
    compiledCss: compiledCss,
    importedJsComponents: metadata.importedJsComponents || [],
    dataDependencies: metadata.dataDependencies || [],
  };
}
