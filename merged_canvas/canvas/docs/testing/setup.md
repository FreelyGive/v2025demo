# Global setup for testing

Ensure that the module's dependencies are correctly installed. From the
root directory of your Drupal installation, where the root `composer.json`
is located, add the module (altering the path `modules/custom/canvas`
as appropriate):

```shell
composer config repositories.drupal/canvas --json '{"type": "path", "url": "modules/custom/canvas" }'
composer require "drupal/canvas @dev" --with-all-dependencies
```

Then, from the module directory, add the module's dev dependencies:
```
cd modules/custom/canvas
composer run install-dev-deps
```

If you are using a composer scaffolded copy of Drupal (i.e. not the core git
checkout), then install the `drupal/core-dev` package.
```shell
composer require drupal/core-dev --dev --with-all-dependencies
```
