# Drupal Canvas CLI

A command-line interface for managing Drupal Canvas code components, which are
built with standard React and JavaScript. While Drupal Canvas includes a
built-in browser-based code editor for working with these components, this CLI
tool makes it possible to create, build, and manage components outside of that
UI environment.

## Installation

```bash
npm install @drupal-canvas/cli
```

## Setup

1. Install the Drupal Canvas OAuth module (`canvas_oauth`), which is shipped as
   a submodule of Drupal Canvas.
2. Follow the
   [configuration steps of the module](https://git.drupalcode.org/project/canvas/-/tree/1.x/modules/canvas_oauth#22-configuration)
   to set up a client with an ID and secret.

### Configuration

Settings can be configured using:

1. Command-line arguments;
1. Environment variables;
1. A project `.env` file;
1. A global `.canvasrc` file in your home directory.

These are applied in order of precedence from highest to lowest. You can copy
the
[`.env.example` file](https://git.drupalcode.org/project/canvas/-/blob/1.x/cli/.env.example)
to get started.

| CLI argument      | Environment variable   | Description                                                   |
| ----------------- | ---------------------- | ------------------------------------------------------------- |
| `--site-url`      | `CANVAS_SITE_URL`      | Base URL of your Drupal site.                                 |
| `--client-id`     | `CANVAS_CLIENT_ID`     | OAuth client ID.                                              |
| `--client-secret` | `CANVAS_CLIENT_SECRET` | OAuth client secret.                                          |
| `--dir`           | `CANVAS_COMPONENT_DIR` | Directory where code components are stored in the filesystem. |
| `--verbose`       | `CANVAS_VERBOSE`       | Verbose CLI output for troubleshooting. Defaults to `false`.  |
| `--scope`         | `CANVAS_SCOPE`         | (Optional) Space-separated list of OAuth scopes to request.   |

**Note:** The `--scope` parameter defaults to
`"canvas:js_component canvas:asset_library"`, which are the default scopes
provided by the Drupal Canvas OAuth module (`canvas_oauth`).

## Commands

### `download`

Download components to your local filesystem.

**Usage:**

```bash
npx canvas download [options]
```

**Options:**

- `-c, --component <name>`: Download a specific component by machine name
- `--all`: Download all components

Downloads one or more components from your site. You can select components to
download, or use `--all` to download everything. Existing component directories
will be overwritten after confirmation. Also downloads global CSS assets if
available.

---

### `scaffold`

Create a new code component scaffold for Drupal Canvas.

```bash
npx canvas scaffold [options]
```

**Options:**

- `-n, --name <n>`: Machine name for the new component

Creates a new component directory with example files (`component.yml`,
`index.jsx`, `index.css`).

---

### `build`

Build local components and Tailwind CSS assets.

```bash
npx canvas build [options]
```

**Options:**

- `--all`: Build all components
- `--no-tailwind`: Skip Tailwind CSS build

Builds the selected (or all) local components, compiling their source files.
Also builds Tailwind CSS assets for all components (can be skipped with
`--no-tailwind`). For each component, a `dist` directory will be created
containing the compiled output. Additionally, a top-level `dist` directory will
be created, which will be used for the generated Tailwind CSS assets.

---

### `upload`

Build and upload local components and global CSS assets.

```bash
npx canvas upload [options]
```

**Options:**

- `--all`: Upload all components in the directory
- `--no-tailwind`: Skip Tailwind CSS build and global asset upload

Builds and uploads the selected (or all) local components to your site. Also
builds and uploads global Tailwind CSS assets unless `--no-tailwind` is
specified. Existing components on the site will be updated if they already
exist.
