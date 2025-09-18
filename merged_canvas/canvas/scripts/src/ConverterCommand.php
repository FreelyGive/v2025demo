<?php
// @codingStandardsIgnoreFile
// cspell:ignore renamer

declare(strict_types = 1);

// Do not use `experience_builder` namespace so this script won't change it
// in composer.json.
namespace Drupal\renamer\Rename;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Renames the experience_builder module to canvas.
 *
 * Cspell:disable.
 *
 * File usage:
 *  - TBD.
 */
class ConverterCommand extends Command {

  private string $projectRoot;

  private Filesystem $fs;

  /**
   * @return array<string, string>
   */
  public function getReplacements(): array {
    $replacements = [
      'experience_builder' => 'canvas',
      'xb-canvas' => 'editor-frame',
      'xb_asset_library' => 'asset_library',
      // Next two are just oauth2 scopes weird requirements.
      'oauth2_scope.asset_library' => 'oauth2_scope.canvas_asset_library',
      'id: asset_library' => 'id: canvas_asset_library',
      'xb_page' => 'canvas_page',
      // canvas_test will make fields names that are too long.
      'xbt' => 'cvt',
      'EXPERIENCE_BUILDER' => 'CANVAS',
      'Experience Builder' => 'Drupal Canvas',
      'Experience-Builder' => 'Canvas',
      'experience-builder' => 'canvas',
      'experience builder' => 'Drupal Canvas',
      'Experience builder' => 'Drupal Canvas',
      'xb' => 'canvas',
      // Class constants must be uppercase!
      'XB_' => 'CANVAS_',
      'XB' => 'Canvas',
      'Xb' => 'Canvas',
      'ExperienceBuilder' => 'Canvas',
      'Drupal Drupal Canvas' => 'Drupal Canvas',
      'xBEditor' => 'canvasEditor',
      // We cannot replace xB because that would match things like ajaxBehaviors.
      // If we need for some reason, we need to revert that here.
    ];

    // Those components that include an enum prop will change its version,
    // because their settings reference the now renamed
    // `canvas_load_allowed_values_for_component_prop` function.
    $replacements += [
      // sdc.canvas_test_sdc.one_column
      '836c8835c850cdc5' => '0555ab081a3c8721',
      // sdc.canvas_test_sdc.two_column
      'd99140cbd47c0b51' => 'b1ae1e286c75438e',
      // sdc.canvas_test_sdc.heading
      '9616e3c4ab9b4fce' => '8dd7b865998f53b0',
      // sdc.canvas_test_sdc.my-cta
      'b4cd62533ff9bd99' => '6f8647435386329e',
      // js.my-cta
      '9454c3bca9bbbf4b' => '53ed322c96bee384',
      // js.canvas_test_code_components_with_props
      '4e53ca9f3f06b418' => 'cd8b163a8d299fea',
      // js.canvas_test_code_components_vanilla_image
      'c69815f5c7412502' => 'b5e039b71b27fef6',
      // sdc.canvas_test_sdc.image, because of $ref: json-schema-definitions://canvas.module/image
      'c06e0be7dd131740' => 'cc9b97c9370aabdf',
      'd3a3df7d7e68efc0' => '5eabbfdb96b39a59',
      // sdc.canvas_test_sdc.image-optional-with-example-and-additional-prop, because of $ref: json-schema-definitions://canvas.module/image
      '602623740c98a6cf' => '43b51c7233d50b97',
      // block.canvas_test_block_input_schema_change_poc
      '731594cf105d2a9f' => '6388b43679123f84',
      '739aaf363770b8b9' => 'af78995aa8d4160e',
      // block.canvas_test_block_input_none
      '86af6a7a4e4644d5' => 'ea2d88e625ba1e74',
      // @todo Should we just ignore `ComponentInputsEvolutionTest` that requires updating the fixture dump?
      '64ca25db5092fad7' => 'f91f8d4aff4aba7c',
      'ea2d88e625ba1e74' => '7cc894b85e93a7d8',
      '0b69de6df4584ecc' => '88c370526c14d185',
      '9704fc9dd83ff450' => '5ddd92c66000b6d0',
      '922675dd0e16c16e' => '7683487645918f28',
      'af78995aa8d4160e' => 'ec03b64ff4f992b9',
      '45c41f776f70991e' => 'af78995aa8d4160e',
    ];
    // Handle generated `itok` in images in tests.
    $replacements += [
      'uFWMj39h' => 'uQqtnjV1',
      'ebZalNOg' => 'DnW_VIs-',
      '3D6Jb0oZWl' => '3D801KACCy',
      '3DdQpNrzPR' => '3DYLIQc4vO',
      '6Jb0oZWl' => 'spSF5vvd',
      'dQpNrzPR' => 'SnSVAYVj',
    ];

    // JS Components astro-islands asset filenames also depend on their normalized settings.
    $replacements += [
      '1Dq8BIqr4CMOA9RWhpbDNM4mjbvezQDq0mKKzO7iEmw' => 'OXEtkRiIQlg16fvA1lWA_1ggYYS5VOUJpRZ5r3ow2N8',
      'fvdcbYvgbnFGHremAlwsfbIcqUtlrp6B1uNETJtRsbo' => '9R7mSubaIqZ03U019LY2_xnqOKyDzLzQ0y11jg724VY',
    ];
    return $replacements;
  }

  /**
   * {@inheritdoc}
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->fs = new Filesystem();
    // Traverse upwards until we find `composer.json`.
    $previous_dir = NULL;
    $current_dir = getcwd();
    while ($previous_dir !== $current_dir) {
      // Allow the script to also be run after it has run once.
      // @todo Is this a good idea?
      if (file_exists($current_dir . '/experience_builder.info.yml') || file_exists($current_dir . '/canvas.info.yml')) {
        $this->projectRoot = $current_dir;
        break;
      }
      $previous_dir = $current_dir;
      $current_dir = dirname($current_dir);
    }
    if (empty($this->projectRoot)) {
      throw new \Exception("Could not find the project root.");
    }

    $this->doConvert();

    // Run prettier on ui because of line lengths.
    $this->executeCommand("cd ui && npm run lint:fix");
    $this->executeCommand("npm run lint:prettier-fix");

    // @todo Formerly XbPage becomes Page class, with `canvas` as plugin id
    //   1. Do changes manually or with rector?
    //   2. recipes' `xb_page` folder should become `canvas` folder.
    //   3. Ideally we are using constants everywhere, but search for any remaining instances
    //      of xb_page or cv_page.

    // @todo Fix tests (manually or rector?) that depend on order:
    //   1. Those arrays listing a bunch of components
    //   2. Those arrays listing a bunch of cache tags or cache contexts.
    //   2. The DX routing tests depending on routing.yml sorted alphabetically.
    //   3. The routing.yml file itself should still be sorted alphabetically.

    // @todo Verify openapi.yml

    return Command::SUCCESS;
  }

  /**
   * Executes a command and throws an exception if it fails.
   *
   * @param string $cmd
   *   The command to execute.
   */
  private static function executeCommand(string $cmd): void {
    $result = NULL;
    static::info("Running $cmd");
    system($cmd, $result);
    if ($result !== 0) {
      throw new \Exception("Command failed: $cmd");
    }
  }

  /**
   * Prints message.
   *
   * @param string $msg
   *   The message to print.
   */
  private static function info(string $msg): void {
    print "\n$msg";
  }

  /**
   * Converts the contrib module to core merge request.
   */
  private function doConvert(): void {
    $replacements = $this->getReplacements();

    /*$debug_path = '/Users/ted.bowman/sites/debug-renam.txt';
    // Delete the debug file if it exists.
    if (file_exists($debug_path)) {
      unlink($debug_path);
    }
    foreach (static::getDirContents($this->projectRoot) as $file) {
      // Add full path of $file to debug file.
      file_put_contents($debug_path, $file->getRealPath() . "\n", FILE_APPEND);
    }*/
    foreach ($replacements as $search => $replace) {
      $this->renameFiles(static::getDirContents($this->projectRoot), $search, $replace);
    }
    $renamed_files = static::getDirContents($this->projectRoot, TRUE);
    foreach ($renamed_files as $file) {
      // Exclude package.json and package-lock.json for now, as this might cause changes in the integrity validation
      // hashes for packages.
      if (in_array($file->getFilename(), ['package.json', 'package-lock.json']) || in_array($file->getExtension(), ['webp', 'png', 'avi', 'mkv', 'JPEG', 'JPG', 'jpeg', 'jpg', 'gif', 'png', 'mp4', 'gz'])) {
        continue;
      }
      static::replaceContents($file->getRealPath(), $replacements);
    }

    self::info('Replacements done.');
  }

  /**
   * Replaces a string in the contents of the module files.
   *
   * @param string $file
   * @param array<string, string> $replacements
   */
  private static function replaceContents(string $file, array $replacements): void {
    $contents = file_get_contents($file);
    foreach ($replacements as $search => $replace) {
      $contents = str_replace($search, $replace, $contents);
    }
    file_put_contents($file, $contents);
  }

  /**
   * Renames the module files.
   *
   * @param array $files
   *   Files to replace.
   * @param string $old_pattern
   *   The old file name.
   * @param string $new_pattern
   *   The new file name.
   */
  private function renameFiles(array $files, string $old_pattern, string $new_pattern): void {
    // Keep a record of the files and directories to change.
    // We will change all the files first, so we don't change the location of
    // any files in the middle. This probably won't work if we had nested
    // folders with the pattern on 2 folder levels, but we don't.
    $filesToChange = [];
    $dirsToChange = [];
    foreach ($files as $file) {
      $fileName = $file->getFilename();
      if ($fileName === '.') {
        $fullPath = $file->getPath();
        $parts = explode('/', $fullPath);
        $name = array_pop($parts);
        $path = "/" . implode('/', $parts);
      }
      else {
        $name = $fileName;
        $path = $file->getPath();
      }
      if (strpos($name, $old_pattern) !== FALSE) {
        $new_filename = str_replace($old_pattern, $new_pattern, $name);
        if ($file->isFile()) {
          $filesToChange[$file->getRealPath()] = $file->getPath() . "/$new_filename";
        }
        else {
          // Store directories by path depth.
          $depth = count(explode('/', $path));
          $dirsToChange[$depth][$file->getRealPath()] = "$path/$new_filename";
        }
      }
    }
    foreach ($filesToChange as $old => $new) {
      $this->fs->rename($old, $new);
    }
    // Rename directories starting with the most nested to avoid renaming
    // parents directories first.
    krsort($dirsToChange);
    foreach ($dirsToChange as $dirs) {
      foreach ($dirs as $old => $new) {
        $this->fs->rename($old, $new);
      }
    }
  }

  /**
   * Gets the contents of a directory.
   *
   * @param string $path
   *   The path of the directory.
   * @param bool $excludeDirs
   *   (optional) If TRUE, all directories will be excluded. Defaults to FALSE.
   *
   * @return \SplFileInfo[]
   *   Array of objects containing file information.
   */
  private static function getDirContents(string $path, bool $excludeDirs = FALSE): array {
    $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));

    $files = [];
    /** @var \SplFileInfo $file */
    foreach ($rii as $file) {
      if ($file->isLink()) {
        continue;
      }
      // Exclude the .git directories always.
      if ($file->getFilename() === '.git' || $file->getFilename() === 'node_modules' || $file->getFilename() === '..' || $file->getRealPath() === $path) {
        continue;
      }
      if (str_contains($file->getRealPath(), 'node_modules') || str_contains($file->getRealPath(), 'ConverterCommand') || str_contains($file->getRealPath(), '/.git/') || str_ends_with($file->getRealPath(), '/.git')) {
        continue;
      }
      if ($excludeDirs && $file->isDir()) {
        continue;
      }

      $files[] = $file;
    }

    return $files;
  }

  /**
   * Ensures the git status is clean.
   *
   * @return bool
   *   TRUE if git status is clean , otherwise returns a exception.
   */
  private static function ensureGitClean(): bool {
    $status_output = shell_exec('git status');
    if (strpos($status_output, 'nothing to commit, working tree clean') === FALSE) {
      throw new \Exception("git not clean: " . $status_output);
    }
    return TRUE;
  }


}
