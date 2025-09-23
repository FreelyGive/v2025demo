# Contributing

For joining the development process of Drupal Canvas (Canvas for short) or trying the development process, we strongly recommend the use of [DDEV](https://ddev.com/get-started/) (version 1.24.0 or later), and the use of the [drupal-canvas/ddev-drupal-canvas-dev addon](https://github.com/drupal-canvas/ddev-drupal-canvas-dev).

## Useful links
1. [Issue queue](https://www.drupal.org/project/issues/canvas?categories=All)
2. [Source code](https://git.drupalcode.org/project/canvas)

## DDEV for your local environment

```shell
# Extracted from the ddev-drupal-canvas-dev plugin
mkdir ~/Sites/canvas-dev
cd ~/Sites/canvas-dev
ddev config --project-type=drupal --php-version=8.3 --docroot=web
# Canvas requires Drupal >= 11.2
ddev composer create drupal/recommended-project:11.x@dev --no-install
ddev add-on get drupal-canvas/ddev-drupal-canvas-dev
# This will clone the 'canvas' repo under web/modules/contrib
ddev canvas-setup
ddev canvas-dev-extras
```
Additionally, you should add  `$settings['extension_discovery_scan_tests'] = TRUE;` to the end of the `sites/default/settings.php` file (this allows hidden modules to be installed).

### First experience
After this process, you will get the `drush uli` to login into the admin area. You will see an article created. If you edit the article, you will see a new link "Drupal Canvas: Test" to edit this entity with the new Canvas UI.

### Usage
The most common commands in the development process are:
1. `ddev canvas-site-install`: This will reinstall the site and enable a couple of commands. Very useful when you update Canvas and what to have a fresh installation.
2. `ddev canvas-ui-build`: This will build the `canvas/ui` javascript application. Required whenever you update Canvas.
3. Tests and linting: The commands `canvas-eslint`, `canvas-fix`, `canvas-phpcs`, `canvas-phpstan` and `canvas-phpunit` must be used before any commit to ensure code looks good and pass tests.
4. More commands with `ddev | grep canvas-` and in the [ddev-drupal-canvas-dev repo usage](https://github.com/drupal-canvas/ddev-drupal-canvas-dev#usage).

Tip: Use `ddev help <command>` for additional information about the command and arguments available.

## Setting up you local manually
1. Clone Drupal 11 (preferably a clone for Git archeology: `git clone git@git.drupal.org:project/drupal.git` — Drupal >=11.2 is required, so also do: `git checkout 11.2.x`).
2. `cd drupal && git clone git@git.drupal.org:project/canvas.git modules/contrib/canvas`
3. `composer require drush/drush`
4. Add `$settings['extension_discovery_scan_tests'] = TRUE;` to the end of the `sites/default/settings.php` file (this allows hidden modules to be installed).
5.a Recommended: using Recipes to set up a standardized environment for development *and* testing:
```
php core/scripts/drupal install minimal
php core/scripts/drupal recipe modules/contrib/canvas/tests/fixtures/recipes/base
php core/scripts/drupal recipe modules/contrib/canvas/tests/fixtures/recipes/test_site
```
5.b If you prefer Drush and "Standard", and then subsequently manually installing `canvas_test_*` modules:
6. Build the front end: `cd modules/contrib/canvas/ui` and then either
    * With Node.js available: `npm install && npm run build`
    * With Docker available: `docker build --output dist .`
7. You can access Drupal Canvas at `/canvas` and start by creating your first Canvas Page!
8. If you're curious: look at the code, step through it with a debugger, and join us!
9. If you want to run *all* tests locally: `composer require drupal/simple_oauth:^6 jangregor/phpstan-prophecy league/openapi-psr7-validator devizzent/cebe-php-openapi --dev && composer update`

### During development
The following commands assume the recommended development details outlined above, particularly the location of the `vendor` directory. If your `vendor` directory is not adjacent to your `index.php` — if you created your environment using [`drupal/recommended-project`](https://packagist.org/packages/drupal/recommended-project), for example — you will need to adjust the command path (i.e., `../vendor` instead of `vendor`).

### Usage of the commands
#### `phpcs`
Manually, from the Drupal project root (i.e. where `index.php` lives):
```shell
vendor/bin/phpcs -s modules/contrib/canvas/ --standard=modules/contrib/canvas/phpcs.xml --basepath=modules/contrib/canvas
```
#### `phpstan`
Manually, from the Drupal project root (i.e. where `index.php` lives):
```shell
php vendor/bin/phpstan analyze modules/contrib/canvas --memory-limit=256M --configuration=modules/contrib/canvas/phpstan.neon
```
#### OpenAPI spec
From the Canvas project root:
```shell
npx @redocly/cli@latest lint openapi.yml
```

# Architectural Decision Records
When architectural decisions are made, they should be recorded in _ADRs_. To create an ADR:

1. Install <https://github.com/npryce/adr-tools> — see [installation instructions](https://github.com/npryce/adr-tools/blob/master/INSTALL.md).
2. From the root of this project: ```adr new This Is A New Decision```.

# C4 model & Auto-Generated Diagrams
To edit: either modify locally, or copy/paste `docs/Canvas.dsl` into <https://structurizr.com/dsl> for easy previewing!

When updating the C4 model in `docs/Canvas.dsl`, the diagrams (in `docs/diagrams/`) must be updated too. To do that:

```
structurizr-cli export -workspace docs/Canvas.dsl --format mermaid --output docs/diagrams && for file in docs/diagrams/*.mmd; do printf "\`\`\`mermaid\n" | cat - $file  > temp && mv -f temp $file && echo $'\n```' >> $file; done && find docs/diagrams/ -name "*.mmd" -exec sh -c 'mv "$1" "${1%.mmd}.md"' _ {} \;
```
This will auto-update `docs/diagrams/structurizr-*.md`.

(After installing `structurizr-cli`: <https://docs.structurizr.com/cli/installation>.)

# Data Model Diagram

To edit: either modify locally, or copy/paste `docs/diagrams/*.md` (not the ones with the `structurizr-` prefix) into <https://mermaid.live/edit> for easy previewing!

# Developer Tips & Tricks

## 1. Inspecting the rendered preview markup
There is a secret keyboard shortcut that is very helpful for debugging the HTML inside the iFrame.

If you press and hold the `V` key, the React app will 'disappear' until you release the `V` key.

If you press & hold the `V` key and then click on the iFrame (focusing into it),  then the V key can be released and the UI will remain hidden. Once hidden, it becomes much easier to use the browser's developer tools to inspect elements inside the iFrame.

You can then click (focus) outside the iFrame and tap the `V` key once more to return the UI.

## 2. Always start from a fresh start
1. Reinstall the site frequently (`ddev canvas-site-install`).
2. If you're working with sqlite, delete the database frequently (`rm web/sites/default/.ht.sqlite`).
3. Build the UI frequently (`ddev canvas-ui-build`).

# Releases

For now, Drupal Canvas does _not_ include the built UI in its `git` repository, because it is currently optimized
for development.

Therefore, every release must build the UI, commit the built UI, and revert it. Similar to Drupal core's releases.

This has been semi-automated: use `sh scripts/tag-release.sh` to be asked what tag to create, and it'll create that tag
in a (temporary) working directory without touching the Drupal Canvas `git` repository the command runs from. Prior
to pushing, you'll be given the opportunity to inspect the result.
