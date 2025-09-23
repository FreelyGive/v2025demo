#!/usr/bin/env node
import chalk from 'chalk';
import { Command } from 'commander';

import packageJson from '../package.json';
import { buildCommand } from './commands/build';
import { downloadCommand } from './commands/download';
import { scaffoldCommand } from './commands/scaffold';
import { uploadCommand } from './commands/upload';

const version = packageJson.version;

const program = new Command();
program
  .name('canvas')
  .description('CLI tool for managing Drupal Canvas code components')
  .version(version ?? '0.0.0');

// Register commands
downloadCommand(program);
scaffoldCommand(program);
uploadCommand(program);
buildCommand(program);

// Handle errors
program.showHelpAfterError();
program.showSuggestionAfterError(true);

try {
  // Parse command line arguments and execute the command
  await program.parseAsync(process.argv);
} catch (error) {
  if (error instanceof Error) {
    console.error(chalk.red(`Error: ${error.message}`));
    process.exit(1);
  }
}
