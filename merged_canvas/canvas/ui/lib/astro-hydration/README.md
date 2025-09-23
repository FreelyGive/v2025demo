# Astro Island Hydration Library
The purpose of this library is to build the necessary Astro files for the
JS component source plugin to power in-browser JS components in Canvas.

## Why Astro?
[Astro](https://astro.build/) can bundle JS components from any JS rendering framework. Using Astro's special `<astro-island/>`
tag and hydration script, we can render JS components server-side and hydrate them by attaching the necessary Astro code.

## How it works
This directory is an Astro project and is defined as an [npm workspace](https://docs.npmjs.com/cli/v7/using-npm/workspaces) in ui/package.json. It is built as part of Canvas UI's build process.

The build step `astro build` generates bundles for Preact (preact.module.js), hooks (hooks.module.js), etc. that the in-browser-editable components depend on.

`client.js`, `client.css`, and `hydration.js` are defined as libraries in `canvas.libraries.yml` to make them accessible to the JS component source plugin.

The `client.css` file contains basic styling for Astro elements to ensure proper display using `display: contents`.

***The rest of the built files do not need to be defined as Drupal libraries because they are imported by `client.js`.**

----
Relevant reading: [Islands architecture](https://docs.astro.build/en/concepts/islands/)
