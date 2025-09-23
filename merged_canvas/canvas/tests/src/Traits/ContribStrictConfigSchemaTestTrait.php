<?php

declare(strict_types=1);

namespace Drupal\Tests\canvas\Traits;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Symfony\Component\Yaml\Yaml as SymfonyYaml;

/**
 * @see \Drupal\Core\Config\Development\ConfigSchemaChecker::__construct(validateConstraints)
 */
trait ContribStrictConfigSchemaTestTrait {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    // Opt kernel test in to config validation, despite this being contrib.
    $container->getDefinition('testing.config_schema_checker')->setArgument(2, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareSettings() {
    // @phpstan-ignore-next-line
    parent::prepareSettings();
    // Opt functional test in to config validation, despite this being contrib.
    $directory = DRUPAL_ROOT . '/' . $this->siteDirectory;
    $yaml = new SymfonyYaml();
    // @phpstan-ignore-next-line
    $services = $yaml->parse(file_get_contents($directory . '/services.yml'));
    $services['services']['testing.config_schema_checker']['arguments'][2] = TRUE;
    file_put_contents($directory . '/services.yml', $yaml->dump($services));
  }

}
