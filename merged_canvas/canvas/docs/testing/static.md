# Static Testing

## PHP

>>>
❗ Setup
First follow the global [setup instructions](./setup.md) to ensure you have all
the required PHP dependencies.
>>>

The following commands are all run from the module directory.

### PHP_CodeSniffer
Also known as [`phpcs`](https://github.com/squizlabs/PHP_CodeSniffer), this
script will detect violations of a defined coding standard.
```shell
composer run phpcs
```

Violations, where possible, can be fixed with `phpcbf` (PHP Code Beautifier and
Fixer).
```shell
composer run phpcbf
```

Configuration is located in [`phpcs.xml`](../../phpcs.xml).

### PHPStan
PHPStan performs static analysis of code to detects potential errors.
```shell
composer run phpstan
```

Configuration is located in [`phpstan.neon`](../../phpstan.neon)

## Node.js

### Setup
From the module directory, install dependencies:

```shell
npm install
```

You can run all the following linting scripts in one go:
```shell
npm run lint
```

### eslint
This script will detect violations of a defined coding standard and perform
static analysis of code.

```shell
npm run lint:eslint
```

Configuration is located in [`eslint.config.mjs`](../../eslint.config.mjs).

### prettier
A code formatter to enforce coding style across the project.
```shell
npm run lint:prettier
```

Configuration is located in [`.prettierrc.json`](../../.prettierrc.json) and
[`.prettierignore`](../../.prettierignore).

### cspell
A spell checker for code.

```shell
npm run lint:cspell
```

Configuration is located in [`.cspell.json`](../../.cspell.json).
