import fs from 'fs/promises';
import path from 'path';
import chalk from 'chalk';
import * as p from '@clack/prompts';

import { getConfig, setConfig } from '../config.js';

import type { Command } from 'commander';

interface ScaffoldOptions {
  name?: string;
  dir?: string;
  verbose?: boolean;
}

// @todo: Support non-interactive scaffold if user passes all necessary args in.
/**
 * Scaffolding command for creating an example React component.
 */
export function scaffoldCommand(program: Command): void {
  program
    .command('scaffold')
    .description('create a new code component scaffold for Drupal Canvas')
    .option(
      '-n, --name <n>',
      'Component name (used for directory and metadata)',
    )
    .option(
      '-d, --dir <directory>',
      'Component directory to create component in',
    )
    .option('--verbose', 'Enable verbose output')
    .action(async (options: ScaffoldOptions) => {
      p.intro('Drupal Canvas Component Scaffold');

      try {
        // Update config with CLI options
        if (options.dir) setConfig({ componentDir: options.dir });
        if (options.verbose) setConfig({ verbose: options.verbose });
        const config = getConfig();
        const baseDir = config.componentDir;

        // Get component name
        let componentName = options.name;
        if (!componentName) {
          const name = await p.text({
            message: 'Enter the component name',
            placeholder: 'my-component',
            validate: (value) => {
              if (!value) return 'Component name is required';
              if (!/^[a-zA-Z0-9-_]+$/.test(value))
                return 'Component name can only contain letters, numbers, hyphens, and underscores';
              return;
            },
          });

          if (p.isCancel(name)) {
            p.cancel('Operation cancelled');
            return;
          }

          componentName = name;
        }

        const componentDir = path.join(baseDir, componentName);
        const s = p.spinner();
        s.start(`Creating component "${componentName}"`);

        try {
          // Create directory
          await fs.mkdir(componentDir, { recursive: true });

          // Get template directory path
          const templateDir = path.join(
            path.dirname(new URL(import.meta.url).pathname),
            'templates/hello-world',
          );

          // List template files
          const files = await fs.readdir(templateDir);

          const newDirFiles = await fs.readdir(componentDir);

          // Check with the user if the directory has existing files.
          if (newDirFiles.length > 0) {
            const confirmDelete = await p.confirm({
              message: `The "${componentDir}" directory is not empty. Do you want to proceed and potentially overwrite existing files?`,
              initialValue: true,
            });
            if (p.isCancel(confirmDelete) || !confirmDelete) {
              p.cancel('Operation cancelled');
              process.exit(0);
            }
          }

          // Copy and process each template file
          for (const file of files) {
            const srcPath = path.join(templateDir, file);
            const destPath = path.join(componentDir, file);

            // Read template content
            let content = await fs.readFile(srcPath, 'utf-8');

            // Replace placeholders with correctly formatted component name.
            const { pascalCaseName, className, displayName, machineName } =
              generateComponentNameFormats(componentName);
            content = content
              .replace(/HelloWorld/g, pascalCaseName)
              .replace(/hello-world-component/g, className)
              .replace(/Hello World/g, displayName)
              .replace(/hello_world/g, machineName);

            // Write processed file
            await fs.writeFile(destPath, content, 'utf-8');
          }

          s.stop(chalk.green(`Created component "${componentName}"`));

          // Show summary and next steps
          p.note(`Component "${componentName}" has been created:
- Directory: ${componentDir}
- Component metadata: ${path.join(componentDir, `component.yml`)}
- Source file: ${path.join(componentDir, `index.jsx`)}
- CSS file: ${path.join(componentDir, `index.css`)}`);

          p.outro('🏗️ Scaffold command completed');
        } catch (error) {
          s.stop(chalk.red(`Failed to create component "${componentName}"`));
          throw error;
        }
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

/**
 * Generates different case formats for a component name.
 * @param componentName
 * @returns Object containing different case formats
 */
function generateComponentNameFormats(componentName: string) {
  const pascalCaseName = componentName
    .replace(/[-_\s]+(.)?/g, (_, c) => (c ? c.toUpperCase() : ''))
    .replace(/^(.)/, (c) => c.toUpperCase());

  const displayName = componentName
    .replace(/-/g, ' ')
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase());

  const machineName = componentName.replace(/-/g, '_').toLowerCase();

  return {
    pascalCaseName,
    displayName,
    machineName,
    className: `${componentName}-component`,
  };
}
