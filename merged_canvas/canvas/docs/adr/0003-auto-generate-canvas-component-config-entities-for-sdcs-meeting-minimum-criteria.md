# 3. Auto-generate Canvas Component config entities for SDCs meeting minimum criteria

Date: 2024-09-12

Issue: <https://www.drupal.org/project/canvas/issues/3468112>

## Status

Accepted

## Context

This can be considered an addition to [ADR #2](0002-Use-SDC-slots-to-build-component-tree-and-field-types-for-populating-SDC-props.md).

All compatible [Single-Directory Components](https://www.drupal.org/project/sdc) must be available to user in Experience
Builder without manual setup steps. Metadata of the available SDCs must be respected wherever possible. For example: the
first example value for each SDC prop will be used as the default value.

This is important for the DX of the Front-End Developer, as well as for the UX for the Site Builder.

## Decision

1. SDCs must be made available automatically upon module/theme installation, except in production environments.
2. _If_ they meet the [documented criteria](../components.md), a [`Component` config entity](../config-management.md) is
   auto-generated (except when syncing configuration of course).
3. They will be available immediately in the Canvas UI.
4. SDCs that do not meet criteria will be listed along with the reason _why_ at `/admin/appearance/component/status`.

## Consequences

- Changing the default value for an SDC prop will [require the SDC's metadata to be changed](
https://www.drupal.org/project/canvas/issues/3462705#consequences).
- Not every SDC will be available in Canvas.

- [Details for the supported components are documented.](../components.md)
- [Details for the `Component` config entity are documented.](../config-management.md)
