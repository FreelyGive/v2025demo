<?php

declare(strict_types=1);

namespace Drupal\drupal_cms_helper;

use Drupal\Core\Database\Connection;
use PhpTuf\ComposerStager\API\Path\Value\PathInterface;
use PhpTuf\ComposerStager\API\Process\Service\ComposerProcessRunnerInterface;
use PhpTuf\ComposerStager\API\Process\Service\OutputCallbackInterface;
use PhpTuf\ComposerStager\API\Process\Service\ProcessInterface;
use PhpTuf\ComposerStager\API\Process\Service\RsyncProcessRunnerInterface;

/**
 * @internal
 *   This is an internal part of Drupal CMS and may be changed or removed at any
 *   time without warning. External code should not interact with this class.
 *
 * @todo Remove when https://drupal.org/i/3541137 is fixed and released.
 */
final readonly class ProcessRunner implements ComposerProcessRunnerInterface, RsyncProcessRunnerInterface {

  public function __construct(
    private ComposerProcessRunnerInterface|RsyncProcessRunnerInterface $decorated,
    private Connection $database,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function run(array $command, ?PathInterface $cwd = NULL, array $env = [], ?OutputCallbackInterface $callback = NULL, int $timeout = ProcessInterface::DEFAULT_TIMEOUT): void {
    // Try to prevent the database connection from timing out during long
    // operations. Since the timeout needs to be an integer, we cannot use
    // parameterized queries, but it's okay because the argument is guaranteed
    // to have been coerced to an int.
    // @todo Use SQL CAST instead?
    $driver = $this->database->driver();
    if ($driver === 'mysql') {
      $this->database->query("SET SESSION wait_timeout = " . ($timeout + 5));
    }
    elseif ($driver === 'pgsql') {
      // PostgreSQL timeouts are in milliseconds.
      $this->database->query("SET SESSION idle_session_timeout = " . (($timeout + 5) * 1000));
    }
    $this->decorated->run($command, $cwd, $env, $callback, $timeout);
  }

}
